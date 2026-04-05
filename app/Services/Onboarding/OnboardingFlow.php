<?php

namespace App\Services\Onboarding;

use App\Actions\Business\CreateBranch;
use App\Actions\Business\CreateBusiness;
use App\Actions\Services\CreateService;
use App\Blueprints\Blueprint;
use App\Models\SectorType;
use App\Models\SetupRequest;
use App\Models\Specialist;
use App\Models\User;
use App\Services\PromptBuilder\PromptBuilder;

class OnboardingFlow
{
    private DataExtractor $extractor;

    private array $stepConfig = [
        'sector' => ['type' => 'detect'],
        'preview' => ['type' => 'confirm'],
        'name_city' => ['type' => 'extract', 'required' => ['name', 'city']],
        'services' => ['type' => 'confirm_defaults', 'confirm' => true],
        'prices' => ['type' => 'confirm_defaults', 'confirm' => true],
        'service_mode' => ['type' => 'select', 'condition' => 'show_service_mode_step'],
        'travel_fee' => ['type' => 'number_or_free', 'condition' => 'needs_travel_fee'],
        'specialists' => ['type' => 'extract_specialists', 'condition' => 'has_specialists', 'confirm' => true],
        'agent_name' => ['type' => 'confirm_name'],
    ];

    public function __construct()
    {
        $this->extractor = new DataExtractor();
    }

    /**
     * Main entry point.
     * Session data stored in cache keyed by session_token.
     */
    public function handle(string $sessionToken, string $input, ?User $user = null): array
    {
        $data = cache()->get("onboarding:{$sessionToken}", [
            'step' => 'sector',
            'sector_type_id' => null,
            'name' => null,
            'city' => null,
            'services' => null,
            'services_confirmed' => false,
            'service_mode' => null,
            'travel_fee' => null,
            'specialists' => null,
            'agent_name' => null,
        ]);

        $step = $data['step'];
        $sectorType = !empty($data['sector_type_id']) ? SectorType::find($data['sector_type_id']) : null;
        $bp = $sectorType ? Blueprint::fromSectorType($sectorType) : null;

        // Process step, then auto-advance if needed
        $result = $this->processStep($step, $input, $data, $bp, $user);
        $data = $result['data'] ?? $data;

        // Auto-advance loop: if step changed and auto_advance, call next step with ''
        $maxLoops = 10;
        while (($result['auto_advance'] ?? false) && isset($result['next_step']) && $maxLoops-- > 0) {
            $data['step'] = $result['next_step'];
            $step = $data['step'];
            $sectorType = !empty($data['sector_type_id']) ? SectorType::find($data['sector_type_id']) : null;
            $bp = $sectorType ? Blueprint::fromSectorType($sectorType) : null;

            $result = $this->processStep($step, '', $data, $bp, $user);
            $data = $result['data'] ?? $data;
        }

        if (isset($result['next_step'])) {
            $data['step'] = $result['next_step'];
        }
        cache()->put("onboarding:{$sessionToken}", $data, now()->addHours(24));

        // Build debug info
        $sectorType = !empty($data['sector_type_id']) ? SectorType::find($data['sector_type_id']) : null;
        $debug = [
            'step' => $data['step'],
            'previous_step' => $prevStep ?? $step,
            'sector_type_id' => $data['sector_type_id'] ?? null,
            'sector_label' => $sectorType?->label,
            'blueprint' => $sectorType?->blueprint,
            'work_model' => $sectorType?->work_model,
            'has_specialists' => (bool) ($sectorType?->has_specialists),
            'service_mode' => $data['service_mode'] ?? $sectorType?->default_service_mode,
            'onboarding_data' => $data,
            'terms' => $sectorType ? (is_string($sectorType->terms) ? json_decode($sectorType->terms, true) : $sectorType->terms) : null,
            'terminology' => $sectorType ? (is_string($sectorType->terminology) ? json_decode($sectorType->terminology, true) : $sectorType->terminology) : null,
            'llm_output' => $result['llm_output'] ?? null,
            'validation_errors' => $result['validation_errors'] ?? [],
            'actions_executed' => $result['actions_executed'] ?? [],
        ];

        return [
            'response' => $result['reply'],
            'state' => $data['step'],
            'business_id' => $data['business_id'] ?? null,
            'session_token' => $sessionToken,
            'delegate' => $result['delegate'] ?? false,
            'source' => 'onboarding',
            'debug' => $debug,
        ];
    }

