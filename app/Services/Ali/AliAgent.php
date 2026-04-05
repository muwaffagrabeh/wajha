<?php

namespace App\Services\Ali;

use App\Actions\Business\CreateBranch;
use App\Actions\Business\CreateBusiness;
use App\Actions\Business\UpdatePolicy;
use App\Actions\Business\UpdateWorkingHours;
use App\Actions\Reports\GetDailyStats;
use App\Actions\Services\CreateService;
use App\Actions\Services\ToggleService;
use App\Actions\Services\UpdateService;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SectorType;
use App\Models\User;
use OpenAI\Laravel\Facades\OpenAI;

class AliAgent
{
    private ?Business $business;
    private ?User $owner;
    private ?string $sessionToken;
    private ?AliGateway $gateway;
    private ?AliValidator $validator;
    private array $conversationHistory = [];

    public function __construct(?User $owner = null, ?Business $business = null, ?string $sessionToken = null)
    {
        $this->owner = $owner;
        $this->business = $business;
        $this->sessionToken = $sessionToken ?? ($owner ? null : bin2hex(random_bytes(16)));
        $this->gateway = $business ? new AliGateway($business) : null;
        $this->validator = $business ? new AliValidator($business) : null;
    }

    public function getSessionToken(): ?string
    {
        return $this->sessionToken;
    }

    public function handleMessage(string $ownerMessage, ?Conversation $conversation = null): array
    {
        // Layer 0: Onboarding State Machine (if no business yet or still onboarding)
        if ($this->sessionToken && (!$this->business || $this->isOnboarding())) {
            $sm = new \App\Services\Onboarding\OnboardingStateMachine($this->sessionToken);
            $smResult = $sm->handle($ownerMessage);

            if (!($smResult['delegate'] ?? false)) {
                // State machine handled it
                if (!empty($smResult['business_id'])) {
                    $this->business = Business::find($smResult['business_id']);
                    $this->gateway = $this->business ? new AliGateway($this->business) : null;
                    $this->validator = $this->business ? new AliValidator($this->business) : null;
                }
                return $this->result($smResult['response'], 'onboarding', [], null, $smResult['state'] ?? null);
            }

            // READY state — delegate to normal flow, ensure business is set
            if (!empty($smResult['business_id'])) {
                $this->business = Business::find($smResult['business_id']);
                $this->gateway = $this->business ? new AliGateway($this->business) : null;
                $this->validator = $this->business ? new AliValidator($this->business) : null;
            }
        }

        // Layer 1: Gateway
        if ($this->gateway) {
            $gatewayResponse = $this->gateway->tryHandle($ownerMessage);
            if ($gatewayResponse !== null) {
                return $this->result($gatewayResponse, 'gateway');
            }
        }

        // Layer 2: LLM
        $parsed = $this->callLLM($ownerMessage);

        // Execute actions chain
        $actionsExecuted = [];
        $lastResult = null;

        if (!empty($parsed['actions']) && is_array($parsed['actions'])) {
            // Multiple actions (setup flow)
            foreach ($parsed['actions'] as $act) {
                $actionName = $act['action'] ?? null;
                $params = $act['params'] ?? [];
                if (!$actionName) continue;

                // Pass business_id from previous create_business
                if ($this->business && !isset($params['business_id'])) {
                    $params['business_id'] = $this->business->id;
                }

                $lastResult = $this->executeAction($actionName, $params);
                $actionsExecuted[] = $actionName;

                if (!($lastResult['success'] ?? false)) break;
            }
        } elseif (!empty($parsed['action'])) {
            // Single action
            $lastResult = $this->executeAction($parsed['action'], $parsed['params'] ?? []);
            $actionsExecuted[] = $parsed['action'];
        }

        // Build response
        $response = $parsed['response_text'] ?? '';
        if ($lastResult && isset($lastResult['message'])) {
            $response = $lastResult['message'];
        }
        // If multiple actions, build combined response
        if (count($actionsExecuted) > 1 && $response === '') {
            $response = '✓ تم تجهيز كل شي. شي ثاني؟';
        }

        return $this->result($response, 'llm', $actionsExecuted, $parsed['intent'] ?? null);
    }

    private function isOnboarding(): bool
    {
        $session = \App\Services\Onboarding\OnboardingSession::load($this->sessionToken);
        return $session->state !== \App\Services\Onboarding\OnboardingState::READY
            || $session->state === \App\Services\Onboarding\OnboardingState::INIT;
    }

    private function result(string $response, string $source, array $actions = [], ?string $intent = null, ?string $state = null): array
    {
        return [
            'response' => $response,
            'source' => $source,
            'action_taken' => !empty($actions) ? implode(', ', $actions) : null,
            'intent' => $intent,
            'needs_input' => false,
            'session_token' => $this->sessionToken,
            'business_id' => $this->business?->id,
            'state' => $state,
        ];
    }

    private function callLLM(string $message): array
    {
        $systemPrompt = $this->buildPrompt();

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($this->conversationHistory as $msg) {
            $messages[] = $msg;
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 1000,
        ]);

