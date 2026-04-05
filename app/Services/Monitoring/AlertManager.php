<?php

namespace App\Services\Monitoring;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;

class AlertManager
{
    public function __construct(private string $businessId) {}

    /**
     * Run all alert rules and create alerts if triggered.
     */
    public function checkAll(): array
    {
        $triggered = [];

        foreach ($this->rules() as $rule) {
            $result = $this->evaluate($rule);
            if ($result['triggered']) {
                $alert = $this->createAlert($rule, $result);
                $triggered[] = $alert;
            }
        }

        return $triggered;
    }

    private function rules(): array
    {
        return [
            [
                'name' => 'high_error_rate',
                'condition' => fn() => $this->countBlockedInLastHours(1) > 10,
                'value_fn' => fn() => $this->countBlockedInLastHours(1),
                'type' => 'error_caught',
                'severity' => 'critical',
                'title' => 'أخطاء متكررة — إيقاف مؤقت مقترح',
                'message_fn' => fn($v) => "تم حجب {$v} رد في الساعة الأخيرة. الوكيل يحتاج مراجعة.",
            ],
            [
                'name' => 'escalation_spike',
                'condition' => fn() => $this->escalationRateLastHours(2) > 0.4,
                'value_fn' => fn() => round($this->escalationRateLastHours(2) * 100),
                'type' => 'escalation',
                'severity' => 'high',
                'title' => 'نسبة تصعيد مرتفعة',
                'message_fn' => fn($v) => "نسبة التصعيد {$v}% في آخر ساعتين.",
            ],
            [
                'name' => 'low_confidence_streak',
                'condition' => fn() => $this->lowConfidenceCountLastHours(4) > 5,
                'value_fn' => fn() => $this->lowConfidenceCountLastHours(4),
                'type' => 'low_confidence',
                'severity' => 'medium',
                'title' => 'ثقة منخفضة متكررة',
                'message_fn' => fn($v) => "{$v} ردود بثقة منخفضة في آخر 4 ساعات — غالباً يحتاج تحديث بيانات.",
            ],
            [
                'name' => 'drift_detected',
                'condition' => fn() => $this->avgResponseLengthLastHours(2) > 300,
                'value_fn' => fn() => round($this->avgResponseLengthLastHours(2)),
                'type' => 'drift_detected',
                'severity' => 'medium',
                'title' => 'انحراف في سلوك الوكيل',
                'message_fn' => fn($v) => "متوسط طول الردود {$v} حرف — أطول من المعتاد.",
            ],
        ];
    }

    private function evaluate(array $rule): array
    {
        $triggered = ($rule['condition'])();
        $value = $triggered ? ($rule['value_fn'])() : null;

        return [
            'triggered' => $triggered,
            'value' => $value,
        ];
    }

    private function createAlert(array $rule, array $result): Alert
    {
        // Don't duplicate — check if same alert exists in last hour
        $existing = Alert::where('business_id', $this->businessId)
            ->where('type', $rule['type'])
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->first();

        if ($existing) {
            return $existing;
        }

        return Alert::create([
            'business_id' => $this->businessId,
            'type' => $rule['type'],
            'severity' => $rule['severity'],
            'title' => $rule['title'],
            'message' => ($rule['message_fn'])($result['value']),
        ]);
    }

    // === Helper queries ===

    private function branchIds(): array
    {
        return Branch::where('business_id', $this->businessId)->pluck('id')->toArray();
    }

    private function countBlockedInLastHours(int $hours): int
    {
        $convIds = Conversation::whereIn('branch_id', $this->branchIds())
            ->where('started_at', '>=', Carbon::now()->subHours($hours))
            ->pluck('id');

        return Message::whereIn('conversation_id', $convIds)
            ->where('was_blocked', true)
            ->count();
    }

    private function escalationRateLastHours(int $hours): float
    {
        $since = Carbon::now()->subHours($hours);
        $total = Conversation::whereIn('branch_id', $this->branchIds())
            ->where('started_at', '>=', $since)->count();

        if ($total === 0) return 0;

        $escalated = Conversation::whereIn('branch_id', $this->branchIds())
            ->where('started_at', '>=', $since)
            ->where('status', 'escalated')->count();

        return $escalated / $total;
    }

    private function lowConfidenceCountLastHours(int $hours): int
    {
        $convIds = Conversation::whereIn('branch_id', $this->branchIds())
            ->where('started_at', '>=', Carbon::now()->subHours($hours))
            ->pluck('id');

        return Message::whereIn('conversation_id', $convIds)
            ->where('confidence', 'low')
            ->count();
    }

    private function avgResponseLengthLastHours(int $hours): float
    {
        $convIds = Conversation::whereIn('branch_id', $this->branchIds())
            ->where('started_at', '>=', Carbon::now()->subHours($hours))
            ->pluck('id');

        $messages = Message::whereIn('conversation_id', $convIds)
            ->where('role', 'agent')
            ->pluck('content');

        if ($messages->isEmpty()) return 0;

        return $messages->avg(fn($c) => mb_strlen($c));
    }
}
