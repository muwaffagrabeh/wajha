# علي — الهيكل الشامل المحدّث

---

## أولاً: المستودع الكامل (9 أنماط + 8 طبقات + 9 قواعد قطاعية)

---

### الأنماط (9)

```yaml
patterns:

  # ─── 1. حجز موعد ───
  booking:
    label: "حجز موعد"
    trigger: "العميل يبي يحجز وقت محدد مع مختص أو لخدمة"
    steps:
      - ask: service → date_time → specialist(optional)
      - action: check_availability → confirm | suggest_alternative
      - action: send_confirmation → schedule_reminder
    requires_from_config: [services, specialists, working_hours]
    validator: [no_booking_outside_hours, no_double_booking, specialist_exists]
    dashboard_equivalent: "صفحة المواعيد → إضافة موعد"

  # ─── 2. طلب منتج ───
  product_order:
    label: "طلب منتج"
    trigger: "العميل يبي يشتري منتج أو يطلب أكل"
    steps:
      - ask: product → quantity
      - action: check_stock → calculate_total → show_summary
      - ask: confirm
      - action: create_order
    requires_from_config: [products, stock, prices]
    validator: [product_exists, price_matches, stock_available]
    dashboard_equivalent: "صفحة الطلبات → إنشاء طلب يدوي"

  # ─── 3. طلب خدمة ───
  service_request:
    label: "طلب خدمة"
    trigger: "العميل يبي خدمة تتطلب زيارة أو معاينة أو تنفيذ"
    steps:
      - ask: service_type → location → preferred_time
      - ask: details (صور/وصف إضافي)
      - action: schedule_visit → assign_technician
      - action: send_confirmation
    requires_from_config: [services, coverage_zones, technicians]
    validator: [service_exists, location_in_coverage]
    dashboard_equivalent: "صفحة الطلبات → طلب خدمة جديد"

  # ─── 4. تأهيل عميل ───
  client_qualification:
    label: "تأهيل عميل"
    trigger: "العميل يحتاج استشارة أو خدمة مهنية تتطلب فهم حالته أولاً"
    steps:
      - ask: what_do_you_need → classify_category
      - ask: dynamic_followup (حسب التصنيف)
      - action: create_lead → assign_specialist → book_consultation
    requires_from_config: [service_categories, specialists]
    validator: [never_give_professional_advice, never_promise_outcome]
    dashboard_equivalent: "صفحة العملاء المحتملين → إضافة عميل"

  # ─── 5. تتبع حالة ───
  status_tracking:
    label: "تتبع حالة"
    trigger: "العميل يسأل وين طلبه أو شحنته أو معاملته"
    steps:
      - ask: reference_number
      - action: fetch_status_from_db → format_response
    requires_from_config: [orders_table | shipments_table | transactions_table]
    validator: [status_from_db_only, never_estimate_without_data]
    dashboard_equivalent: "صفحة الطلبات → بحث بالرقم"

  # ─── 6. عرض كتالوج ───
  catalog_browse:
    label: "عرض كتالوج"
    trigger: "العميل يتصفح أو يدوّر على منتج/خدمة/عقار"
    steps:
      - ask: what_are_you_looking_for → extract_criteria
      - action: search_catalog → filter → sort → show_top_3
      - ask: want_more_details
    requires_from_config: [catalog, categories, filters]
    validator: [only_active_items, prices_match_db]
    dashboard_equivalent: "صفحة المنتجات/العروض → بحث وفلترة"

  # ─── 7. عرض وتوصية ───
  recommend:
    label: "توصية ذكية"
    trigger: "العميل يبي نصيحة أو اقتراح بناءً على معايير"
    steps:
      - ask: preferences (budget, location, size, purpose...)
      - action: match_and_rank → show_top_3_with_comparison
    requires_from_config: [catalog, scoring_weights]
    validator: [only_available_items, never_fabricate_features]
    dashboard_equivalent: "لا يوجد مكافئ — هذا حصري للوكيل"

  # ─── 8. تأجير ─── [جديد]
  rental:
    label: "تأجير"
    trigger: "العميل يبي يأجر (سيارة، معدة، شاليه، قاعة، كراسي)"
    steps:
      - ask: what_to_rent → dates_from_to → quantity
      - action: check_availability → calculate_total (مدة × سعر)
      - ask: confirm
      - action: create_reservation → collect_deposit_info
      - action: send_contract_summary
    requires_from_config: [rental_items, pricing_per_unit, deposit_policy, availability_calendar]
    validator: [item_available_for_dates, price_matches, no_contract_modification]
    dashboard_equivalent: "صفحة الحجوزات → حجز جديد"

  # ─── 9. عرض سعر مخصص ─── [جديد]
  custom_quote:
    label: "عرض سعر مخصص"
    trigger: "العميل يبي شي مصنوع حسب الطلب (طباعة، تفصيل، فيديو، بناء)"
    steps:
      - ask: what_do_you_need → specifications → quantity → deadline
      - action: calculate_estimate (من جدول الأسعار القياسية)
        fallback: "السعر النهائي يتحدد بعد مراجعة المختص"
      - action: create_quote_request → assign_to_specialist
    requires_from_config: [base_prices, customization_options, specialists]
    validator: [estimate_within_range, never_commit_final_price_without_approval]
    dashboard_equivalent: "صفحة عروض الأسعار → طلب جديد"
```

