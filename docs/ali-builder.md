# كيف يبني علي وكيل لأي مجال

---

## المبدأ

علي ما يكتب كود ولا برومبت من الصفر.
علي عنده **مستودع مكونات** — يختار منه ويركّب.

```
مكونات جاهزة + بيانات المالك = وكيل شغّال
```

---

## المستودع: 3 أنواع مكونات

### 1. الأنماط (Patterns) — الـ workflow

كل نمط = سلسلة خطوات جاهزة + أسئلة + ردود + تحققات

```yaml
patterns:

  booking:
    label: "حجز موعد"
    steps:
      - ask: "أي خدمة؟"
        source: config.services
        validate: must_exist_in_services
      - ask: "أي يوم ووقت؟"
        validate: must_be_within_working_hours
      - ask: "تفضل مختص معين؟"
        source: config.specialists
        optional: true
      - action: check_availability
        on_success: confirm_booking
        on_fail: suggest_alternatives
      - action: send_confirmation
    validator_rules:
      - no_booking_outside_hours
      - no_double_booking
      - specialist_must_exist

  product_order:
    label: "طلب منتج"
    steps:
      - ask: "وش تبي تطلب؟"
        source: config.products
        validate: must_exist_in_catalog
      - ask: "الكمية؟"
        validate: must_be_positive_number
      - action: check_stock
        on_fail: suggest_similar_or_notify
      - action: calculate_total
        show: itemized_summary
      - ask: "تأكيد الطلب؟"
      - action: create_order
    validator_rules:
      - price_must_match_catalog
      - product_must_be_in_stock
      - quantity_must_be_reasonable

  service_request:
    label: "طلب خدمة"
    steps:
      - ask: "وش المطلوب؟"
        source: config.services
      - ask: "الموقع؟"
        validate: must_be_in_coverage
      - ask: "وقت مناسب للمعاينة؟"
      - action: schedule_visit
      - action: send_confirmation
    validator_rules:
      - location_must_be_in_coverage
      - service_must_exist

  client_qualification:
    label: "تأهيل عميل"
    steps:
      - ask: "وش تحتاج بالضبط؟"
        classify: intent_category
      - ask: dynamic_followup_based_on_category
      - action: create_lead_profile
      - action: assign_to_specialist
      - action: book_consultation
    validator_rules:
      - never_give_professional_advice
      - never_promise_outcome

  status_tracking:
    label: "تتبع حالة"
    steps:
      - ask: "رقم الطلب/الشحنة/المعاملة؟"
        validate: must_exist_in_system
      - action: fetch_status
      - action: format_and_respond
    validator_rules:
      - status_must_come_from_db
      - never_estimate_delivery_without_data

  catalog_browse:
    label: "عرض كتالوج"
    steps:
      - ask: "وش تدوّر عليه؟"
        extract: search_criteria
      - action: search_catalog
        filters: from_criteria
        sort: by_relevance
      - action: show_top_results
        max: 3
      - ask: "تبي تفاصيل أكثر عن أي واحد؟"
    validator_rules:
      - only_show_active_items
      - prices_must_match_db
      - images_must_match_item

  recommend:
    label: "عرض وتوصية"
    steps:
      - ask: "وش معاييرك؟" (ميزانية، موقع، حجم...)
        extract: preferences
      - action: match_and_rank
        source: config.catalog
        algorithm: weighted_match
      - action: show_recommendations
        max: 3
        format: comparison_card
    validator_rules:
      - only_recommend_available_items
      - never_fabricate_features
```

### 2. الطبقات (Layers) — إضافات اختيارية

```yaml
layers:

  delivery:
    fields_needed: [delivery_zones, delivery_fee, estimated_time]
    adds_to_workflow:
      - after: order_confirmed
        ask: "وين التوصيل؟"
        validate: must_be_in_zones
      - action: calculate_delivery_fee
      - action: track_delivery
    validator_rules:
      - delivery_zone_must_exist
      - fee_must_match_config

  payment:
    fields_needed: [payment_methods, payment_gateway]
    adds_to_workflow:
      - after: total_calculated
        ask: "طريقة الدفع؟"
        options: from_config.payment_methods
      - action: process_payment_or_send_link
    validator_rules:
      - amount_must_match_order

  warranty:
    fields_needed: [warranty_period, warranty_terms]
    adds_to_workflow:
      - after: sale_completed
        action: send_warranty_info
      - on_trigger: "warranty_claim"
        action: verify_warranty → create_ticket
    validator_rules:
      - warranty_dates_must_be_accurate

  reminder:
    fields_needed: [reminder_before_minutes]
    adds_to_workflow:
      - after: booking_confirmed
        action: schedule_reminder
      - cron: check_upcoming_bookings
        action: send_reminder
    validator_rules: []

  followup:
    fields_needed: [followup_after_hours, followup_message]
    adds_to_workflow:
      - after: service_completed
        wait: config.followup_after_hours
        action: send_followup_message
    validator_rules: []
```

### 3. القواعد القطاعية (Sector Rules) — قيود خاصة