    private function processStep(string $step, string $input, array $data, ?Blueprint $bp, ?User $user): array
    {
        $result = match ($step) {
            'sector' => $this->stepSector($input, $data, $user),
            'preview' => $this->stepPreview($input, $data, $bp),
            'name_city' => $this->stepNameCity($input, $data, $bp),
            'services' => $this->stepServices($input, $data, $bp),
            'service_mode' => $this->stepServiceMode($input, $data, $bp),
            'travel_fee' => $this->stepTravelFee($input, $data, $bp),
            'specialists' => $this->stepSpecialists($input, $data, $bp),
            'agent_name' => $this->stepAgentName($input, $data, $bp),
            'DONE' => ['reply' => 'نشاطك جاهز! وش تبي تسوي؟', 'data' => $data, 'delegate' => true],
            'PENDING' => ['reply' => 'طلبك مسجّل وفريقنا يتواصل معك.', 'data' => $data],
            'COLLECT_PENDING_INFO' => $this->stepCollectPendingInfo($input, $data, $user),
            default => ['reply' => 'حصل خطأ.', 'data' => $data],
        };

        // Attach LLM output if extractor was used
        if ($this->extractor->lastLLMOutput) {
            $result['llm_output'] = $this->extractor->lastLLMOutput;
            $this->extractor->lastLLMOutput = null;
        }

        return $result;
    }

    // ═══ STEP: sector ═══

    private function stepSector(string $input, array $data, ?User $user): array
    {
        if ($input === '') {
            return ['reply' => 'أهلاً! أنا علي، مديرك الرقمي.\nقولي وش نوع نشاطك وأجهّز لك كل شي.', 'data' => $data];
        }

        $detected = $this->extractor->detectSectorType($input);
        $typeId = $detected['sector_type_id'] ?? null;

        if (!$typeId) {
            $attempts = ($data['sector_attempts'] ?? 0) + 1;
            $data['sector_attempts'] = $attempts;

            if ($attempts >= 2) {
                return $this->handleNoMatch($data, $input, $user);
            }

            return ['reply' => 'ما تعرفت على نوع النشاط. ممكن توضح أكثر؟\nمثال: صالون نسائي، مطعم، عيادة أسنان', 'data' => $data];
        }

        $st = SectorType::find($typeId);
        if (!$st) {
            return $this->handleNoMatch($data, $input, $user);
        }

        // Save any name/city extracted along with sector
        $data['sector_type_id'] = $typeId;
        if (!empty($detected['name'])) $data['name'] = $detected['name'];
        if (!empty($detected['city'])) $data['city'] = $detected['city'];

        // Check if fully configured (has preview + services data) AND approved
        $isReady = $st->approval_status === 'approved'
            && !empty($st->preview)
            && !empty($st->default_services_with_prices)
            && !empty($st->terms);

        if (!$isReady && (!$user || $user->role !== 'super_admin')) {
            return $this->handlePartialMatch($data, $input, $st, $user);
        }

        $data['step'] = 'preview';
        return ['reply' => '', 'next_step' => 'preview', 'data' => $data, 'auto_advance' => true];
    }

    // ═══ STEP: preview ═══

    private function stepPreview(string $input, array $data, ?Blueprint $bp): array
    {
        // First time or auto-advance → show preview
        if ($input === '' || !isset($data['preview_shown'])) {
            $data['preview_shown'] = true;

            $painPoints = $bp->preview['pain_points'] ?? [];
            $outcomes = $bp->preview['outcomes'] ?? [];

            $msg = "يومك قبل واجهة:\n";
            foreach ($painPoints as $p) {
                $msg .= "  • {$p}\n";
            }
            $msg .= "\nيومك بعد واجهة:\n";
            foreach ($outcomes as $o) {
                $msg .= "  ✓ {$o}\n";
            }
            $msg .= "\nجاهز نبدأ؟ قول \"نبدأ\"";

            return ['reply' => $msg, 'data' => $data];
        }

        // User confirmed
        // Skip name_city if already have both
        if (!empty($data['name']) && !empty($data['city'])) {
            return ['reply' => '', 'next_step' => 'services', 'data' => $data, 'auto_advance' => true];
        }

        return ['reply' => '', 'next_step' => 'name_city', 'data' => $data, 'auto_advance' => true];
    }

