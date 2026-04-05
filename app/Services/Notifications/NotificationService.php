<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;

class NotificationService
{
    private array $templates = [
        'new_booking' => [
            'title' => '📅 حجز جديد',
            'body' => '{customer_name} حجز {service_name} يوم {date} الساعة {time}',
        ],
        'new_order' => [
            'title' => '🛒 طلب جديد',
            'body' => '{customer_name} طلب {item_count} منتجات بقيمة {total} ريال',
        ],
        'booking_cancelled' => [
            'title' => '❌ إلغاء حجز',
            'body' => '{customer_name} ألغى حجز {service_name}',
        ],
        'escalation' => [
            'title' => '⚠️ تصعيد',
            'body' => 'الوكيل حوّل محادثة مع {customer_name}: {reason}',
        ],
        'error_caught' => [
            'title' => '🔴 خطأ تم منعه',
            'body' => '{error_type}: {detail}',
        ],
        'service_created' => [
            'title' => '➕ خدمة جديدة',
            'body' => 'تم إضافة "{service_name}" بسعر {price} ريال',
        ],
        'service_updated' => [
            'title' => '✏️ تعديل خدمة',
            'body' => 'تم تعديل "{service_name}"',
        ],
        'service_toggled' => [
            'title' => '🔄 تغيير حالة خدمة',
            'body' => '"{service_name}" الحين {status}',
        ],
        'hours_updated' => [
            'title' => '🕐 تعديل أوقات',
            'body' => 'أوقات العمل تغيّرت',
        ],
        'policy_updated' => [
            'title' => '📋 تعديل سياسة',
            'body' => 'سياسة {key} تغيّرت إلى: {value}',
        ],
        'daily_report' => [
            'title' => '📊 التقرير اليومي',
            'body' => '{report}',
        ],
        'suggestion' => [
            'title' => '💡 اقتراح من علي',
            'body' => '{suggestion_text}',
        ],
    ];

    /**
     * Send notification to business owner.
     */
    public function notify(User $user, string $eventType, array $data = []): array
    {
        $results = [];

        // Check user preferences
        $prefs = NotificationPreference::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->first();

        // Default: send to both channels
        $sendDashboard = $prefs?->dashboard ?? true;
        $sendTelegram = $prefs?->telegram ?? true;

        $message = $this->buildMessage($eventType, $data);

        if ($sendDashboard) {
            $results['dashboard'] = (new DashboardChannel())->send($user, $message);
        }

        if ($sendTelegram && $user->telegram_chat_id) {
            $results['telegram'] = (new TelegramChannel())->send($user, $message);
        }

        return $results;
    }

    private function buildMessage(string $eventType, array $data): array
    {
        $template = $this->templates[$eventType] ?? [
            'title' => $eventType,
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ];

        $title = $template['title'];
        $body = $template['body'];

        // Replace placeholders
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $body = str_replace('{' . $key . '}', (string) $value, $body);
            }
        }

        return [
            'title' => $title,
            'body' => $body,
            'event_type' => $eventType,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