---

### الطبقات (8)

```yaml
layers:

  # ─── 1. توصيل ───
  delivery:
    fields: [delivery_zones, delivery_fee, estimated_time, provider]
    inserts_after: order_confirmed
    steps: [ask_address → validate_zone → calculate_fee → track]
    validator: [zone_exists, fee_matches]
    dashboard_equivalent: "إعدادات → التوصيل"

  # ─── 2. دفع ───
  payment:
    fields: [payment_methods, gateway_config]
    inserts_after: total_calculated
    steps: [show_methods → process_or_send_link → confirm_payment]
    validator: [amount_matches_order]
    dashboard_equivalent: "إعدادات → الدفع"

  # ─── 3. ضمان ───
  warranty:
    fields: [warranty_period, warranty_terms]
    inserts_after: sale_completed
    steps: [send_warranty_info]
    on_trigger: warranty_claim → verify → create_ticket
    validator: [dates_accurate]
    dashboard_equivalent: "إعدادات → سياسة الضمان"

  # ─── 4. تذكير ───
  reminder:
    fields: [reminder_before_minutes]
    inserts_after: booking_confirmed
    steps: [schedule_reminder → send_at_time]
    dashboard_equivalent: "إعدادات → التذكيرات"

  # ─── 5. متابعة بعد الخدمة ───
  followup:
    fields: [followup_after_hours, followup_message]
    inserts_after: service_completed
    steps: [wait_duration → send_followup]
    dashboard_equivalent: "إعدادات → المتابعة"

  # ─── 6. اشتراك/عضوية ─── [جديد]
  subscription:
    fields: [plans, billing_cycle, freeze_policy, renewal_rules]
    inserts_after: client_registered
    steps: [show_plans → select → activate → schedule_renewal]
    on_trigger: cancel | freeze | upgrade → process → confirm
    validator: [plan_exists, dates_valid, no_modification_without_approval]
    dashboard_equivalent: "صفحة الاشتراكات → إدارة العضويات"

  # ─── 7. مستندات ─── [جديد]
  documents:
    fields: [required_documents_per_service, document_status_tracking]
    inserts_after: service_request_created
    steps: [list_required_docs → receive_docs → mark_complete → notify_if_missing]
    validator: [doc_list_matches_service, never_approve_completeness_without_verification]
    dashboard_equivalent: "صفحة المعاملات → المستندات المطلوبة"

  # ─── 8. تقييم بعد الخدمة ─── [جديد]
  rating:
    fields: [rating_delay_hours, rating_questions]
    inserts_after: service_completed | order_delivered
    steps: [wait_delay → ask_rating → ask_feedback → save → alert_if_low]
    alert: if rating < 3 → notify_owner
    dashboard_equivalent: "صفحة التقييمات"
```