    // ═══ STEP: name_city ═══

    private function stepNameCity(string $input, array $data, ?Blueprint $bp): array
    {
        if ($input !== '') {
            $extracted = $this->extractor->extractNameCity($input);
            if (!empty($extracted['name'])) $data['name'] = $extracted['name'];
            if (!empty($extracted['city'])) $data['city'] = $extracted['city'];
        }

        $place = $bp ? $bp->term('service_place', 'النشاط') : 'النشاط';

        if (empty($data['name']) && empty($data['city'])) {
            return ['reply' => "وش اسم {$place} وبأي مدينة؟", 'data' => $data];
        }
        if (empty($data['name'])) {
            return ['reply' => "وش اسم {$place}؟", 'data' => $data];
        }
        if (empty($data['city'])) {
            return ['reply' => 'بأي مدينة؟', 'data' => $data];
        }

        return ['reply' => '', 'next_step' => 'services', 'data' => $data, 'auto_advance' => true];
    }

    // ═══ STEP: services (show defaults + prices, user confirms or edits) ═══

    private function stepServices(string $input, array $data, ?Blueprint $bp): array
    {
        // First time → show defaults
        if (!isset($data['services']) || $data['services'] === null) {
            $defaults = $bp->defaultServices;
            $data['services'] = $defaults;

            $list = collect($defaults)->map(fn($s) =>
                "  • {$s['name']}    {$s['price']} ريال" . (isset($s['duration']) ? "    ({$s['duration']} دقيقة)" : '')
            )->implode("\n");

            return [
                'reply' => "✓ {$data['name']} — {$data['city']}\n\n"
                    . "هذي الخدمات الشائعة لـ{$bp->term('service_place')}:\n\n{$list}\n\n"
                    . "عدّل الأسعار أو أضف/احذف — أو قول \"تمام\"",
                'data' => $data,
            ];
        }

        // User said "تمام"
        if ($this->isConfirmation($input)) {
            // Validate no zero prices
            $zeros = collect($data['services'])->where('price', 0)->count();
            if ($zeros > 0) {
                return ['reply' => 'فيه خدمات بسعر 0. عدّل أسعارها أو احذفها.', 'data' => $data];
            }

            $data['services_confirmed'] = true;
            $nextStep = $this->resolveNextStep('services', $data, $bp);
            return ['reply' => '', 'next_step' => $nextStep, 'data' => $data, 'auto_advance' => true];
        }

        // User editing → extract changes
        $changes = $this->extractor->extractServiceChanges($input, $data['services'], $bp->terminology);
        if (!empty($changes)) {
            $data['services'] = $this->applyChanges($data['services'], $changes, $bp);
        }

        $list = collect($data['services'])->map(fn($s) =>
            "  • {$s['name']}    {$s['price']} ريال"
        )->implode("\n");

        return [
            'reply' => "الخدمات المحدّثة:\n\n{$list}\n\nتمام؟ أو عدّل",
            'data' => $data,
        ];
    }

    // ═══ STEP: service_mode ═══

    private function stepServiceMode(string $input, array $data, ?Blueprint $bp): array
    {
        if ($input === '') {
            $place = $bp->term('service_place', 'المقر');
            return ['reply' => "خدماتكم بـ{$place} بس، ولا توصلون للعميل، ولا الاثنين؟", 'data' => $data];
        }

        $lower = mb_strtolower($input);
        if (str_contains($lower, 'الاثنين') || str_contains($lower, 'كلها')) {
            $data['service_mode'] = 'both';
        } elseif (str_contains($lower, 'توصيل') || str_contains($lower, 'عميل') || str_contains($lower, 'بيت')) {
            $data['service_mode'] = 'at_customer';
        } else {
            $data['service_mode'] = 'at_branch';
        }

        $nextStep = $this->resolveNextStep('service_mode', $data, $bp);
        return ['reply' => '', 'next_step' => $nextStep, 'data' => $data, 'auto_advance' => true];
    }