        $content = $response->choices[0]->message->content ?? '';
        $tokensUsed = $response->usage->totalTokens ?? 0;

        $this->conversationHistory[] = ['role' => 'user', 'content' => $message];
        $this->conversationHistory[] = ['role' => 'assistant', 'content' => $content];

        $parsed = $this->parseResponse($content);
        $parsed['tokens_used'] = $tokensUsed;

        return $parsed;
    }

    private function buildPrompt(): string
    {
        $sectorTypes = SectorType::with('sector')->get()
            ->map(fn($t) => "    {$t->id}: {$t->label} ({$t->sector->label})")
            ->join("\n");

        $businessContext = '';
        if ($this->business) {
            $services = $this->business->serviceItems()->where('status', 'active')->get();
            $serviceList = $services->map(fn($s) => "    - {$s->name}: {$s->price} ريال")->join("\n");
            $businessContext = <<<CTX

  <current_business>
    الاسم: {$this->business->name}
    القطاع: {$this->business->sector_type_id}
    الخدمات:
{$serviceList}
  </current_business>
CTX;
        }

        $hasBusinessFlag = $this->business ? 'true' : 'false';

        return <<<PROMPT
أنت علي — مدير رقمي يساعد صاحب العمل في إعداد وإدارة نشاطه التجاري.
لهجتك سعودية، مباشر بدون إنشاء، سطر أو سطرين كحد أقصى.

has_business: {$hasBusinessFlag}
{$businessContext}

أنواع الأنشطة المتاحة (sector_type_id):
{$sectorTypes}

=== القواعد ===

إذا has_business = false:
  المستخدم يعرّف نشاطه ← أنت تستخرج: الاسم، المدينة، نوع النشاط (sector_type_id).
  إذا ذكر الثلاثة أو تقدر تستنتجهم ← نفّذ create_business + create_branch فوراً.
  إذا ناقص شي ← اسأل سؤال واحد محدد فقط.
  بعد الإنشاء ← اقترح خدمات افتراضية مناسبة للقطاع أو اسأل المالك عن خدماته.

إذا has_business = true:
  المستخدم يدير نشاطه ← نفّذ الإجراء المناسب مباشرة.

=== هيكل الرد (JSON فقط) ===

للإعداد (إنشاء نشاط + فرع + خدمات):
{
  "intent": "setup",
  "actions": [
    {"action": "create_business", "params": {"name": "...", "sector_type_id": "..."}},
    {"action": "create_branch", "params": {"city": "...", "district": "..."}},
    {"action": "create_service", "params": {"name": "...", "type": "service", "price": 0}},
    {"action": "create_service", "params": {"name": "...", "type": "service", "price": 0}}
  ],
  "response_text": "رد قصير للمالك"
}

لإجراء واحد أو أكثر (بدون إنشاء نشاط):
{
  "intent": "modify",
  "actions": [
    {"action": "create_service", "params": {"name": "لاتيه", "type": "product", "price": 18}},
    {"action": "create_service", "params": {"name": "كابتشينو", "type": "product", "price": 15}}
  ],
  "response_text": "..."
}

لسؤال (معلومات ناقصة):
{
  "intent": "setup",
  "action": null,
  "question": "سؤال واحد محدد",
  "response_text": "السؤال نفسه"
}

=== مهم ===
- إذا المستخدم قال "عندي صالون" ← sector_type_id = salon_women أو barber_men
- إذا قال "مطعم" أو "شاورما" ← sector_type_id = grocery
- إذا قال "محل تنجيد" ← sector_type_id = upholstery_shop
- إذا اقترحت خدمات ← حط سعر 0 واكتب في الرد "حطيت أسعار مؤقتة، عدّلها"
- لا تسأل أكثر من سؤال واحد في الرسالة
- نفّذ بأقصى ما تقدر من المعلومات المتاحة
PROMPT;
    }

    private function parseResponse(string $content): array
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\n?/', '', $content);
            $content = preg_replace('/\n?```$/', '', $content);
        }

        // Handle multiple JSON objects concatenated
        if (substr_count($content, '{"intent"') > 1) {
            // Multiple JSON — merge into actions array
            preg_match_all('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/u', $content, $jsonMatches);
            $actions = [];
            $responseText = '';
            foreach ($jsonMatches[0] as $jsonStr) {
                $obj = json_decode($jsonStr, true);
                if (!$obj) continue;
                if (isset($obj['action']) && $obj['action']) {
                    $actions[] = ['action' => $obj['action'], 'params' => $obj['params'] ?? []];
                }
                if (isset($obj['response_text'])) $responseText = $obj['response_text'];
            }
            if (!empty($actions)) {
                return ['intent' => 'modify', 'actions' => $actions, 'response_text' => $responseText];
            }
        }

        $decoded = json_decode($content, true);
        if ($decoded && isset($decoded['intent'])) {
            // Normalize: if single action without actions array
            if (isset($decoded['action']) && !isset($decoded['actions'])) {
                $decoded['actions'] = [['action' => $decoded['action'], 'params' => $decoded['params'] ?? []]];
            }
            return $decoded;
        }

        return [
            'intent' => 'unknown',
            'action' => null,
            'actions' => null,
            'params' => [],
            'response_text' => $content,
            'question' => null,
        ];
    }

    private function executeAction(string $actionName, array $params): array
    {
        // Inject business_id
        if ($this->business && !isset($params['business_id'])) {
            $params['business_id'] = $this->business->id;
        }
        if ($this->business && !isset($params['branch_id'])) {
            $branch = $this->business->branches()->where('is_default', true)->first()
                ?? $this->business->branches()->first();
            if ($branch) $params['branch_id'] = $branch->id;
        }

        $params['session_token'] = $this->sessionToken;
        $st = $params['session_token'];

        try {
            return match ($actionName) {
                'create_business' => $this->doCreateBusiness($params, $st),
                'create_branch' => $this->doCreateBranch($params, $st),
                'create_service' => $this->doCreateService($params, $st),
                'update_service' => $this->doUpdateService($params, $st),
                'toggle_service' => $this->doToggleService($params, $st),
                'update_working_hours' => $this->doUpdateHours($params, $st),
                'update_policy' => $this->doUpdatePolicy($params, $st),
                'get_daily_stats' => $this->doGetStats($params),
                default => ['success' => false, 'message' => "إجراء غير معروف: {$actionName}"],
            };
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => "خطأ: {$e->getMessage()}"];
        }
    }

    private function doCreateBusiness(array $p, ?string $st): array
    {
        $biz = (new CreateBusiness())->execute($p, 'ali', $st);
        $this->business = $biz;
        $this->gateway = new AliGateway($biz);
        $this->validator = new AliValidator($biz);
        return ['success' => true, 'message' => "✓ تم إنشاء \"{$biz->name}\"", 'data' => ['business_id' => $biz->id]];
    }

    private function doCreateBranch(array $p, ?string $st): array
    {
        if (!isset($p['business_id']) && $this->business) {
            $p['business_id'] = $this->business->id;
        }
        $branch = (new CreateBranch())->execute($p, 'ali', $st);
        return ['success' => true, 'message' => "✓ تم إنشاء فرع \"{$branch->city}\"", 'data' => ['branch_id' => $branch->id]];
    }

    private function doCreateService(array $p, ?string $st): array
    {
        $service = (new CreateService())->execute($p, 'ali', $st);
        return ['success' => true, 'message' => "✓ تم إضافة \"{$service->name}\" بسعر {$service->price} ريال", 'data' => ['service_id' => $service->id]];
    }

    private function doUpdateService(array $p, ?string $st): array
    {
        $service = $this->findService($p);
        if (!$service) return ['success' => false, 'message' => 'ما لقيت الخدمة. وش اسمها بالضبط؟'];

        unset($p['service_id'], $p['business_id'], $p['branch_id'], $p['name'], $p['session_token']);
        $service = (new UpdateService())->execute($service->id, $p, 'ali', $st);
        return ['success' => true, 'message' => "✓ تم تحديث \"{$service->name}\". شي ثاني؟"];
    }

    private function doToggleService(array $p, ?string $st): array
    {
        $service = $this->findService($p);
        if (!$service) return ['success' => false, 'message' => 'ما لقيت الخدمة. وش اسمها بالضبط؟'];

        $active = $p['active'] ?? false;
        $service = (new ToggleService())->execute($service->id, $active, 'ali', $st);
        $status = $active ? 'مفعّلة' : 'موقّفة';
        return ['success' => true, 'message' => "✓ \"{$service->name}\" الحين {$status}. شي ثاني؟"];
    }

    private function doUpdateHours(array $p, ?string $st): array
    {
        $branch = (new UpdateWorkingHours())->execute($p['branch_id'], $p['working_hours'] ?? [], 'ali', $st);
        return ['success' => true, 'message' => "✓ أوقات العمل اتحدثت. شي ثاني؟"];
    }

    private function doUpdatePolicy(array $p, ?string $st): array
    {
        $policy = (new UpdatePolicy())->execute($p['business_id'], $p['key'], $p['value'], 'ali', $st);
        return ['success' => true, 'message' => "✓ سياسة \"{$policy->key}\" اتحدثت. شي ثاني؟"];
    }

    private function doGetStats(array $p): array
    {
        $stats = (new GetDailyStats())->execute($p['business_id'], $p['date'] ?? null);
        return ['success' => true, 'message' => "محادثات: {$stats['total_conversations']} | رسائل: {$stats['total_messages']}"];
    }

    private function findService(array $p): ?\App\Models\ServiceItem
    {
        if (!$this->business) return null;
        $id = $p['service_id'] ?? null;
        $name = $p['name'] ?? $id;

        if ($id) {
            $s = $this->business->serviceItems()->find($id);
            if ($s) return $s;
        }
        if ($name) {
            return $this->business->serviceItems()->where('name', 'like', "%{$name}%")->first();
        }
        return null;
    }
}