---

### القواعد القطاعية (9)

```yaml
sector_rules:

  medical:
    rules: [لا_تشخيص, لا_وصف_علاج, لا_رأي_طبي, حوّل_للطبيب]
    tone: "مهني ومطمئن"
    sensitive_data: true

  legal:
    rules: [لا_استشارة_قانونية, لا_تفسير_أنظمة, حوّل_للمحامي]
    tone: "رسمي ودقيق"
    sensitive_data: true

  financial:  # [جديد]
    rules: [لا_نصيحة_مالية, لا_موافقة_تمويل, لا_كشف_بيانات_حساسة]
    tone: "مهني وحذر"
    sensitive_data: true

  real_estate:
    rules: [لا_اختراع_عقارات, لا_تعديل_سعر, لا_رأي_استثماري]
    tone: "مهني ومباشر"

  food:
    rules: [لا_تعديل_طلب_بعد_تأكيد, اذكر_تحذيرات_غذائية]
    tone: "ودود وسريع"

  children:  # [جديد]
    rules: [لا_تواصل_مع_طفل, كل_التواصل_مع_ولي_أمر, لا_مشاركة_بيانات_طفل]
    tone: "دافئ ومطمئن"
    sensitive_data: true

  government:  # [جديد]
    rules: [لا_تفسير_أنظمة, لا_ضمان_نتيجة_معاملة, لا_تقدير_مدة_بدون_بيانات]
    tone: "مهني وواضح"

  animals:  # [جديد]
    rules: [لا_تشخيص, لا_وصف_علاج, حالة_طوارئ_حوّل_فوراً]
    tone: "ودود ومطمئن"

  rental:  # [جديد]
    rules: [لا_تعديل_عقد, لا_إعفاء_من_تأمين, لا_تمديد_بدون_موافقة]
    tone: "مهني وواضح"
```

---

## ثانياً: نموذج العمل — هيكل المنتج

```
┌───────────────────────────────────────────────────────┐
│                    المشترك (صاحب العمل)                │
└──────────┬────────────────────────────────────────────┘
           │
           ▼
┌───────────────────────────────────────────────────────┐
│              لوحة التحكم (Dashboard)                   │
│                                                       │
│  ┌─────────────┐ ┌──────────────┐ ┌────────────────┐  │
│  │ إدخال       │ │ إعدادات      │ │ مراقبة         │  │
│  │ خدمات/      │ │ تشغيلية      │ │ وتقارير        │  │
│  │ منتجات      │ │ (أوقات،      │ │ (محادثات،      │  │
│  │             │ │ سياسات،      │ │ أخطاء،         │  │
│  │             │ │ دفع، توصيل)  │ │ تقييمات)       │  │
│  └─────────────┘ └──────────────┘ └────────────────┘  │
│                                                       │
│  ┌─────────────────────────────────────────────────┐  │
│  │              محادثة علي                          │  │
│  │  (كل شي تسويه من اللوحة، علي يقدر يسويه       │  │
│  │   من المحادثة والعكس صحيح)                      │  │
│  └─────────────────────────────────────────────────┘  │
│                                                       │
│  ┌─────────────────────────────────────────────────┐  │
│  │              إشعارات                             │  │
│  │  لوحة التحكم ←→ تليجرام                          │  │
│  │  (حجز جديد، طلب جديد، تصعيد، تنبيه خطأ)        │  │
│  └─────────────────────────────────────────────────┘  │
└──────────┬────────────────────────────────────────────┘
           │
           ▼
┌───────────────────────────────────────────────────────┐
│              المخرجات (يختار المشترك)                   │
│                                                       │
│  خيار أ: صفحة تعريفية/موقع + حجز (نبنيها له)         │
│  خيار ب: شات ذكي يندمج في موقعه الحالي (widget)       │
│  خيار ج: الاثنين معاً                                 │
│                                                       │
│  + واتساب / تليجرام (قنوات الوكيل)                     │
└──────────┬────────────────────────────────────────────┘
           │
           ▼
┌───────────────────────────────────────────────────────┐
│                    العملاء النهائيين                    │
└───────────────────────────────────────────────────────┘
```

