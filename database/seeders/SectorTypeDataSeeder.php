<?php

namespace Database\Seeders;

use App\Models\SectorType;
use Illuminate\Database\Seeder;

class SectorTypeDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── صالون نسائي ──
        SectorType::where('id', 'salon_women')->update([
            'blueprint' => 'appointments',
            'approval_status' => 'approved',
            'has_specialists' => true,
            'default_service_mode' => 'at_branch',
            'show_service_mode_step' => true,
            'default_agent_name' => 'مساعدة {business_name}',
            'default_agent_gender' => 'female',
            'onboarding_steps' => json_encode(['sector', 'preview', 'name_city', 'services', 'prices', 'service_mode', 'specialists']),
            'preview' => json_encode([
                'pain_points' => [
                    'عميلة تتصل بعد الدوام ← ما أحد رد ← راحت لغيرك',
                    'عميلة تسأل بالواتساب ← رد متأخر ← حجزت عند المنافسة',
                    'عميلة تنسى موعدها ← ما جات ← خسارة',
                    'ما تعرفين كم عميلة فاتتك الشهر هذا',
                ],
                'outcomes' => [
                    'كل استفسار يجيه رد فوري — حتى 3 الفجر',
                    'كل عميلة تحجز موعدها تلقائياً',
                    'تذكير قبل الموعد — العميلة ما تنسى',
                    'كل شي تشوفينه بلوحتك — مين سألت، وش تبي، وش صار',
                ],
            ]),
            'agent_rules' => json_encode(['لا تشخّص', 'لا تصف علاج', 'لا توصي بمنتج معين']),
            'terms' => json_encode([
                'specialist' => 'مختصة',
                'specialist_plural' => 'المختصات',
                'service' => 'خدمة',
                'customer' => 'عميلة',
                'booking' => 'موعد',
                'tone_gender' => 'female',
                'service_place' => 'الصالون',
            ]),
            'terminology' => json_encode([
                'categories' => [
                    'صبغة' => ['variants' => ['صبغة كاملة', 'صبغة جذور', 'هايلايت', 'بالياج', 'أومبري']],
                    'قص' => ['variants' => ['قص عادي', 'قص مدرّج', 'قص أطفال', 'قص غرّة']],
                    'بروتين' => ['variants' => ['بروتين برازيلي', 'بروتين معالج', 'كيراتين']],
                    'مكياج' => ['variants' => ['مكياج عروس', 'مكياج سهرة', 'مكياج ناعم']],
                    'تصفيف' => ['variants' => ['سشوار', 'استشوار', 'تسريحة عروس', 'تسريحة سهرة']],
                    'حنا' => ['variants' => ['حنا عادي', 'حنا نقش', 'حنا عروس']],
                    'تنظيف بشرة' => ['variants' => ['تنظيف عادي', 'تنظيف عميق', 'هيدرافيشل']],
                    'إزالة شعر' => ['variants' => ['ليزر', 'واكس', 'خيط']],
                ],
                'aliases' => [
                    'سشوار' => 'تصفيف', 'استشوار' => 'تصفيف', 'فرد' => 'بروتين',
                    'كرياتين' => 'كيراتين', 'ليزر' => 'إزالة شعر بالليزر',
                ],
            ]),
            'default_services_with_prices' => json_encode([
                ['category' => 'قص', 'name' => 'قص شعر', 'price' => 50, 'duration' => 30],
                ['category' => 'صبغة', 'name' => 'صبغة كاملة', 'price' => 200, 'duration' => 120],
                ['category' => 'صبغة', 'name' => 'صبغة جذور', 'price' => 100, 'duration' => 60],
                ['category' => 'بروتين', 'name' => 'بروتين', 'price' => 300, 'duration' => 120],
                ['category' => 'مكياج', 'name' => 'مكياج سهرة', 'price' => 150, 'duration' => 60],
                ['category' => 'تصفيف', 'name' => 'تصفيف', 'price' => 50, 'duration' => 30],
            ]),
        ]);

        // ── حلاق رجالي ──
        SectorType::where('id', 'barber_men')->update([
            'blueprint' => 'appointments',
            'approval_status' => 'approved',
            'has_specialists' => true,
            'default_service_mode' => 'at_branch',
            'show_service_mode_step' => false,
            'default_agent_name' => 'مساعد {business_name}',
            'default_agent_gender' => 'male',
            'onboarding_steps' => json_encode(['sector', 'preview', 'name_city', 'services', 'prices', 'specialists']),
            'preview' => json_encode([
                'pain_points' => [
                    'عميل يتصل وأنت مشغول ← ما رديت ← راح لغيرك',
                    'عميل يسأل بالواتساب ← تأخرت ← حجز عند المنافس',
                    'ما تعرف كم عميل فاتك هالأسبوع',
                ],
                'outcomes' => [
                    'كل استفسار يجيه رد فوري — حتى بعد الدوام',
                    'كل عميل يحجز موعده تلقائياً',
                    'تذكير قبل الموعد — العميل ما ينسى',
                    'كل شي تشوفه بلوحتك',
                ],
            ]),
            'agent_rules' => json_encode([]),
            'terms' => json_encode([
                'specialist' => 'حلاق', 'specialist_plural' => 'الحلاقين',
                'service' => 'خدمة', 'customer' => 'عميل', 'booking' => 'موعد',
                'tone_gender' => 'male', 'service_place' => 'المحل',
            ]),
            'terminology' => json_encode([
                'categories' => [
                    'حلاقة' => ['variants' => ['حلاقة شعر', 'حلاقة + ذقن', 'حلاقة أطفال']],
                    'ذقن' => ['variants' => ['تحديد ذقن', 'حلاقة ذقن', 'تشكيل ذقن']],
                    'صبغة' => ['variants' => ['صبغة رجالي', 'صبغة ذقن']],
                    'بروتين' => ['variants' => ['فرد شعر', 'بروتين رجالي']],
                ],
                'aliases' => ['فرد' => 'بروتين', 'زيرو' => 'حلاقة شعر'],
            ]),
            'default_services_with_prices' => json_encode([
                ['category' => 'حلاقة', 'name' => 'حلاقة شعر', 'price' => 30, 'duration' => 20],
                ['category' => 'حلاقة', 'name' => 'حلاقة + ذقن', 'price' => 40, 'duration' => 30],
                ['category' => 'ذقن', 'name' => 'تحديد ذقن', 'price' => 20, 'duration' => 15],
                ['category' => 'صبغة', 'name' => 'صبغة رجالي', 'price' => 50, 'duration' => 30],
                ['category' => 'بروتين', 'name' => 'فرد شعر', 'price' => 100, 'duration' => 60],
            ]),
        ]);

        // ── عيادة أسنان ──
        SectorType::where('id', 'dental_center')->update([
            'blueprint' => 'appointments',
            'approval_status' => 'approved',
            'has_specialists' => true,
            'default_service_mode' => 'at_branch',
            'show_service_mode_step' => false,
            'default_agent_name' => 'مساعد {business_name}',
            'default_agent_gender' => 'male',
            'onboarding_steps' => json_encode(['sector', 'preview', 'name_city', 'services', 'prices', 'specialists']),
            'preview' => json_encode([
                'pain_points' => [
                    'مريض يتصل بعد الدوام ← ما أحد رد ← حجز بعيادة ثانية',
                    'مريض ينسى موعده ← ما جاء ← خسارة وقت الطبيب',
                    'استفسارات متكررة عن الأسعار تشغل الموظفات',
                ],
                'outcomes' => [
                    'كل استفسار يجيه رد فوري — 24/7',
                    'حجز مواعيد تلقائي مع الطبيب المناسب',
                    'تذكير قبل الموعد — المريض ما ينسى',
                    'تقارير يومية عن الحجوزات والاستفسارات',
                ],
            ]),
            'agent_rules' => json_encode([
                'لا تشخّص', 'لا تصف علاج', 'لا تعطي رأي طبي',
                'أي سؤال طبي → يجب استشارة الطبيب',
            ]),
            'terms' => json_encode([
                'specialist' => 'طبيب', 'specialist_plural' => 'الأطباء',
                'service' => 'إجراء', 'customer' => 'مريض', 'booking' => 'موعد',
                'tone_gender' => 'male', 'service_place' => 'العيادة',
            ]),
            'terminology' => json_encode([
                'categories' => [
                    'تنظيف' => ['variants' => ['تنظيف عادي', 'تنظيف عميق', 'تنظيف لثة']],
                    'حشوة' => ['variants' => ['حشوة عادية', 'حشوة تجميلية', 'حشوة عصب']],
                    'تقويم' => ['variants' => ['تقويم معدني', 'تقويم شفاف', 'تقويم متحرك']],
                    'خلع' => ['variants' => ['خلع عادي', 'خلع جراحي', 'خلع ضرس عقل']],
                    'تبييض' => ['variants' => ['تبييض بالليزر', 'تبييض منزلي', 'فينير']],
                    'زراعة' => ['variants' => ['زراعة سن', 'زراعة فورية']],
                    'تاج' => ['variants' => ['تاج زيركون', 'تاج خزفي', 'تاج معدني']],
                ],
                'aliases' => ['تلبيسة' => 'تاج', 'عصب' => 'حشوة عصب', 'تركيبة' => 'تاج'],
            ]),
            'default_services_with_prices' => json_encode([
                ['category' => 'تنظيف', 'name' => 'تنظيف عادي', 'price' => 200, 'duration' => 30],
                ['category' => 'تنظيف', 'name' => 'تنظيف عميق', 'price' => 400, 'duration' => 45],
                ['category' => 'حشوة', 'name' => 'حشوة عادية', 'price' => 150, 'duration' => 30],
                ['category' => 'حشوة', 'name' => 'حشوة تجميلية', 'price' => 300, 'duration' => 45],
                ['category' => 'خلع', 'name' => 'خلع عادي', 'price' => 200, 'duration' => 20],
                ['category' => 'تبييض', 'name' => 'تبييض بالليزر', 'price' => 800, 'duration' => 60],
                ['category' => 'تقويم', 'name' => 'تقويم شفاف', 'price' => 8000, 'duration' => 30],
            ]),
        ]);
    }
}