```yaml
sector_rules:

  medical:
    critical:
      - "لا تشخّص"
      - "لا تصف علاج"
      - "لا تعطي رأي طبي"
      - "أي سؤال طبي → 'يجب استشارة الطبيب'"
    tone: "مهني ومطمئن"
    sensitive_data: true

  legal:
    critical:
      - "لا تعطي استشارة قانونية"
      - "لا تفسّر نظام أو لائحة"
      - "أي سؤال قانوني → تحويل للمحامي"
    tone: "رسمي ودقيق"
    sensitive_data: true

  real_estate:
    critical:
      - "لا تخترع عقارات"
      - "لا تعدّل سعر بدون تصريح"
      - "لا تعطي رأي استثماري"
    tone: "مهني ومباشر"

  food:
    critical:
      - "لا تعدّل طلب بعد تأكيده بدون موافقة"
      - "اذكر التحذيرات الغذائية إن وجدت"
    tone: "ودود وسريع"

  children:  # حضانة، مدرسة
    critical:
      - "لا تتواصل مع الطفل مباشرة"
      - "كل التواصل مع ولي الأمر"
      - "لا تشارك بيانات طفل مع غير ولي أمره"
    tone: "دافئ ومطمئن"
    sensitive_data: true
```

---

## محرك البناء: كيف يركّب علي الوكيل

### الخطوة 1: الفهم

```python
def understand_business(owner_message):
    """
    علي يفهم النشاط من كلام المالك
    المخرج: ليس تصنيف — بل قرارات بناء
    """
    return {
        "business_name": "معرض الجوهرة",
        "sector": "real_estate",           # يحدد sector_rules
        "patterns": [                       # يحدد الـ workflow
            "catalog_browse",
            "recommend",
            "client_qualification",
            "booking"
        ],
        "layers": [                         # يحدد الإضافات
            "followup"
        ],
        "excluded_layers": [
            "delivery",    # عقار ما يتوصّل
            "payment"      # الدفع خارج الوكيل
        ]
    }
```

### الخطوة 2: جمع البيانات المطلوبة

```python
def calculate_required_fields(build_plan):
    """
    بناءً على الأنماط والطبقات المختارة،
    حدد وش البيانات اللي تحتاجها من المالك
    """
    required = set()

    for pattern_name in build_plan["patterns"]:
        pattern = PATTERNS[pattern_name]
        for step in pattern["steps"]:
            if step.get("source"):
                required.add(step["source"])
                # مثال: config.services, config.products

    for layer_name in build_plan["layers"]:
        layer = LAYERS[layer_name]
        for field in layer["fields_needed"]:
            required.add(field)

    # شيل اللي عنده قيم افتراضية
    # خل بس اللي لازم يجي من المالك
    return filter_mandatory(required)
```

**مثال المخرج:**
```
مطلوب من المالك:
  ✗ قائمة العقارات (أو رابط الكتالوج)
  ✗ مناطق التغطية
  ✗ أوقات العمل
  ✗ أسماء الوسطاء
  ✓ سياسة الإلغاء (افتراضي: مسموح قبل 24 ساعة)
  ✓ رسالة المتابعة (افتراضي: "شكراً لتواصلك، هل تحتاج شي ثاني؟")
```

علي يسأل المالك **فقط** عن اللي ما عنده افتراضي.

### الخطوة 3: بناء الـ Config

```python
def build_config(build_plan, owner_data):
    return {
        "business": {
            "name": owner_data["name"],
            "sector": build_plan["sector"],
            "location": owner_data["location"],
            "working_hours": owner_data["hours"]
        },
        "services": owner_data["services"],  # أو products
        "specialists": owner_data.get("specialists", []),
        "policies": {
            **DEFAULT_POLICIES[build_plan["sector"]],
            **owner_data.get("custom_policies", {})
        },
        "active_patterns": build_plan["patterns"],
        "active_layers": build_plan["layers"]
    }
```

### الخطوة 4: تركيب البرومبت

```python
def build_prompt(config, build_plan):
    prompt = PROMPT_TEMPLATE  # الهيكل الثابت

    # 1. الهوية
    prompt.identity = {
        "name": generate_agent_name(config),
        "role": generate_role_description(config),
        "tone": SECTOR_RULES[config["sector"]]["tone"],
        "language": "ar-SA"
    }

    # 2. النطاق — من الأنماط المختارة
    prompt.scope.does = []
    for p in build_plan["patterns"]:
        prompt.scope.does += PATTERNS[p]["user_facing_capabilities"]

    prompt.scope.does_not = SECTOR_RULES[config["sector"]]["critical"]

    # 3. الـ workflow — تسلسل الأنماط
    prompt.workflow = []
    for p in build_plan["patterns"]:
        prompt.workflow += PATTERNS[p]["steps"]

    # 4. إدراج الطبقات في أماكنها الصحيحة
    for l in build_plan["layers"]:
        layer = LAYERS[l]
        for insertion in layer["adds_to_workflow"]:
            prompt.workflow.insert_after(
                insertion["after"],
                insertion
            )

    # 5. القواعد
    prompt.rules = []
    # قواعد القطاع (حرجة)
    prompt.rules += SECTOR_RULES[config["sector"]]["critical"]
    # قواعد الأنماط
    for p in build_plan["patterns"]:
        prompt.rules += PATTERNS[p]["validator_rules"]
    # قواعد الطبقات
    for l in build_plan["layers"]:
        prompt.rules += LAYERS[l]["validator_rules"]

    # 6. التصعيد
    prompt.escalation = generate_escalation(config, build_plan)

    return prompt
```