---

## ثالثاً: القاعدة الذهبية — اللوحة = الوكيل

```
كل إجراء في لوحة التحكم = أمر يقدر علي ينفذه
كل إجراء علي ينفذه = يظهر في لوحة التحكم

لا يوجد شي حصري لجهة واحدة
```

### كيف يتحقق هذا تقنياً

```
┌──────────────────────────────────────────────────┐
│                  طبقة الإجراءات                    │
│                  (Actions Layer)                   │
│                                                  │
│  كل إجراء = دالة واحدة يستدعيها:                  │
│  - لوحة التحكم (عبر API)                          │
│  - علي (عبر function calling)                     │
│  - سند (عبر function calling)                     │
│                                                  │
│  add_service()          → نفس الدالة              │
│  update_price()         → نفس الدالة              │
│  create_booking()       → نفس الدالة              │
│  cancel_booking()       → نفس الدالة              │
│  get_stats()            → نفس الدالة              │
│  update_working_hours() → نفس الدالة              │
│  toggle_service()       → نفس الدالة              │
│  ...                                             │
└──────────────────────────────────────────────────┘
```

```php
// Laravel — مثال: إضافة خدمة
// app/Actions/Services/CreateService.php

class CreateService
{
    public function execute(array $data): Service
    {
        // نفس الكود سواء جاء من:
        // 1. لوحة التحكم (ServiceController@store)
        // 2. علي (function call: create_service)
        // 3. سند (function call: create_service — لو سمحت له)

        $validated = $this->validate($data);
        $service = Service::create($validated);
        $this->rebuildAgentPrompt($service->business_id);
        $this->notify($service->business_id, 'service_created', $service);

        return $service;
    }

    private function rebuildAgentPrompt($businessId)
    {
        // هنا السحر: كل تعديل يعيد بناء برومبت سند تلقائياً
        PromptBuilder::rebuild($businessId);
    }
}
```

---

## رابعاً: علي واعي بالكود — كيف يعرف وش يقدر يسوي

### المشكلة
إذا أضفت دالة جديدة في الكود، تحتاج تعلّم علي عنها يدوياً؟ **لا.**

### الحل: سجل الإجراءات (Action Registry)

```php
// app/Actions/ActionRegistry.php

class ActionRegistry
{
    /**
     * كل Action يسجّل نفسه هنا تلقائياً
     * علي يقرأ هذا السجل ويعرف كل إجراء ممكن
     */

    public static function all(): array
    {
        return [
            [
                'name' => 'create_service',
                'description' => 'إضافة خدمة أو منتج جديد',
                'parameters' => [
                    'name' => ['type' => 'string', 'required' => true],
                    'price' => ['type' => 'number', 'required' => true],
                    'duration_minutes' => ['type' => 'number', 'required' => false],
                    'requires_booking' => ['type' => 'boolean', 'default' => false],
                ],
                'who_can_call' => ['dashboard', 'ali'],
                'triggers_prompt_rebuild' => true,
                'notification' => 'service_created',
            ],
            [
                'name' => 'update_price',
                'description' => 'تعديل سعر خدمة أو منتج',
                'parameters' => [
                    'service_id' => ['type' => 'string', 'required' => true],
                    'new_price' => ['type' => 'number', 'required' => true],
                ],
                'who_can_call' => ['dashboard', 'ali'],
                'triggers_prompt_rebuild' => true,
                'notification' => 'price_updated',
            ],
            [
                'name' => 'create_booking',
                'description' => 'حجز موعد جديد',
                'parameters' => [
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
            // ... باقي الإجراءات
        ];
    }
}
```

