<?php

namespace App\Actions;

class ActionRegistry
{
    public static function all(): array
    {
        return [
            // === Setup ===
            [
                'name' => 'create_business',
                'description' => 'إنشاء نشاط تجاري جديد',
                'parameters' => [
                    'name' => ['type' => 'string', 'required' => true],
                    'sector_type_id' => ['type' => 'string', 'required' => true],
                ],
                'who_can_call' => ['ali'],
                'triggers_prompt_rebuild' => false,
                'notification' => null,
            ],
            [
                'name' => 'create_branch',
                'description' => 'إنشاء فرع للنشاط التجاري',
                'parameters' => [
                    'business_id' => ['type' => 'string', 'required' => true],
                    'city' => ['type' => 'string', 'required' => true],
                    'district' => ['type' => 'string', 'required' => false],
                    'name' => ['type' => 'string', 'required' => false],
                    'working_hours' => ['type' => 'object', 'required' => false],
                ],
                'who_can_call' => ['ali'],
                'triggers_prompt_rebuild' => true,
                'notification' => null,
            ],

            // === Services ===
            [
                'name' => 'create_service',
                'description' => 'إضافة خدمة أو منتج جديد',
                'parameters' => [
                    'business_id' => ['type' => 'string', 'required' => true],
                    'name' => ['type' => 'string', 'required' => true],
                    'type' => ['type' => 'string', 'required' => true, 'enum' => ['service', 'product']],
                    'price' => ['type' => 'number', 'required' => true],
                    'price_model' => ['type' => 'string', 'default' => 'fixed'],
                    'category' => ['type' => 'string', 'required' => false],
                    'duration_minutes' => ['type' => 'number', 'required' => false],
                    'requires_booking' => ['type' => 'boolean', 'default' => false],
                    'requires_specialist' => ['type' => 'boolean', 'default' => false],
                ],
                'who_can_call' => ['dashboard', 'ali'],
                'triggers_prompt_rebuild' => true,
                'notification' => 'service_created',
            ],
            [
                'name' => 'update_service',
                'description' => 'تعديل خدمة أو منتج',
                'parameters' => [
                    'service_id' => ['type' => 'string', 'required' => true],
                    'name' => ['type' => 'string', 'required' => false],
                    'price' => ['type' => 'number', 'required' => false],
                    'duration_minutes' => ['type' => 'number', 'required' => false],
                    'category' => ['type' => 'string', 'required' => false],
                    'description' => ['type' => 'string', 'required' => false],
                ],
                'who_can_call' => ['dashboard', 'ali'],
                'triggers_prompt_rebuild' => true,
                'notification' => 'service_updated',
            ],
            [
                'name' => 'toggle_service',
                'description' => 'تفعيل أو إيقاف خدمة',
                'parameters' => [
                    'service_id' => ['type' => 'string', 'required' => true],
                    'active' => ['type' => 'boolean', 'required' => true],
                ],
                'who_can_call' => ['dashboard', 'ali'],
                'triggers_prompt_rebuild' => true,
                'notification' => 'service_toggled',
            ],

            // === Bookings ===
            [
                'name' => 'create_booking',
                'description' => 'حجز موعد جديد',
                'parameters' => [
                    'branch_id' => ['type' => 'string', 'required' => true],
                    'service_id' => ['type' => 'string', 'required' => true],
                    'customer_name' => ['type' => 'string', 'required' => true],
                    'customer_phone' => ['type' => 'string', 'required' => true],
                    'date_time' => ['type' => 'datetime', 'required' => true],
                    'specialist_id' => ['type' => 'string', 'required' => false],
                ],
                'who_can_call' => ['dashboard', 'ali', 'snad'],
                'triggers_prompt_rebuild' => false,
                'notification' => 'new_booking',
            ],
            [
                'name' => 'cancel_booking',
                'description' => 'إلغاء موعد',
                'parameters' => [
                    'booking_id' => ['type' => 'string', 'required' => true],
                    'reason' => ['type' => 'string', 'required' => false],
                ],
                'who_can_call' => ['dashboard', 'ali', 'snad'],
                'triggers_prompt_rebuild' => false,
                'notification' => 'booking_cancelled',
            ],

            // === Business ===
            [
                'name' => 'update_working_hours',
                'description' => 'تعديل أوقات العمل',
                'parameters' => [
                    'branch_id' => ['type' => 'string', 'required' => true],
                    'working_hours' => ['type' => 'object', 'required' => true],
                ],
                'who_can_call' => ['dashboard', 'ali'],
                'triggers_prompt_rebuild' => true,
                'notification' => 'hours_updated',
            ],
            [
                'name' => 'update_policy',
                'description' => 'تعديل سياسة (إلغاء، دفع، إلخ)',
                'parameters' => [
                    'business_id' => ['type' => 'string', 'required' => true],
                    'key' => ['type' => 'string', 'required' => true],
                    'value' => ['type' => 'string', 'required' => true],
                ],
                'who_can_call' => ['dashboard', 'ali'],
                'triggers_prompt_rebuild' => true,
                'notification' => 'policy_updated',
            ],

            // === Reports ===
            [
                'name' => 'get_daily_stats',
                'description' => 'تقرير إحصائيات اليوم',
                'parameters' => [
                    'business_id' => ['type' => 'string', 'required' => true],
                    'date' => ['type' => 'date', 'required' => false],
                ],
                'who_can_call' => ['dashboard', 'ali'],
                'triggers_prompt_rebuild' => false,
                'notification' => null,
            ],
        ];
    }

    public static function forAgent(string $agent): array
    {
        return array_filter(self::all(), fn($action) =>
            in_array($agent, $action['who_can_call'])
        );
    }

    public static function find(string $name): ?array
    {
        foreach (self::all() as $action) {
            if ($action['name'] === $name) {
                return $action;
            }
        }
        return null;
    }

    public static function triggersRebuild(string $name): bool
    {
        $action = self::find($name);
        return $action ? $action['triggers_prompt_rebuild'] : false;
    }
}