    // ═══ STEP: travel_fee ═══

    private function stepTravelFee(string $input, array $data, ?Blueprint $bp): array
    {
        if ($input === '') {
            return ['reply' => 'كم رسوم الوصول للعميل؟ (أو "مجاني")', 'data' => $data];
        }

        $lower = mb_strtolower($input);
        if (str_contains($lower, 'مجان')) {
            $data['travel_fee'] = 0;
        } else {
            preg_match('/(\d+)/', $input, $m);
            $data['travel_fee'] = isset($m[1]) ? (float) $m[1] : 0;
        }

        $nextStep = $this->resolveNextStep('travel_fee', $data, $bp);
        return ['reply' => '', 'next_step' => $nextStep, 'data' => $data, 'auto_advance' => true];
    }

    // ═══ STEP: specialists ═══

    private function stepSpecialists(string $input, array $data, ?Blueprint $bp): array
    {
        if ($input === '') {
            $term = $bp->term('specialist_plural', 'المختصين');
            return [
                'reply' => "مين {$term} وكل واحد وش يسوي؟\n"
                    . "مثال: نورة - قص وصبغة، سارة - مكياج\n"
                    . "أو قول \"بدون\" وتضيفهم بعدين.",
                'data' => $data,
            ];
        }

        $lower = mb_strtolower($input);
        if (str_contains($lower, 'بدون') || str_contains($lower, 'لاحق')) {
            $data['specialists'] = [];
        } else {
            $result = $this->extractor->extractSpecialists($input, $data['services']);
            $data['specialists'] = $result['specialists'] ?? [];
        }

        // Execute all and finish
        return $this->finish($data, $bp);
    }

    // ═══ STEP: agent_name ═══

    private function stepAgentName(string $input, array $data, ?Blueprint $bp): array
    {
        $defaultName = str_replace('{business_name}', $data['name'] ?? '', $bp->defaultAgentName);
        $gender = $bp->defaultAgentGender;
        $genderLabel = $gender === 'female' ? 'مساعدة' : 'مساعد';

        if ($input === '') {
            return [
                'reply' => "الوكيل اسمه \"{$defaultName}\" — تبي تغيّره؟\nأو قول \"تمام\"",
                'data' => $data,
            ];
        }

        if ($this->isConfirmation($input)) {
            $data['agent_name'] = $defaultName;
        } else {
            $data['agent_name'] = trim($input);
        }
        $data['agent_gender'] = $gender;

        return $this->finish($data, $bp);
    }

    // ═══ FINISH: execute all at once ═══

