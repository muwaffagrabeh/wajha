# الـ Schema الكامل — النسخة المعتمدة

---

## التسلسل الهرمي

```
مستخدم (User) ← يسجّل بالجوال + OTP
  └── نشاط (Business) ← نوع محدد من sector_types
        └── فرع (Branch) ← ممكن أكثر من واحد
              ├── خدمات ← كتالوج النشاط + تخصيص الفرع
              ├── مختصين
              ├── عملاء
              ├── حجوزات (مؤجل)
              ├── طلبات (مؤجل)
              └── محادثات
```

---

## الجداول المرجعية (Seed)

### 1. sectors — القطاعات الرئيسية

```
sectors
├── id              string PK     "health_beauty"
├── label           string        "صحة وتجميل"
├── label_en        string        "Health & Beauty"
├── icon            string        "💇‍♀️"
├── sort_order      int
├── status          active | inactive
└── created_at
```

### 2. sector_types — أنواع الأنشطة داخل كل قطاع

```
sector_types
├── id                string PK    "salon_women"
├── sector_id         FK → sectors "health_beauty"
├── label             string       "صالون نسائي"
├── label_en          string       "Women's Salon"
├── default_patterns  JSON         ["booking","catalog_browse"]
├── default_layers    JSON         ["reminder","rating"]
├── sector_rules      string       "medical"
├── sort_order        int
├── status            active | inactive
└── created_at
```

### 3. sector_schemas — الحقول المخصصة لكل قطاع

```
sector_schemas
├── id                ULID
├── sector_type_id    FK → sector_types
├── attribute_key     string        "rooms"
├── label             string        "عدد الغرف"
├── label_en          string null   "Rooms"
├── type              number | text | boolean | select | textarea
├── options           JSON null     ["1","2","3","4","5+"]
├── required          boolean
├── show_to_customer  boolean
├── filterable        boolean
├── sort_order        int
└── created_at
```

---

## جداول المصادقة

### 4. users — المستخدم

```
users
├── id                  ULID
├── phone               string unique    "05xxxxxxxx"
├── name                string null      ← يتعبى بعد أول دخول
├── email               string null
├── locale              ar | en  default ar
├── timezone             string default "Asia/Riyadh"
├── telegram_chat_id    string null
├── status              active | suspended
├── last_login_at       timestamp null
├── created_at
└── updated_at
```

### 5. otp_codes — رموز التحقق

```
otp_codes
├── id                  ULID
├── phone               string
├── code                string          "4829"
├── expires_at          timestamp
├── verified_at         timestamp null
├── attempts            int default 0   ← عدد المحاولات الخطأ
├── created_at
└── updated_at
```

---

## جداول الأعمال

### 6. businesses — النشاط التجاري

```
businesses
├── id                  ULID
├── user_id             FK → users
├── sector_type_id      FK → sector_types
├── name                string          "صالون لمسة"
├── name_en             string null     "Lamsa Salon"
├── logo                string null
├── description         text null
├── default_currency    string default "SAR"
├── active_patterns     JSON            ← ينسخ من sector_types.default_patterns ثم يتخصص
├── active_layers       JSON
├── custom_rules        JSON null       ← قواعد أضافها المالك
├── agent_name          string null     "مساعدة صالون لمسة"
├── agent_tone          string default "ودود ومهني"
├── agent_dialect       string default "saudi"
├── status              active | inactive
├── created_at
└── updated_at
```

### 7. branches — الفروع

```
branches
├── id                  ULID
├── business_id         FK → businesses
├── name                string          "فرع حي السلام"
├── city                string          "المدينة المنورة"
├── district            string null     "حي السلام"
├── address             text null
├── lat                 decimal(10,7) null
├── lng                 decimal(10,7) null
├── phone               string null
├── whatsapp            string null
├── working_hours       JSON
├── is_default          boolean default false
├── status              active | inactive
├── created_at
└── updated_at
```

### 8. service_items — المنتجات والخدمات

```
service_items
├── id                  ULID
├── business_id         FK → businesses
├── name                string           "قص شعر"
├── name_en             string null
├── type                service | product
├── category            string null      "شعر"
├── description         text null
├── price               decimal(10,2)    50.00
├── price_model         fixed | starting_from | per_unit | quote
├── price_unit          string null      "شهري" | "للمتر"
├── currency            string default "SAR"
├── duration_minutes    int null         30
├── requires_booking    boolean default false
├── requires_specialist boolean default false
├── deliverable         in_person | delivery | digital
├── stock_quantity      int null
├── media               JSON null
├── attributes          JSON null        ← حسب sector_schemas
├── tags                JSON null
├── status              active | inactive
├── sort_order          int default 0
├── created_at
└── updated_at
```

### 9. branch_service_overrides — تخصيص الفرع

```
branch_service_overrides
├── id                  ULID
├── branch_id           FK → branches
├── service_item_id     FK → service_items
├── price_override      decimal(10,2) null
├── available           boolean default true
├── stock_override      int null
├── created_at
└── updated_at

UNIQUE: (branch_id, service_item_id)
```

