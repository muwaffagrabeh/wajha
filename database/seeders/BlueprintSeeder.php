<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $blueprints = [
            [
                'id' => 'appointments',
                'label' => 'حجز مواعيد مع مختصين',
                'label_en' => 'Appointments & Specialists',
                'base_flow' => json_encode(['sector', 'preview', 'name_city', 'services', 'prices', 'service_mode', 'specialists']),
                'base_features' => json_encode(['حجز', 'تذكير', 'تقييم', 'متابعة']),
                'requires' => json_encode(['services', 'specialists']),
                'optional' => json_encode(['service_mode']),
            ],
            [
                'id' => 'orders',
                'label' => 'طلبات ومنتجات',
                'label_en' => 'Orders & Products',
                'base_flow' => json_encode(['sector', 'preview', 'name_city', 'services', 'prices']),
                'base_features' => json_encode(['طلب', 'توصيل', 'تتبع', 'تقييم']),
                'requires' => json_encode(['services']),
                'optional' => json_encode(['delivery']),
            ],
            [
                'id' => 'listings',
                'label' => 'عرض وتأهيل عملاء',
                'label_en' => 'Listings & Client Qualification',
                'base_flow' => json_encode(['sector', 'preview', 'name_city', 'services', 'prices', 'specialists']),
                'base_features' => json_encode(['عرض', 'تأهيل', 'تحويل', 'متابعة']),
                'requires' => json_encode(['services', 'specialists']),
                'optional' => null,
            ],
            [
                'id' => 'reservations',
                'label' => 'حجوزات إقامة',
                'label_en' => 'Accommodation Reservations',
                'base_flow' => json_encode(['sector', 'preview', 'name_city', 'services', 'prices']),
                'base_features' => json_encode(['حجز', 'تسجيل دخول', 'تسجيل خروج', 'تقييم']),
                'requires' => json_encode(['services']),
                'optional' => null,
            ],
            [
                'id' => 'estimates',
                'label' => 'طلب خدمة وعرض سعر',
                'label_en' => 'Service Requests & Estimates',
                'base_flow' => json_encode(['sector', 'preview', 'name_city', 'services', 'prices', 'service_mode']),
                'base_features' => json_encode(['طلب', 'معاينة', 'عرض سعر', 'متابعة']),
                'requires' => json_encode(['services']),
                'optional' => json_encode(['service_mode']),
            ],
        ];

        DB::table('blueprints')->insert($blueprints);
    }
}