### كيف علي يقرأ السجل

```php
// app/Services/AliPromptBuilder.php

class AliPromptBuilder
{
    public function buildToolsSection(): string
    {
        $actions = ActionRegistry::all();
        $aliActions = array_filter($actions, fn($a) =>
            in_array('ali', $a['who_can_call'])
        );

        // يتحول تلقائياً إلى tools في الـ API call
        return $this->formatAsTools($aliActions);
    }
}
```

### النتيجة

```
أنت تضيف Action جديد في الكود
     ↓
تسجّله في ActionRegistry (سطرين)
     ↓
علي يعرف عنه تلقائياً في المحادثة التالية
     ↓
ما تحتاج تعدّل برومبت علي أبداً
```

---

## خامساً: إعادة بناء البرومبت تلقائياً

```
أي تعديل على config → يعيد بناء برومبت سند

التعديل ممكن يجي من:
  - لوحة التحكم (المشترك عدّل خدمة)
  - علي (المشترك قال "غيّر السعر")
  - كلهم يمرون على نفس الـ Action
  - كل Action يستدعي PromptBuilder::rebuild()
```

```php
// app/Services/PromptBuilder.php

class PromptBuilder
{
    public static function rebuild(string $businessId): void
    {
        $config = BusinessConfig::get($businessId);
        $buildPlan = BuildPlan::get($businessId);

        // 1. الهوية
        $identity = self::buildIdentity($config);

        // 2. النطاق — من الأنماط المفعّلة
        $scope = self::buildScope($buildPlan->active_patterns);

        // 3. الـ workflow — تركيب الأنماط + الطبقات
        $workflow = self::buildWorkflow(
            $buildPlan->active_patterns,
            $buildPlan->active_layers
        );

        // 4. القواعد — قطاعية + أنماط + طبقات + مخصصة
        $rules = self::buildRules(
            $config->sector,
            $buildPlan->active_patterns,
            $buildPlan->active_layers,
            $config->custom_rules
        );

        // 5. الأدوات — من ActionRegistry
        $tools = ActionRegistry::forAgent('snad', $businessId);

        // 6. التصعيد
        $escalation = self::buildEscalation($config);

        // 7. تجميع
        $prompt = self::assemble($identity, $scope, $workflow,
                                  $rules, $tools, $escalation);

        // 8. حفظ
        AgentPrompt::save($businessId, $prompt);

        // 9. سند يستخدم البرومبت الجديد فوراً
        // (لا restart — البرومبت يُقرأ مع كل رسالة جديدة)
    }
}
```

---

## سادساً: الإشعارات — تليجرام + لوحة التحكم

```php
// app/Services/NotificationService.php

class NotificationService
{
    /**
     * كل حدث يرسل إشعار لقناتين:
     * 1. لوحة التحكم (real-time عبر WebSocket)
     * 2. تليجرام (عبر Bot API)
     */

    private array $channels = ['dashboard', 'telegram'];

    private array $templates = [
        'new_booking' => [
            'title' => '📅 حجز جديد',
            'body' => '{customer_name} حجز {service_name} يوم {date} الساعة {time}',
            'priority' => 'normal',
        ],
        'new_order' => [
            'title' => '🛒 طلب جديد',
            'body' => '{customer_name} طلب {item_count} منتجات بقيمة {total} ريال',
            'priority' => 'normal',
        ],
        'escalation' => [
            'title' => '⚠️ تصعيد',
            'body' => 'الوكيل حوّل محادثة مع {customer_name}: {reason}',
            'priority' => 'high',
        ],
        'error_caught' => [
            'title' => '🔴 خطأ تم منعه',
            'body' => '{error_type}: {detail}',
            'priority' => 'high',
        ],
        'suggestion' => [
            'title' => '💡 اقتراح من علي',
            'body' => '{suggestion_text}',
            'priority' => 'low',
        ],
    ];
}
```