### 10. specialists — المختصين

```
specialists
├── id                  ULID
├── branch_id           FK → branches
├── name                string          "نورة"
├── role                string null     "مختصة شعر"
├── phone               string null
├── service_ids         JSON            ["srv_01","srv_02"]
├── working_hours       JSON
├── status              active | inactive
├── sort_order          int default 0
├── created_at
└── updated_at
```

### 11. customers — عملاء المشترك

```
customers
├── id                  ULID
├── business_id         FK → businesses
├── name                string
├── phone               string
├── email               string null
├── notes               text null
├── tags                JSON null
├── source              walk_in | whatsapp | website | agent | manual
├── first_contact_at    timestamp
├── last_contact_at     timestamp
├── total_bookings      int default 0
├── total_orders        int default 0
├── total_spent         decimal(10,2) default 0
├── created_at
└── updated_at

UNIQUE: (business_id, phone)
```

### 12. business_policies — سياسات النشاط

```
business_policies
├── id                  ULID
├── business_id         FK → businesses
├── key                 string          "cancellation"
├── value               text            "مسموح قبل ساعتين"
├── display_text        text null
├── created_at
└── updated_at

UNIQUE: (business_id, key)
```

---

## جداول الوكيل

### 13. agent_prompts — البرومبت المحفوظ

```
agent_prompts
├── id                  ULID
├── branch_id           FK → branches
├── prompt_text         longtext
├── tools_snapshot      JSON
├── validator_rules     JSON
├── gateway_routes      JSON
├── version             int
├── built_at            timestamp
├── built_by            ali | system | manual
└── created_at
```

### 14. conversations — المحادثات

```
conversations
├── id                  ULID
├── branch_id           FK → branches
├── customer_id         FK → customers null
├── channel             whatsapp | web_widget | telegram
├── agent_type          ali | snad
├── status              active | resolved | escalated | abandoned
├── escalation_reason   text null
├── started_at          timestamp
├── resolved_at         timestamp null
├── satisfaction_rating int null
└── created_at
```

### 15. messages — رسائل المحادثات

```
messages
├── id                  ULID
├── conversation_id     FK → conversations
├── role                customer | agent | owner | system
├── content             text
├── raw_output          JSON null
├── intent              string null
├── action_taken        string null
├── confidence          string null     high | medium | low
├── validation_result   JSON null
├── was_blocked         boolean default false
├── block_reason        text null
├── tokens_used         int null
├── response_ms         int null
├── created_at
└── updated_at
```

---

## جداول المراقبة

### 16. action_logs — سجل الإجراءات

```
action_logs
├── id                  ULID
├── business_id         FK → businesses
├── branch_id           FK → branches null
├── action_name         string          "create_service"
├── triggered_by        dashboard | ali | snad | system
├── input_data          JSON
├── output_data         JSON
├── success             boolean
├── error_message       text null
├── triggered_at        timestamp
└── created_at
```

### 17. alerts — التنبيهات

```
alerts
├── id                  ULID
├── business_id         FK → businesses
├── branch_id           FK → branches null
├── type                error_caught | escalation | suggestion | drift_detected | low_confidence
├── severity            critical | high | medium | low
├── title               string
├── message             text
├── related_conversation_id  FK → conversations null
├── acknowledged        boolean default false
├── acknowledged_at     timestamp null
├── created_at
└── updated_at
```

### 18. notification_preferences — تفضيلات الإشعارات

```
notification_preferences
├── id                  ULID
├── user_id             FK → users
├── event_type          string          "new_booking"
├── dashboard           boolean default true
├── telegram            boolean default true
├── created_at
└── updated_at
```

---

## ملخص الجداول

```
مرجعية (3):   sectors, sector_types, sector_schemas
مصادقة (2):   users, otp_codes
أعمال (7):    businesses, branches, service_items,
              branch_service_overrides, specialists,
              customers, business_policies
وكيل (3):     agent_prompts, conversations, messages
مراقبة (3):   action_logs, alerts, notification_preferences
───────────────────────────────────────
المجموع:      18 جدول

مؤجلة (5):    bookings, orders, payments,
              subscriptions, landing_pages
```

---

## ترتيب الـ Migrations

```
الدفعة 1 — أساس (ما يعتمد على شي):
  01 → create_sectors_table
  02 → create_sector_types_table
  03 → create_sector_schemas_table
  04 → create_users_table
  05 → create_otp_codes_table

الدفعة 2 — أعمال (يعتمد على users + sector_types):
  06 → create_businesses_table
  07 → create_branches_table
  08 → create_service_items_table
  09 → create_branch_service_overrides_table
  10 → create_specialists_table
  11 → create_customers_table
  12 → create_business_policies_table

الدفعة 3 — وكيل ومراقبة:
  13 → create_agent_prompts_table
  14 → create_conversations_table
  15 → create_messages_table
  16 → create_action_logs_table
  17 → create_alerts_table
  18 → create_notification_preferences_table
```