    private function finish(array $data, ?Blueprint $bp): array
    {
        $sessionToken = null; // will be set by caller
        $st = $data['session_token'] ?? null;

        // 1. Create business
        $biz = (new CreateBusiness())->execute([
            'name' => $data['name'],
            'sector_type_id' => $data['sector_type_id'],
            'agent_name' => $data['agent_name'] ?? null,
            'agent_gender' => $data['agent_gender'] ?? ($bp->defaultAgentGender ?? 'male'),
        ], 'ali', $st);

        // 2. Create branch
        $branch = (new CreateBranch())->execute([
            'business_id' => $biz->id,
            'city' => $data['city'],
        ], 'ali', $st);

        // 3. Create services
        $serviceMode = $data['service_mode'] ?? $bp->defaultServiceMode;
        foreach ($data['services'] as $s) {
            (new CreateService())->execute([
                'business_id' => $biz->id,
                'name' => $s['name'],
                'category' => $s['category'] ?? null,
                'price' => $s['price'],
                'duration_minutes' => $s['duration'] ?? null,
                'type' => 'service',
                'service_mode' => $serviceMode,
                'travel_fee' => $data['travel_fee'] ?? null,
            ], 'ali', $st);
        }

        // 4. Create specialists
        $createdServices = $biz->serviceItems()->pluck('id', 'name')->toArray();
        $st = SectorType::find($data['sector_type_id']);

        // Solo mode → auto-create single specialist with business name
        if (($st->work_model ?? 'team') === 'solo' && empty($data['specialists'])) {
            Specialist::create([
                'branch_id' => $branch->id,
                'name' => $data['name'],
                'role' => $st->label ?? null,
                'service_ids' => array_values($createdServices),
                'status' => 'active',
            ]);
        }

        foreach ($data['specialists'] ?? [] as $sp) {
            if (is_string($sp)) $sp = ['name' => $sp, 'role' => null, 'services' => []];
            $serviceIds = [];
            foreach ($sp['services'] ?? [] as $sName) {
                if (isset($createdServices[$sName])) $serviceIds[] = $createdServices[$sName];
            }
            // If "all" or empty, assign all
            if (empty($serviceIds)) $serviceIds = array_values($createdServices);

            Specialist::create([
                'branch_id' => $branch->id,
                'name' => $sp['name'],
                'role' => $sp['role'] ?? null,
                'service_ids' => $serviceIds,
                'status' => 'active',
            ]);
        }

        // 5. Rebuild prompt
        PromptBuilder::rebuildForBusiness($biz->id);

        // Build summary
        $data['business_id'] = $biz->id;
        $data['step'] = 'DONE';

        $summary = "✓ نشاطك جاهز!\n\n";
        $summary .= "النشاط: {$biz->name}\n";
        $summary .= "القطاع: " . ($bp->terms['service_place'] ?? SectorType::find($data['sector_type_id'])?->label) . "\n";
        $summary .= "المدينة: {$data['city']}\n\n";
        $summary .= "الخدمات:\n";
        foreach ($data['services'] as $s) {
            $summary .= "  • {$s['name']} — {$s['price']} ريال\n";
        }
        if (!empty($data['specialists'])) {
            $term = $bp->term('specialist_plural', 'المختصين');
            $summary .= "\n{$term}:\n";
            foreach ($data['specialists'] as $sp) {
                $name = is_string($sp) ? $sp : $sp['name'];
                $role = is_string($sp) ? '' : ($sp['role'] ?? '');
                $summary .= "  • {$name}" . ($role ? " ({$role})" : '') . "\n";
            }
        }
        $summary .= "\nسجّل عشان أحفظ لك كل شي. أو قول وش تبي تعدّل.";

        return ['reply' => $summary, 'next_step' => 'DONE', 'data' => $data];
    }

    // ═══ Match handlers ═══

    private function handleNoMatch(array $data, string $input, ?User $user): array
    {
        $data['pending_type'] = $input;
        $data['pending_match'] = 'none';
        $data['pending_blueprint'] = null;

        return [
            'reply' => "حالياً نجهّز تجربة مخصصة لنشاطك.\n"
                . "أسجّل بياناتك وفريقنا يبلّغك أول ما تكون جاهزة.\n\n"
                . "وش اسم النشاط؟ وبأي مدينة؟",
            'next_step' => 'COLLECT_PENDING_INFO',
            'data' => $data,
        ];
    }

    private function handlePartialMatch(array $data, string $input, SectorType $st, ?User $user): array
    {
        $data['pending_type'] = $input;
        $data['pending_match'] = 'partial';
        $data['pending_blueprint'] = $st->blueprint;

        return [
            'reply' => "حالياً نجهّز تجربة مخصصة لنشاطك.\n"
                . "أسجّل بياناتك وفريقنا يبلّغك أول ما تكون جاهزة.\n\n"
                . "وش اسم النشاط؟ وبأي مدينة؟",
            'next_step' => 'COLLECT_PENDING_INFO',
            'data' => $data,
        ];
    }

    // ═══ STEP: COLLECT_PENDING_INFO ═══

