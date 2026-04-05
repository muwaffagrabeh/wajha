<?php

namespace App\Services\Monitoring;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;

class DailyHealthCheck
{
    public function __construct(private string $businessId) {}

    public function run(?string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $branchIds = Branch::where('business_id', $this->businessId)->pluck('id');

        $conversations = Conversation::whereIn('branch_id', $branchIds)
            ->whereBetween('started_at', [$start, $end]);

        $totalConvs = $conversations->count();
        $resolved = (clone $conversations)->where('status', 'resolved')->count();
        $escalated = (clone $conversations)->where('status', 'escalated')->count();
        $abandoned = (clone $conversations)->where('status', 'abandoned')->count();
        $active = (clone $conversations)->where('status', 'active')->count();

        $convIds = Conversation::whereIn('branch_id', $branchIds)
            ->whereBetween('started_at', [$start, $end])->pluck('id');

        $messages = Message::whereIn('conversation_id', $convIds);
        $totalMessages = $messages->count();

        $agentMessages = (clone $messages)->where('role', 'agent');
        $totalAgentMsgs = $agentMessages->count();

        $errorsCaught = (clone $agentMessages)->where('was_blocked', true)->count();

        $avgTokens = (clone $agentMessages)->whereNotNull('tokens_used')
            ->where('tokens_used', '>', 0)
            ->avg('tokens_used') ?? 0;

        $avgResponseMs = (clone $agentMessages)->whereNotNull('response_ms')
            ->where('response_ms', '>', 0)
            ->avg('response_ms') ?? 0;

        $gatewayHandled = (clone $agentMessages)->where('tokens_used', 0)->count();

        // Confidence distribution
        $highConf = (clone $agentMessages)->where('confidence', 'high')->count();
        $medConf = (clone $agentMessages)->where('confidence', 'medium')->count();
        $lowConf = (clone $agentMessages)->where('confidence', 'low')->count();

        // Alerts
        $alerts = Alert::where('business_id', $this->businessId)
            ->whereBetween('created_at', [$start, $end]);
        $alertsCritical = (clone $alerts)->where('severity', 'critical')->count();
        $alertsHigh = (clone $alerts)->where('severity', 'high')->count();

        // Top intents
        $topIntents = (clone $agentMessages)->whereNotNull('intent')
            ->selectRaw('intent, COUNT(*) as count')
            ->groupBy('intent')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'intent')
            ->toArray();

        // Risk score (0-100)
        $riskScore = $this->calculateRiskScore($totalConvs, $escalated, $errorsCaught, $lowConf, $alertsCritical);

        return [
            'date' => $date->toDateString(),
            'conversations' => [
                'total' => $totalConvs,
                'resolved' => $resolved,
                'escalated' => $escalated,
                'abandoned' => $abandoned,
                'active' => $active,
                'auto_resolve_rate' => $totalConvs > 0 ? round(($resolved / $totalConvs) * 100) . '%' : '0%',
            ],
            'messages' => [
                'total' => $totalMessages,
                'agent' => $totalAgentMsgs,
                'gateway_handled' => $gatewayHandled,
                'gateway_rate' => $totalAgentMsgs > 0 ? round(($gatewayHandled / $totalAgentMsgs) * 100) . '%' : '0%',
            ],
            'quality' => [
                'errors_caught' => $errorsCaught,
                'avg_tokens' => round($avgTokens),
                'avg_response_ms' => round($avgResponseMs),
                'confidence' => [
                    'high' => $highConf,
                    'medium' => $medConf,
                    'low' => $lowConf,
                ],
            ],
            'alerts' => [
                'critical' => $alertsCritical,
                'high' => $alertsHigh,
            ],
            'top_intents' => $topIntents,
            'risk_score' => $riskScore,
        ];
    }

    private function calculateRiskScore(int $convs, int $escalated, int $errors, int $lowConf, int $critical): int
    {
        if ($convs === 0) return 0;

        $score = 0;
        $score += min(30, ($escalated / max($convs, 1)) * 100);
        $score += min(25, $errors * 5);
        $score += min(20, $lowConf * 3);
        $score += min(25, $critical * 10);

        return min(100, (int) $score);
    }

    public function formatReport(array $data): string
    {
        $riskEmoji = match (true) {
            $data['risk_score'] >= 70 => '🔴',
            $data['risk_score'] >= 40 => '🟡',
            default => '🟢',
        };

        $report = "📊 التقرير اليومي — {$data['date']}\n";
        $report .= "━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $report .= "المحادثات: {$data['conversations']['total']}\n";
        $report .= "  حُلّت: {$data['conversations']['resolved']} ({$data['conversations']['auto_resolve_rate']})\n";
        $report .= "  تصعيد: {$data['conversations']['escalated']}\n";
        $report .= "  مهجورة: {$data['conversations']['abandoned']}\n\n";

        $report .= "الرسائل: {$data['messages']['total']}\n";
        $report .= "  Gateway (بدون LLM): {$data['messages']['gateway_handled']} ({$data['messages']['gateway_rate']})\n";
        $report .= "  متوسط التوكنز: {$data['quality']['avg_tokens']}\n";
        $report .= "  متوسط الاستجابة: {$data['quality']['avg_response_ms']}ms\n\n";

        $report .= "الجودة:\n";
        $report .= "  أخطاء مُنعت: {$data['quality']['errors_caught']}\n";
        $report .= "  ثقة عالية: {$data['quality']['confidence']['high']} | متوسطة: {$data['quality']['confidence']['medium']} | منخفضة: {$data['quality']['confidence']['low']}\n\n";

        if (!empty($data['top_intents'])) {
            $report .= "أكثر النيات:\n";
            foreach ($data['top_intents'] as $intent => $count) {
                $report .= "  • {$intent}: {$count}\n";
            }
            $report .= "\n";
        }

        $report .= "{$riskEmoji} مستوى الخطر: {$data['risk_score']}/100\n";

        if ($data['alerts']['critical'] > 0) {
            $report .= "⚠️ تنبيهات حرجة: {$data['alerts']['critical']}\n";
        }

        return $report;
    }
}
