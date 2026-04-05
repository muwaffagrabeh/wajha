<?php

namespace App\Services\Onboarding;

use App\Models\SectorType;
use OpenAI\Laravel\Facades\OpenAI;

class DataExtractor
{
    /**
     * Detect sector type from free text.
     * Tries keywords first, then LLM.
     */
    public function detectSectorType(string $input): array
    {
        // 1. Keyword matching (no LLM)
        $keywords = [
            'salon_women' => ['صالون نسائي', 'كوافيرة', 'صالون حريم'],
            'barber_men' => ['حلاق', 'باربر', 'صالون رجالي'],
            'dental_center' => ['أسنان', 'عيادة أسنان'],
            'clinic' => ['عيادة', 'مركز طبي'],
            'grocery' => ['مطعم', 'شاورما', 'برجر', 'بيتزا', 'كافيه', 'كوفي', 'بقالة', 'كافتيريا'],
            'laundry' => ['مغسلة', 'مصبغة'],
            'upholstery_shop' => ['تنجيد'],
            'cleaning_company' => ['تنظيف', 'نظافة'],
            'plumbing_electrical' => ['سباك', 'كهربائي', 'سباكة', 'كهرباء'],
            'law_firm' => ['محامي', 'محاماة', 'قانون'],
            'vet_clinic' => ['بيطري', 'بيطرية'],
            'training_center' => ['تدريب', 'دورات', 'معهد'],
            'ac_company' => ['تكييف', 'مكيفات'],
            'print_shop' => ['مطبعة', 'طباعة'],
            'car_showroom' => ['معرض سيارات', 'سيارات'],
            'shipping_company' => ['شحن', 'توصيل'],
        ];

        $lower = mb_strtolower($input);
        foreach ($keywords as $typeId => $kws) {
            foreach ($kws as $kw) {
                if (str_contains($lower, $kw)) {
                    // Also try to extract name and city
                    $extra = $this->extractNameCity($input);
                    return array_merge(['sector_type_id' => $typeId], $extra);
                }
            }
        }

        // 2. LLM fallback
        return $this->detectViaLLM($input);
    }

    /**
     * Extract business name and city from text.
     */
    public function extractNameCity(string $input): array
    {
        $prompt = <<<P
استخرج اسم النشاط التجاري والمدينة من النص التالي.
رد بـ JSON فقط: {"name": "...", "city": "..."}
اللي ما تلاقيه خله null. لا تضيف أي شي ثاني.
P;
        return $this->callLLM($input, $prompt);
    }

    /**
     * Extract service changes (add/update/remove) using terminology.
     */
    public function extractServiceChanges(string $input, array $currentServices, array $terminology): array
    {
        $prompt = "الخدمات الحالية: " . json_encode($currentServices, JSON_UNESCAPED_UNICODE) . "\n\n"
            . "القاموس: " . json_encode($terminology, JSON_UNESCAPED_UNICODE) . "\n\n"
            . "المستخدم قال: {$input}\n\n"
            . "استخرج التغييرات كـ JSON:\n"
            . "[{\"action\": \"update|add|remove\", \"name\": \"...\", \"price\": N, \"category\": \"...\"}]\n"
            . "aliases تتحول للاسم الرسمي. إذا 'تمام' → رجّع []. رد بـ JSON فقط.";

        return $this->callLLM($input, $prompt);
    }

    /**
     * Extract specialists and their services.
     */
    public function extractSpecialists(string $input, array $existingServices): array
    {
        $serviceNames = collect($existingServices)->pluck('name')->toArray();
        $prompt = "استخرج المختصين وخدماتهم.\n"
            . "الخدمات الموجودة: " . json_encode($serviceNames, JSON_UNESCAPED_UNICODE) . "\n"
            . "'كل شي' أو 'الكل' = كل الخدمات.\n"
            . "رد بـ JSON: {\"specialists\": [{\"name\": \"...\", \"role\": \"...\", \"services\": [\"...\"]}]}\n"
            . "النص: {$input}";

        return $this->callLLM($input, $prompt);
    }

    private function detectViaLLM(string $input): array
    {
        $types = SectorType::with('sector')->get()
            ->map(fn($t) => "{$t->id}: {$t->label} ({$t->sector->label})")
            ->join("\n");

        $prompt = "طابق نوع النشاط مع أقرب sector_type_id.\n\nالقائمة:\n{$types}\n\n"
            . "رد بـ JSON: {\"sector_type_id\": \"...\", \"name\": \"...\", \"city\": \"...\"}\n"
            . "اللي ما تلاقيه خله null.";

        return $this->callLLM($input, $prompt);
    }

    public ?array $lastLLMOutput = null;

    private function callLLM(string $input, string $prompt): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $input],
            ],
            'temperature' => 0.1,
            'max_tokens' => 200,
        ]);

        $content = trim($response->choices[0]->message->content ?? '');
        $raw = $content;
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\n?/', '', $content);
            $content = preg_replace('/\n?```$/', '', $content);
        }

        $parsed = json_decode($content, true) ?? [];
        $this->lastLLMOutput = ['raw' => $raw, 'parsed' => $parsed, 'tokens' => $response->usage->totalTokens ?? 0];
        return $parsed;
    }
}
