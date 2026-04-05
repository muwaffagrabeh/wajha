<?php

namespace App\Actions\Reports;

use App\Models\ActionLog;
use App\Models\Alert;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;

class GetDailyStats
{
    public function execute(string $businessId, ?string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $startOfDay = $date->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $branchIds = \App\Models\Branch::where('business_id', $businessId)->pluck('id');

        $conversations = Conversation::whereIn('branch_id', $branchIds)
            ->whereBetween('started_at', [$startOfDay, $endOfDay]);

        $totalConversations = $conversations->count();
        $resolved = (clone $conversations)->where('status', 'resolved')->count();
        $escalated = (clone $conversations)->where('status', 'escalated')->count();

        $messages = Message::whereIn('conversation_id',
            Conversation::whereIn('branch_id', $branchIds)
                ->whereBetween('started_at', [$startOfDay, $endOfDay])
                ->pluck('id')
        );

        $errorsCaught = (clone $messages)->where('was_blocked', true)->count();

        $alerts = Alert::where('business_id', $businessId)
            ->whereBetween('created_at', [$startOfDay, $endOfDay]);

        return [
            'date' => $date->toDateString(),
            'total_conversations' => $totalConversations,
            'auto_resolved' => $resolved,
            'auto_resolved_rate' => $totalConversations > 0
                ? round(($resolved / $totalConversations) * 100) . '%'
                : '0%',
            'escalated' => $escalated,
            'errors_caught' => $errorsCaught,
            'alerts' => [
                'critical' => (clone $alerts)->where('severity', 'critical')->count(),
                'high' => (clone $alerts)->where('severity', 'high')->count(),
                'medium' => (clone $alerts)->where('severity', 'medium')->count(),
                'low' => (clone $alerts)->where('severity', 'low')->count(),
            ],
            'total_messages' => $messages->count(),
        ];
    }
}
