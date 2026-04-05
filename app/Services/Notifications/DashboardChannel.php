<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class DashboardChannel
{
    /**
     * Send notification to dashboard (via broadcast/websocket in production).
     * For now, logs the notification.
     */
    public function send(User $user, array $message): array
    {
        // In production: broadcast via Laravel Echo / Pusher / Reverb
        // event(new DashboardNotification($user->id, $message));

        Log::channel('single')->info('Dashboard notification', [
            'user_id' => $user->id,
            'title' => $message['title'],
            'body' => $message['body'],
        ]);

        return [
            'channel' => 'dashboard',
            'status' => 'sent',
            'message' => $message['title'],
        ];
    }
}
