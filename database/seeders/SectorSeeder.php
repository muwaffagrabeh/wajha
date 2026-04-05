<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            ['id' => 'hospitality_tourism',      'label' => 'الضيافة والسياحة',                'label_en' => 'Hospitality & Tourism',           'icon' => '🏨', 'sort_order' => 1],
            ['id' => 'health_beauty',             'label' => 'الصحة والعناية الشخصية',          'label_en' => 'Health & Beauty',                 'icon' => '💇‍♀️', 'sort_order' => 2],
            ['id' => 'retail',                    'label' => 'البيع بالتجزئة',                  'label_en' => 'Retail',                          'icon' => '🛍️', 'sort_order' => 3],
            ['id' => 'professional_services',     'label' => 'الخدمات المهنية والاستشارية',     'label_en' => 'Professional Services',           'icon' => '💼', 'sort_order' => 4],
            ['id' => 'contracting_maintenance',   'label' => 'المقاولات والصيانة والحرف',       'label_en' => 'Contracting & Maintenance',       'icon' => '🔧', 'sort_order' => 5],
            ['id' => 'logistics',                 'label' => 'الخدمات اللوجستية',              'label_en' => 'Logistics',                       'icon' => '🚚', 'sort_order' => 6],
            ['id' => 'education_training',        'label' => 'التعليم والتدريب',                'label_en' => 'Education & Training',            'icon' => '📚', 'sort_order' => 7],
            ['id' => 'events',                    'label' => 'خدمات المناسبات',                'label_en' => 'Events',                          'icon' => '🎉', 'sort_order' => 8],
            ['id' => 'brokerage_platforms',        'label' => 'الوساطة والمنصات',               'label_en' => 'Brokerage & Platforms',           'icon' => '🔗', 'sort_order' => 9],
            ['id' => 'marketing_media',           'label' => 'التسويق والإعلام',                'label_en' => 'Marketing & Media',               'icon' => '📢', 'sort_order' => 10],
            ['id' => 'technology',                'label' => 'التقنية وتكنولوجيا المعلومات',    'label_en' => 'Technology & IT',                 'icon' => '💻', 'sort_order' => 11],
            ['id' => 'financial_services',        'label' => 'الخدمات المالية',                 'label_en' => 'Financial Services',              'icon' => '🏦', 'sort_order' => 12],
            ['id' => 'real_estate',               'label' => 'العقارات والتطوير',               'label_en' => 'Real Estate & Development',       'icon' => '🏗️', 'sort_order' => 13],
            ['id' => 'agriculture_food',          'label' => 'الزراعة والغذاء',                 'label_en' => 'Agriculture & Food',              'icon' => '🌾', 'sort_order' => 14],
            ['id' => 'manufacturing',             'label' => 'الصناعة والتصنيع',                'label_en' => 'Manufacturing',                   'icon' => '🏭', 'sort_order' => 15],
            ['id' => 'animal_services',           'label' => 'خدمات الحيوانات',                'label_en' => 'Animal Services',                 'icon' => '🐾', 'sort_order' => 16],
            ['id' => 'personal_home_services',    'label' => 'الخدمات الشخصية والمنزلية',       'label_en' => 'Personal & Home Services',        'icon' => '🏠', 'sort_order' => 17],
            ['id' => 'government_services',       'label' => 'الخدمات الحكومية والتنظيمية',     'label_en' => 'Government & Regulatory',         'icon' => '🏛️', 'sort_order' => 18],
        ];

        foreach ($sectors as $sector) {
            Sector::create($sector);
        }
    }
}