    private function stepCollectPendingInfo(string $input, array $data, ?User $user): array
    {
        if ($input === '') {
            return ['reply' => 'وش اسم النشاط؟ وبأي مدينة؟', 'data' => $data];
        }

        // Extract name + city
        $extracted = $this->extractor->extractNameCity($input);
        if (!empty($extracted['name'])) $data['name'] = $extracted['name'];
        if (!empty($extracted['city'])) $data['city'] = $extracted['city'];

        // Still missing?
        if (empty($data['name'])) {
            return ['reply' => 'وش اسم النشاط؟', 'data' => $data];
        }
        if (empty($data['city'])) {
            return ['reply' => 'بأي مدينة؟', 'data' => $data];
        }

        // Save setup_request
        $req = SetupRequest::create([
            'session_token' => $data['session_token'] ?? null,
            'user_id' => $user?->id,
            'requested_type' => $data['pending_type'] ?? 'غير محدد',
            'matched_blueprint' => $data['pending_blueprint'] ?? null,
            'match_level' => $data['pending_match'] ?? 'none',
            'collected_data' => [
                'name' => $data['name'],
                'city' => $data['city'],
                'original_input' => $data['pending_type'] ?? null,
            ],
            'status' => 'pending',
        ]);

        // Notify super admin via Telegram
        $this->notifySuperAdmin($req);

        $data['setup_request_id'] = $req->id;

        return [
            'reply' => "✓ تم تسجيل طلبك.\n\n"
                . "النشاط: {$data['name']}\n"
                . "المدينة: {$data['city']}\n\n"
                . "فريقنا يتواصل معك قريباً.",
            'next_step' => 'PENDING',
            'data' => $data,
        ];
    }

    private function notifySuperAdmin(SetupRequest $req): void
    {
        try {
            $admins = User::where('role', 'super_admin')->get();
            $notifier = new \App\Services\Notifications\NotificationService();

            foreach ($admins as $admin) {
                $notifier->notify($admin, 'suggestion', [
                    'suggestion_text' => "طلب إعداد جديد: {$req->requested_type}\n"
                        . "الاسم: " . ($req->collected_data['name'] ?? '-') . "\n"
                        . "المدينة: " . ($req->collected_data['city'] ?? '-') . "\n"
                        . "المطابقة: {$req->match_level}",
                ]);
            }
        } catch (\Throwable $e) {
            // Don't fail onboarding if notification fails
        }
    }

    // ═══ Helpers ═══

    private function resolveNextStep(string $current, array $data, ?Blueprint $bp): string
    {
        $allSteps = $bp ? $bp->steps : array_keys($this->stepConfig);
        $idx = array_search($current, $allSteps);

        for ($i = $idx + 1; $i < count($allSteps); $i++) {
            $next = $allSteps[$i];
            if ($this->shouldSkip($next, $data, $bp)) continue;
            return $next;
        }

        // No more steps → finish via specialists or agent_name
        return 'DONE';
    }

    private function shouldSkip(string $step, array $data, ?Blueprint $bp): bool
    {
        $st = !empty($data['sector_type_id']) ? SectorType::find($data['sector_type_id']) : null;
        $workModel = $st->work_model ?? 'team';

        return match ($step) {
            'preview' => isset($data['preview_shown']),
            'name_city' => !empty($data['name']) && !empty($data['city']),
            'prices' => true, // prices are included with services in the new design
            'service_mode' => !$bp->showServiceModeStep,
            'travel_fee' => !in_array($data['service_mode'] ?? '', ['at_customer', 'both']),
            'specialists' => !$bp->hasSpecialists || $workModel === 'solo',
            'agent_name' => true, // skip for now, use default
            default => false,
        };
    }

    private function isConfirmation(string $input): bool
    {
        $confirms = ['تمام', 'نعم', 'اي', 'أي', 'ايوه', 'صح', 'موافق', 'ok', 'اوك', 'ماشي', 'يلا', 'نبدأ', 'ابدأ'];
        return in_array(mb_strtolower(trim($input)), $confirms);
    }

    private function applyChanges(array $services, array $changes, Blueprint $bp): array
    {
        if (!is_array($changes)) return $services;

        foreach ($changes as $change) {
            if (!is_array($change)) continue;
            $action = $change['action'] ?? null;

            match ($action) {
                'add' => $services[] = [
                    'name' => $bp->resolveAlias($change['name'] ?? ''),
                    'price' => $change['price'] ?? 0,
                    'category' => $change['category'] ?? $bp->findCategory($change['name'] ?? '') ?? null,
                ],
                'update' => array_walk($services, function (&$s) use ($change) {
                    if (($s['name'] ?? '') === ($change['name'] ?? '')) {
                        if (isset($change['price'])) $s['price'] = $change['price'];
                    }
                }),
                'remove' => $services = array_values(array_filter($services, fn($s) => ($s['name'] ?? '') !== ($change['name'] ?? ''))),
                default => null,
            };
        }

        return $services;
    }
}
