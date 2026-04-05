<?php

namespace App\Services\Ali;

use App\Actions\ActionRegistry;
use App\Models\Business;

class AliPromptBuilder
{
    public static function build(?Business $business = null): string
    {
        $tools = self::buildToolsDescription();
        $businessContext = $business ? self::buildBusinessContext($business) : '';

        return <<<PROMPT
<agent>
  <identity>
    <name>علي</name>
    <role>وكيل إدارة أعمال — يمثل المالك في إعداد وتشغيل وإدارة النشاط التجاري والوكلاء الفرعيين</role>
    <tone>مهني وعملي · يتكلم كشريك مدير · مباشر بدون إنشاء · لهجة سعودية</tone>
    <language>ar-SA</language>
    <speaks_to>صاحب العمل فقط — لا يتواصل مع العملاء النهائيين</speaks_to>
  </identity>

  <capabilities>
    <cap>إعداد نشاط تجاري جديد من الصفر</cap>
    <cap>بناء وتعديل برومبت الوكيل الفرعي (سند)</cap>
    <cap>إدارة قائمة الخدمات/المنتجات (إضافة، تعديل، حذف، تسعير)</cap>
    <cap>إدارة الإعدادات التشغيلية (أوقات، مواقع، سياسات)</cap>
    <cap>مراقبة أداء الوكيل الفرعي وتقديم تقارير</cap>
    <cap>تعديل سلوك الوكيل الفرعي بناءً على توجيه المالك</cap>
  </capabilities>

  <available_actions>
{$tools}
  </available_actions>

{$businessContext}

  <output_format>
    رد دائماً بـ JSON بهذا الهيكل:
    {
      "intent": "setup | modify | query | control",
      "entities": ["service", "price", "hours", "policy", "tone", "stats"],
      "action": "اسم الإجراء من available_actions أو null",
      "params": { المعاملات المطلوبة للإجراء },
      "needs_confirmation": true/false,
      "question": "سؤال للمالك إذا المعلومات ناقصة أو null",
      "response_text": "الرد المباشر للمالك",
      "changes_summary": "ملخص التغييرات اللي تمت أو null"
    }
  </output_format>

  <rules>
    <rule priority="critical">لا تنفّذ أمر يتعارض مع أمر سابق بدون تنبيه المالك</rule>
    <rule priority="critical">لا تحذف بيانات بدون تأكيد صريح</rule>
    <rule priority="critical">لا تكشف بيانات العملاء للمالك إلا بطلب محدد</rule>
    <rule priority="high">كل تعديل على البرومبت → اعرض مثال على سلوك سند الجديد</rule>
    <rule priority="high">إذا المالك طلب شي غامض → اسأل سؤال واحد محدد قبل التنفيذ</rule>
    <rule priority="high">لا تضيف افتراضات بدون ذكرها — قول "افترضت كذا، صح؟"</rule>
    <rule priority="normal">ردودك مباشرة — لا مقدمات ولا خاتمة إنشائية</rule>
    <rule priority="normal">بعد كل تنفيذ → أكّد بسطر واحد + اسأل "شي ثاني؟"</rule>
  </rules>
</agent>
PROMPT;
    }

    private static function buildToolsDescription(): string
    {
        $actions = ActionRegistry::forAgent('ali');
        $lines = [];

        foreach ($actions as $action) {
            $params = [];
            foreach ($action['parameters'] as $name => $spec) {
                $required = ($spec['required'] ?? false) ? 'مطلوب' : 'اختياري';
                $params[] = "      {$name}: {$spec['type']} ({$required})";
            }
            $paramsStr = implode("\n", $params);

            $lines[] = "    - name: {$action['name']}\n      description: {$action['description']}\n      rebuilds_prompt: " . ($action['triggers_prompt_rebuild'] ? 'نعم' : 'لا') . "\n      params:\n{$paramsStr}";
        }

        return implode("\n\n", $lines);
    }

    private static function buildBusinessContext(Business $business): string
    {
        $business->load(['branches.specialists', 'serviceItems', 'policies', 'sectorType.sector']);

        $services = $business->serviceItems->map(fn($s) =>
            "    - {$s->name} | {$s->price} ريال | {$s->type} | {$s->status}"
        )->join("\n");

        $policies = $business->policies->map(fn($p) =>
            "    - {$p->key}: {$p->value}"
        )->join("\n");

        $branches = $business->branches->map(function ($b) {
            $specialists = $b->specialists->map(fn($s) => $s->name)->join('، ');
            $hours = $b->working_hours ? ($b->working_hours['from'] ?? '?') . ' - ' . ($b->working_hours['to'] ?? '?') : 'غير محدد';
            return "    - {$b->name} | {$b->city} | {$hours} | المختصين: {$specialists}";
        })->join("\n");

        $patternsStr = self::arrayToString($business->active_patterns);
        $layersStr = self::arrayToString($business->active_layers);

        return <<<CTX
  <current_business>
    الاسم: {$business->name}
    القطاع: {$business->sectorType->sector->label} > {$business->sectorType->label}
    الأنماط: [{$patternsStr}]
    الطبقات: [{$layersStr}]

    الفروع:
{$branches}

    الخدمات/المنتجات:
{$services}

    السياسات:
{$policies}
  </current_business>
CTX;
    }

    private static function arrayToString(array $arr): string
    {
        return implode(', ', $arr);
    }
}
