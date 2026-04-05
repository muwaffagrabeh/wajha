<?php

namespace App\Services\Monitoring;

use App\Models\Alert;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;

class RiskPatterns
{
    public function __construct(private string $businessId, private array $branchIds = []) {}

    /**
     * Check all drift signals for the last N responses.
     */
    public function checkDrift(int $hours = 1): array
    {
        $since = Carbon::now()->subHours($hours);
        $conversationIds = Conversation::whereIn('branch_id', $this->branchIds)
            ->where('started_at', '>=', $since)
            ->pluck('id');

        $messages = Message::whereIn('conversation_id', $conversationIds)
            ->where('role', 'agent')
            ->get();

        if ($messages->isEmpty()) {
            return ['status' => 'no_data', 'signals' => []];
        }

        $signals = [];

        // 1. Verbose — responses getting too long
        $avgLength = $messages->avg(fn($m) => mb_strlen($m->content));
        if ($avgLength > 200) {
            $signals['verbose'] = [
                'triggered' => true,
                'value' => round($avgLength),
                'threshold' => 200,
                'message' => 'متوسط طول الردود مرتفع (' . round($avgLength) . ' حرف)',
            ];
        }

        // 2. Escalation spike
        $totalConvs = Conversation::whereIn('branch_id', $this->branchIds)
            ->where('started_at', '>=', $since)->count();
        $escalated = Conversation::whereIn('branch_id', $this->branchIds)
            ->where('started_at', '>=', $since)
            ->where('status', 'escalated')->count();
        $escalationRate = $totalConvs > 0 ? $escalated / $totalConvs : 0;

        if ($escalationRate > 0.3) {
            $signals['escalation_spike'] = [
                'triggered' => true,
                'value' => round($escalationRate * 100) . '%',
                'threshold' => '30%',
                'message' => 'نسبة التصعيد مرتفعة (' . round($escalationRate * 100) . '%)',
            ];
        }

        // 3. Confidence drop
        $lowConfidence = $messages->where('confidence', 'low')->count();
        $lowRate = $messages->count() > 0 ? $lowConfidence / $messages->count() : 0;

        if ($lowRate > 0.2) {
            $signals['confidence_drop'] = [
                'triggered' => true,
                'value' => round($lowRate * 100) . '%',
                'threshold' => '20%',
                'message' => 'نسبة الثقة المنخفضة مرتفعة (' . round($lowRate * 100) . '%)',
            ];
        }

        // 4. Blocked responses (validator catching errors)
        $blocked = $messages->where('was_blocked', true)->count();
        $blockedRate = $messages->count() > 0 ? $blocked / $messages->count() : 0;

        if ($blockedRate > 0.1) {
            $signals['high_block_rate'] = [
                'triggered' => true,
                'value' => $blocked,
                'threshold' => '10%',
                'message' => "تم حجب {$blocked} رد — الوكيل يحتاج مراجعة",
            ];
        }

        // 5. Customer corrections (customer sends same question again)
        $repeatCount = $this->detectRepeatQuestions($conversationIds);
        if ($repeatCount > 3) {
            $signals['repeat_questions'] = [
                'triggered' => true,
                'value' => $repeatCount,
                'threshold' => 3,
                'message' => "العملاء يكررون أسئلتهم {$repeatCount} مرات — الردود قد تكون غير واضحة",
            ];
        }

        return [
            'status' => empty($signals) ? 'healthy' : 'drift_detected',
            'signals' => $signals,
            'period' => "{$hours} ساعة",
            'total_messages' => $messages->count(),
        ];
    }

    private function detectRepeatQuestions($conversationIds): int
    {
        $count = 0;

        foreach ($conversationIds as $convId) {
            $customerMessages = Message::where('conversation_id', $convId)
                ->where('role', 'customer')
                ->pluck('content')
                ->toArray();

            // Simple: check if similar messages appear twice
            $seen = [];
            foreach ($customerMessages as $msg) {
                $normalized = mb_substr(trim($msg), 0, 50);
                if (in_array($normalized, $seen)) {
                    $count++;
                }
                $seen[] = $normalized;
            }
        }

        return $count;
    }
}
