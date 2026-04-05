<?php

namespace App\Services\Snad;

use App\Models\AgentPrompt;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use OpenAI\Laravel\Facades\OpenAI;

class SnadAgent
{
    private Gateway $gateway;
    private Validator $validator;
    private Branch $branch;
    private ?AgentPrompt $prompt;

    public function __construct(Branch $branch)
    {
        $this->branch = $branch;
        $this->prompt = AgentPrompt::where('branch_id', $branch->id)
            ->orderByDesc('version')
            ->first();

        $routes = $this->prompt?->gateway_routes ?? [];
        $rules = $this->prompt?->validator_rules ?? [];

        $this->gateway = new Gateway($branch, $routes);
        $this->validator = new Validator($branch, $rules);
    }

    /**
     * Process an incoming customer message.
     */
    public function handleMessage(Conversation $conversation, string $customerMessage): array
    {
        // Save customer message
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'customer',
            'content' => $customerMessage,
        ]);

        // Layer 1: Gateway — try without LLM
        $gatewayResponse = $this->gateway->tryHandle($customerMessage);

        if ($gatewayResponse !== null) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'agent',
                'content' => $gatewayResponse,
                'intent' => 'gateway_handled',
                'confidence' => 'high',
                'tokens_used' => 0,
                'response_ms' => 0,
            ]);

            return [
                'response' => $gatewayResponse,
                'source' => 'gateway',
                'tokens' => 0,
            ];
        }

        // Layer 2: LLM — send to OpenAI
        $context = $this->gateway->enrichContext($conversation->customer_id);
        $startTime = microtime(true);

        $agentOutput = $this->callLLM($conversation, $customerMessage, $context);

        $responseMs = (int) ((microtime(true) - $startTime) * 1000);

        // Layer 3: Validator — check before sending
        $errors = $this->validator->validate($agentOutput, $context);
        $decision = $this->validator->decide($agentOutput, $errors);

        $wasBlocked = $decision['action'] === 'block';
        $finalResponse = $wasBlocked ? $decision['fallback'] : $decision['response'];

        // Save agent message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'agent',
            'content' => $finalResponse,
            'raw_output' => $agentOutput,
            'intent' => $agentOutput['intent'] ?? null,
            'action_taken' => $agentOutput['action'] ?? null,
            'confidence' => $agentOutput['confidence'] ?? null,
            'validation_result' => ['passed' => !$wasBlocked, 'errors' => $errors],
            'was_blocked' => $wasBlocked,
            'block_reason' => $wasBlocked ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null,
            'tokens_used' => $agentOutput['tokens_used'] ?? null,
            'response_ms' => $responseMs,
        ]);

        // Create alert if blocked
        if ($wasBlocked) {
            Alert::create([
                'business_id' => $this->branch->business_id,
                'branch_id' => $this->branch->id,
                'type' => 'error_caught',
                'severity' => 'critical',
                'title' => 'رد تم حجبه',
                'message' => collect($errors)->where('severity', 'critical')->pluck('detail')->join(' | '),
                'related_conversation_id' => $conversation->id,
            ]);
        }

        return [
            'response' => $finalResponse,
            'source' => $wasBlocked ? 'fallback' : 'llm',
            'tokens' => $agentOutput['tokens_used'] ?? 0,
            'blocked' => $wasBlocked,
            'errors' => $errors,
        ];
    }

    private function callLLM(Conversation $conversation, string $customerMessage, array $context): array
    {
        if (!$this->prompt) {
            return [
                'intent' => 'error',
                'action' => 'none',
                'response_text' => 'عذراً، النظام غير جاهز حالياً.',
                'confidence' => 'low',
                'confidence_reason' => 'لا يوجد برومبت مُعدّ',
                'tokens_used' => 0,
            ];
        }

        // Build conversation history
        $history = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => [
                'role' => $m->role === 'customer' ? 'user' : 'assistant',
                'content' => $m->content,
            ])
            ->toArray();

        // System prompt = agent prompt + live context
        $systemPrompt = $this->prompt->prompt_text . "\n\n<live_context>\n"
            . json_encode($context, JSON_UNESCAPED_UNICODE)
            . "\n</live_context>\n\n"
            . "<output_format>\n"
            . "رد بـ JSON فقط بهذا الهيكل:\n"
            . "{\n"
            . "  \"intent\": \"نية العميل\",\n"
            . "  \"action\": \"respond | book | escalate | clarify\",\n"
            . "  \"response_text\": \"نص الرد للعميل\",\n"
            . "  \"confidence\": \"high | medium | low\",\n"
            . "  \"confidence_reason\": \"سبب مستوى الثقة\"\n"
            . "}\n"
            . "</output_format>";

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            [['role' => 'user', 'content' => $customerMessage]]
        );

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 500,
        ]);

        $content = $response->choices[0]->message->content ?? '';
        $tokensUsed = $response->usage->totalTokens ?? 0;

        // Parse JSON response
        $parsed = $this->parseAgentResponse($content);
        $parsed['tokens_used'] = $tokensUsed;

        return $parsed;
    }

    private function parseAgentResponse(string $content): array
    {
        // Try to extract JSON from the response
        $content = trim($content);

        // Remove markdown code blocks if present
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\n?/', '', $content);
            $content = preg_replace('/\n?```$/', '', $content);
        }

        $decoded = json_decode($content, true);

        if ($decoded && isset($decoded['response_text'])) {
            return $decoded;
        }

        // Fallback: treat entire response as text
        return [
            'intent' => 'unknown',
            'action' => 'respond',
            'response_text' => $content,
            'confidence' => 'medium',
            'confidence_reason' => 'لم يتبع هيكل الرد المطلوب',
        ];
    }
}