---

## سابعاً: خطة الاختبار — إجراء بإجراء

### لا تختبر مجال كامل — اختبر كل إجراء على حدة

```
المرحلة 1: الإجراءات الذرية (أصغر وحدة)
──────────────────────────────────────────
اختبر كل واحد بشكل مستقل:

□ create_service     → علي يضيف خدمة من المحادثة
                     → تظهر في لوحة التحكم
                     → سند يعرف عنها

□ update_price       → علي يغيّر سعر
                     → البرومبت يتحدث تلقائياً
                     → سند يذكر السعر الجديد

□ toggle_service     → علي يوقف خدمة
                     → سند يتجاهلها
                     → علي يفعّلها → سند يذكرها

□ create_booking     → سند يحجز موعد
                     → يظهر في لوحة التحكم
                     → إشعار تليجرام يوصل

□ cancel_booking     → سند يلغي موعد
                     → يتحدث في اللوحة
                     → إشعار يوصل

□ get_stats          → علي يعطي تقرير
                     → الأرقام تطابق اللوحة

المرحلة 2: التسلسلات (نمط كامل)
──────────────────────────────────
اختبر سلسلة إجراءات متتالية:

□ حجز_موعد الكامل:
  عميل يسأل → سند يسأل الخدمة → يسأل الوقت →
  يفحص التوفر → يحجز → يأكد → تذكير يوصل

□ طلب_منتج الكامل:
  عميل يطلب → سند يفحص المخزون → يحسب →
  يأكد → ينشئ الطلب → يسأل التوصيل → يتتبع

المرحلة 3: التكامل (علي + سند + لوحة)
───────────────────────────────────────
□ المالك يضيف خدمة من علي
  → سند يعرف عنها فوراً
  → تظهر في اللوحة
  → العميل يحجزها من سند

□ المالك يغيّر أوقات العمل من اللوحة
  → سند يرفض حجز خارجها
  → علي يعرف التغيير لو سألته

□ سند يصعّد محادثة
  → إشعار في اللوحة + تليجرام
  → المالك يرد من تليجرام أو اللوحة

المرحلة 4: المراقبة والأخطاء
──────────────────────────────
□ سند يذكر سعر خطأ → المدقق يمنعه
□ سند يخترع منتج → المدقق يمنعه
□ 5 أخطاء متتالية → إيقاف مؤقت + تنبيه المالك
□ عميل يصحح سند → يتسجل في التقرير
□ نمط أسئلة جديد → علي يقترح إضافته

المرحلة 5: مجال كامل (بعد نجاح 1-4)
──────────────────────────────────────
□ اختر مجال (مثلاً: مكتب عقار)
□ جرّب المحادثة الكاملة من الصفر
□ من "عندي مكتب عقار" إلى وكيل شغّال يخدم عملاء
```

---

## ثامناً: هيكل الملفات في Laravel