### الخطوة 5: بناء المدقق

```python
def build_validator(config, build_plan):
    """
    المدقق يتبنى تلقائياً من نفس الأنماط والطبقات
    """
    checks = []

    for p in build_plan["patterns"]:
        for rule in PATTERNS[p]["validator_rules"]:
            checks.append(
                create_check_function(rule, config)
            )

    for l in build_plan["layers"]:
        for rule in LAYERS[l]["validator_rules"]:
            checks.append(
                create_check_function(rule, config)
            )

    # قواعد القطاع → تتحول لفحوصات نصية
    for rule_text in SECTOR_RULES[config["sector"]]["critical"]:
        checks.append(
            create_text_check(rule_text)
            # مثال: "لا تشخّص" → فحص وجود مصطلحات طبية تشخيصية
        )

    return Validator(checks)
```

---

## المخرج النهائي: وكيل كامل

```
علي يسلّم 4 أشياء:
│
├── 1. config.json         ← بيانات النشاط
├── 2. prompt.xml          ← برومبت الوكيل
├── 3. validator.rules     ← قواعد التحقق
└── 4. gateway.routes      ← أي أسئلة ما تحتاج نموذج
```

الأربعة تتولّد **تلقائياً** من نفس الأنماط والطبقات.

---

## مثال كامل: من "عندي مصبغة" إلى وكيل

```
المالك: عندي مصبغة ملابس

┌─ علي يفهم ──────────────────────────┐
│  sector: personal_services            │
│  patterns: [service_request,          │
│             status_tracking]          │
│  layers: [delivery, payment]          │
│  sector_rules: (لا قواعد حرجة خاصة)  │
└──────────────────────────────────────┘

علي: تمام — مصبغة ملابس. أحتاج:
     1. اسم المصبغة
     2. وش الخدمات؟ (غسيل عادي، كوي، تنظيف جاف...؟)
     3. عندكم توصيل واستلام من البيت؟

المالك: مصبغة النظافة. غسيل وكوي وتنظيف جاف. إي عندنا توصيل.

┌─ علي يبني ───────────────────────────┐
│                                       │
│  config:                              │
│    services: [غسيل, كوي, تنظيف جاف]   │
│    delivery: enabled                  │
│                                       │
│  prompt (سند):                        │
│    step 1: وش تبي تغسل؟              │
│    step 2: كم قطعة؟                   │
│    step 3: استلام من عندك ولا توصله؟   │
│    step 4: العنوان                     │
│    step 5: تأكيد + موعد الاستلام       │
│                                       │
│  validator:                           │
│    ✓ الخدمة موجودة في config           │
│    ✓ العنوان ضمن مناطق التغطية        │
│    ✓ السعر مطابق                      │
│                                       │
│  gateway:                             │
│    "كم سعر الغسيل" → رد من DB مباشرة  │
│    "وين طلبي" → استعلام من DB مباشرة  │
│    "أوقاتكم" → رد ثابت                │
│                                       │
└───────────────────────────────────────┘

علي: ✓ الوكيل جاهز. بس أعطني الأسعار ومناطق التوصيل وأشغّله.
```

---

## التحديث: كيف يضيف المالك مجال ثاني

```
المالك: فتحت فرع ثاني — خياط رجالي

علي ما يبني من الصفر.
علي:
  1. يضيف config جديد (فرع ثاني)
  2. يختار أنماط مختلفة:
     patterns: [booking, product_order]  ← تفصيل + جاهز
     layers: [payment]
  3. يولّد prompt + validator + gateway
  4. نفس لوحة المراقبة — فرعين تحت مالك واحد

المالك يدير الفرعين من نفس المحادثة مع علي.
```

---

## ملخص: ليش هالطريقة تشتغل

```
┌─────────────────────────────────────────────┐
│  الطريقة التقليدية:                          │
│  100 مجال × برومبت مخصص = 100 برومبت         │
│  كل واحد يحتاج صيانة مستقلة                  │
│  خطأ في واحد ما ينحل في الباقي               │
│                                             │
│  طريقة علي:                                  │
│  7 أنماط + 5 طبقات + 6 قواعد قطاعية          │
│  = أي مجال ممكن                              │
│  تحسّن نمط واحد → كل المجالات تستفيد          │
│  تضيف طبقة جديدة → كل المجالات تقدر تفعّلها  │
└─────────────────────────────────────────────┘
```
