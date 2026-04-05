<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannel
{
    /**
     * Send notification via Telegram Bot API.
     */
    public function send(User $user, array $message): array
    {
        $chatId = $user->telegram_chat_id;
        $botToken = config('services.telegram.bot_token');

        if (!$chatId || !$botToken) {
            Log::channel('single')->info('Telegram notification (no bot configured)', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'title' => $message['title'],
                'body' => $message['body'],
            ]);

            return [
                'channel' => 'telegram',
                'status' => $botToken ? 'no_chat_id' : 'no_bot_token',
                'message' => $message['title'],
            ];
        }

        $text = "{$message['title']}\n\n{$message['body']}";

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            return [
                'channel' => 'telegram',
                'status' => $response->successful() ? 'sent' : 'failed',
                'message' => $message['title'],
            ];
        } catch (\Throwable $e) {
            Log::error('Telegram send failed', ['error' => $e->getMessage()]);

            return [
                'channel' => 'telegram',
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
}