```
app/
├── Actions/                    ← كل إجراء = ملف واحد
│   ├── ActionRegistry.php      ← السجل — علي يقرأ منه
│   ├── Services/
│   │   ├── CreateService.php
│   │   ├── UpdateService.php
│   │   ├── ToggleService.php
│   │   └── DeleteService.php
│   ├── Bookings/
│   │   ├── CreateBooking.php
│   │   ├── CancelBooking.php
│   │   └── RescheduleBooking.php
│   ├── Orders/
│   │   ├── CreateOrder.php
│   │   ├── UpdateOrderStatus.php
│   │   └── CancelOrder.php
│   ├── Business/
│   │   ├── UpdateWorkingHours.php
│   │   ├── UpdatePolicies.php
│   │   └── UpdateConfig.php
│   └── Reports/
│       ├── GetDailyStats.php
│       └── GetConversationLog.php
│
├── Services/
│   ├── Ali/
│   │   ├── AliAgent.php              ← معالج رسائل المالك
│   │   ├── IntentClassifier.php      ← مصنّف نيات المالك
│   │   └── AliPromptBuilder.php      ← يبني أدوات علي من السجل
│   │
│   ├── Snad/
│   │   ├── SnadAgent.php             ← معالج رسائل العميل
│   │   ├── Gateway.php               ← الطبقة 1: قبل النموذج
│   │   ├── Validator.php             ← الطبقة 3: بعد النموذج
│   │   └── ValidatorRulesBuilder.php ← يبني قواعد التحقق من الأنماط
│   │
│   ├── PromptBuilder/
│   │   ├── PromptBuilder.php         ← المركّب الرئيسي
│   │   ├── PatternLoader.php         ← يحمّل الأنماط
│   │   ├── LayerLoader.php           ← يحمّل الطبقات
│   │   └── SectorRulesLoader.php     ← يحمّل القواعد القطاعية
│   │
│   ├── Monitoring/
│   │   ├── RiskPatterns.php          ← كشف الانحراف
│   │   ├── DailyHealthCheck.php      ← التقرير اليومي
│   │   └── AlertManager.php         ← إدارة التنبيهات
│   │
│   └── Notifications/
│       ├── NotificationService.php
│       ├── TelegramChannel.php
│       └── DashboardChannel.php
│
├── Models/
│   ├── Business.php
│   ├── Service.php
│   ├── Booking.php
│   ├── Order.php
│   ├── Customer.php
│   ├── Conversation.php
│   ├── AgentPrompt.php               ← البرومبت المحفوظ لكل نشاط
│   └── BuildPlan.php                 ← خطة البناء (أنماط + طبقات)
│
├── Patterns/                          ← ملفات YAML للأنماط
│   ├── booking.yaml
│   ├── product_order.yaml
│   ├── service_request.yaml
│   ├── client_qualification.yaml
│   ├── status_tracking.yaml
│   ├── catalog_browse.yaml
│   ├── recommend.yaml
│   ├── rental.yaml
│   └── custom_quote.yaml
│
├── Layers/                            ← ملفات YAML للطبقات
│   ├── delivery.yaml
│   ├── payment.yaml
│   ├── warranty.yaml
│   ├── reminder.yaml
│   ├── followup.yaml
│   ├── subscription.yaml
│   ├── documents.yaml
│   └── rating.yaml
│
└── SectorRules/                       ← ملفات YAML للقواعد
    ├── medical.yaml
    ├── legal.yaml
    ├── financial.yaml
    ├── real_estate.yaml
    ├── food.yaml
    ├── children.yaml
    ├── government.yaml
    ├── animals.yaml
    └── rental.yaml
```

---

## الخلاصة

```
┌──────────────────────────────────────────────────┐
│                                                  │
│  أنت تبني:                                       │
│    9 أنماط (YAML)                                │
│    8 طبقات (YAML)                                │
│    9 قواعد قطاعية (YAML)                         │
│    Actions + ActionRegistry (PHP)                │
│    PromptBuilder (PHP)                           │
│    Validator (PHP)                               │
│    Gateway (PHP)                                 │
│                                                  │
│  علي يقرأ الكود ويعرف وش يقدر يسوي              │
│  علي يركّب الأنماط ويبني الوكيل                   │
│  كل تعديل يعيد بناء البرومبت تلقائياً             │
│  ما تعدّل علي — تعدّل الكود وعلي يتكيّف          │
│                                                  │
│  85 نشاط × لا حد = من نفس المكونات              │
│                                                  │
└──────────────────────────────────────────────────┘
```
