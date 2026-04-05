# هيكل المراقبة والحماية — علي كمشرف تشغيلي

---

## المشكلة الحقيقية

الوكيل (سند) يشتغل على نموذج لغوي.
النموذج اللغوي بطبيعته:
- يولّد نص مقنع حتى لو المعلومة خطأ
- يبرر أخطاءه بدل ما يعترف فيها
- ما يعرف الفرق بين "أنا متأكد" و "أنا أخمّن"

البرومبت وحده **لا يحل هذي المشكلة** — لأن البرومبت توجيه مو قيد تقني.

الحل: طبقات حماية **خارج النموذج** — كود فعلي يتحقق قبل وبعد كل رد.

---

## الهيكل: 3 طبقات حماية

```
رسالة العميل
     │
     ▼
┌─────────────────────────┐
│  الطبقة 1: البوابة       │  ← قبل ما يوصل للنموذج
│  (Gateway)               │
└────────────┬────────────┘
             ▼
┌─────────────────────────┐
│  الطبقة 2: النموذج       │  ← سند يعالج ويرد
│  (Agent)                 │
└────────────┬────────────┘
             ▼
┌─────────────────────────┐
│  الطبقة 3: المدقق       │  ← قبل ما يوصل للعميل
│  (Validator)             │
└────────────┬────────────┘
             ▼
     رد للعميل (أو حجب + تصعيد)
```

---

## الطبقة 1: البوابة (Gateway) — قبل النموذج

هذي كود، مو برومبت. تشتغل قبل ما الرسالة توصل لسند.

### 1.1 تصنيف المدخل

```python
def classify_input(message):
    """
    تصنيف رسالة العميل قبل إرسالها للنموذج
    """
    input_type = detect_intent(message)

    # رسائل ما تحتاج نموذج أصلاً
    if input_type == "track_order":
        return handle_tracking(message)  # استعلام مباشر من DB

    if input_type == "business_hours":
        return static_response("أوقات العمل: ...")  # رد ثابت

    if input_type == "price_check":
        return lookup_price(message)  # بحث مباشر في الكتالوج

    # بس هذي تروح للنموذج
    if input_type in ["complex_query", "negotiation", "complaint"]:
        return send_to_agent(message)
```

**الفايدة:** 60-70% من الرسائل ما تحتاج نموذج لغوي أصلاً — رد ثابت أو استعلام من قاعدة البيانات. كل ما قلّلت اعتمادك على النموذج، قلّت الأخطاء.

### 1.2 إثراء السياق

```python
def enrich_context(message, customer_id):
    """
    قبل ما ترسل للنموذج، أعطه فقط البيانات الصحيحة
    """
    context = {
        "available_properties": db.get_active_listings(),
        "customer_history": db.get_customer(customer_id),
        "current_time": now(),
        "business_config": config.get_active()
    }
    return context
```

**الفايدة:** النموذج ما يختلق بيانات لأن البيانات الصحيحة موجودة قدامه.

---

## الطبقة 2: النموذج (Agent) — قواعد محكمة

### 2.1 فصل الرد عن البيانات

بدل ما تقول للنموذج "رد على العميل"، قسّم الطلب:

```xml
<instruction>
  أنت لا تكتب رد نهائي — أنت تملأ هيكل محدد.

  <output_schema>
    <intent>نية العميل كما فهمتها</intent>
    <action>الإجراء المطلوب: respond | book | escalate | clarify</action>
    <data_used>أي بيانات من السياق استخدمت — بالـ id</data_used>
    <response_text>نص الرد للعميل</response_text>
    <confidence>high | medium | low</confidence>
    <confidence_reason>لماذا هذا المستوى</confidence_reason>
  </output_schema>

  <rules>
    - إذا السؤال عن عقار: استخدم فقط العقارات الموجودة في available_properties
    - إذا ما لقيت عقار مطابق: قول "ما لقيت" — لا تخترع
    - إذا ما فهمت السؤال: action=clarify — لا تخمّن
    - confidence=low يعني الرد ما يتأرسل تلقائياً
  </rules>
</instruction>
```

**الفايدة:** النموذج يرجع هيكل منظم مو نص حر — تقدر تتحقق منه برمجياً.

### 2.2 منع التبرير

```xml
<rule priority="critical">
  ممنوع استخدام أي من هذي العبارات:
  - "أعتذر عن الخطأ ولكن..."
  - "قد يكون..."
  - "من الممكن أن..."
  - "أعتقد أن..."
  - "بناءً على تقديري..."

  البديل:
  - إذا المعلومة موجودة في السياق ← اذكرها مع الـ id
  - إذا المعلومة غير موجودة ← "ما عندي هالمعلومة، خلني أحولك"
  - لا يوجد منطقة رمادية
</rule>
```

---

## الطبقة 3: المدقق (Validator) — بعد النموذج، قبل العميل

هذي الطبقة الأهم. كود يفحص رد النموذج قبل إرساله.

### 3.1 التحقق من البيانات

```python
def validate_response(agent_output, context):
    errors = []

    # 1. هل ذكر عقار؟ تأكد إنه موجود فعلاً
    mentioned_ids = extract_property_ids(agent_output.response_text)
    for pid in mentioned_ids:
        if pid not in context["available_properties"]:
            errors.append({
                "type": "hallucinated_property",
                "severity": "critical",
                "detail": f"ذكر عقار {pid} غير موجود"
            })

    # 2. هل ذكر سعر؟ تأكد إنه صحيح
    mentioned_prices = extract_prices(agent_output.response_text)
    for price in mentioned_prices:
        actual = db.get_price(price["property_id"])
        if actual and abs(price["amount"] - actual) > 0:
            errors.append({
                "type": "wrong_price",
                "severity": "critical",
                "detail": f"سعر خطأ: قال {price['amount']} والصحيح {actual}"
            })

    # 3. هل ذكر موقع أو حي؟ تأكد
    mentioned_locations = extract_locations(agent_output.response_text)
    for loc in mentioned_locations:
        if not db.verify_location(loc["property_id"], loc["text"]):
            errors.append({
                "type": "wrong_location",
                "severity": "critical",
                "detail": f"موقع خطأ للعقار"
            })

    # 4. هل الثقة منخفضة؟
    if agent_output.confidence == "low":
        errors.append({
            "type": "low_confidence",
            "severity": "warning",
            "detail": agent_output.confidence_reason
        })

    return errors
```

### 3.2 القرار بناءً على التحقق

```python
def decide(agent_output, errors):

    critical = [e for e in errors if e["severity"] == "critical"]
    warnings = [e for e in errors if e["severity"] == "warning"]

    # لا أخطاء حرجة → أرسل
    if not critical and not warnings:
        return send_to_customer(agent_output.response_text)

    # تحذيرات بس → أرسل + سجّل للمراجعة
    if not critical and warnings:
        log_for_review(agent_output, warnings)
        return send_to_customer(agent_output.response_text)

    # خطأ حرج → لا ترسل
    if critical:
        log_critical(agent_output, critical)
        # حاول مرة ثانية مع تصحيح
        retry = retry_with_correction(agent_output, critical)
        if retry.is_clean:
            return send_to_customer(retry.response_text)
        else:
            # فشل مرتين → تصعيد
            return escalate_to_human(agent_output, critical)
```

### 3.3 أنواع الأخطاء ورد الفعل

```
┌────────────────────┬────────────┬─────────────────────────┐
│ نوع الخطأ          │ الشدة      │ رد الفعل                │
├────────────────────┼────────────┼─────────────────────────┤
│ عقار غير موجود     │ critical   │ حجب + إعادة محاولة       │
│ سعر خطأ            │ critical   │ حجب + تصحيح تلقائي       │
│ موقع خطأ           │ critical   │ حجب + إعادة محاولة       │
│ وعد غير مصرّح      │ critical   │ حجب + تصعيد             │
│ رد خارج النطاق     │ high       │ حجب + رد بديل ثابت      │
│ لهجة غير مناسبة    │ medium     │ إرسال + تسجيل           │
│ رد طويل            │ low        │ إرسال + تسجيل           │
│ confidence = low   │ warning    │ إرسال + مراجعة لاحقة    │
└────────────────────┴────────────┴─────────────────────────┘
```

---

## المراقبة الاستباقية — قبل ما يصير الخطأ

### 4.1 أنماط الخطر

```python
class RiskPatterns:

    def check_drift(self, last_n_responses):
        """
        هل الوكيل بدأ ينحرف عن السلوك المتوقع؟
        """
        drift_signals = {
            # الرد صار أطول من المعتاد
            "verbose": avg_length(last_n) > config.max_avg_length * 1.5,

            # نسبة التصعيد ارتفعت
            "escalation_spike": escalation_rate(last_n) > 0.3,

            # الثقة المنخفضة صارت كثيرة
            "confidence_drop": low_confidence_rate(last_n) > 0.2,

            # نفس السؤال يتكرر (العميل ما فهم الرد)
            "repeat_questions": repeat_rate(last_n) > 0.25,

            # العميل يصحح الوكيل
            "customer_corrections": correction_rate(last_n) > 0.1,
        }
        return drift_signals

    def daily_health_check(self):
        """
        تقرير يومي لعلي
        """
        return {
            "total_conversations": count_today(),
            "auto_resolved": auto_resolved_rate(),
            "escalated": escalated_rate(),
            "errors_caught": errors_caught_count(),
            "errors_missed": errors_reported_by_customers(),
            "avg_confidence": avg_confidence_score(),
            "top_unhandled_intents": get_unmatched_intents(top=5),
            "risk_score": calculate_risk_score()
        }
```

### 4.2 تنبيهات تلقائية لعلي → المالك

```python
ALERT_RULES = [
    {
        "condition": "errors_caught > 10 in 1 hour",
        "action": "pause_agent",
        "notify": "owner",
        "message": "⚠️ الوكيل يواجه مشاكل متكررة — تم إيقافه مؤقتاً"
    },
    {
        "condition": "customer_corrections > 3 in 1 hour",
        "action": "flag_for_review",
        "notify": "owner",
        "message": "⚠️ عملاء يصححون ردود الوكيل — يحتاج مراجعة"
    },
    {
        "condition": "new_intent_unhandled > 5 in 1 day",
        "action": "suggest_update",
        "notify": "owner",
        "message": "💡 فيه أسئلة جديدة الوكيل ما يعرف يرد عليها — تبي أضيفها؟"
    },
    {
        "condition": "confidence_avg < 0.6 for 1 day",
        "action": "alert",
        "notify": "owner",
        "message": "📉 مستوى ثقة الوكيل منخفض — غالباً يحتاج تحديث بيانات"
    }
]
```

---

## 5. سجل المحادثات — كيف يتعلم النظام

كل محادثة تتسجل بهالهيكل:

```json
{
  "conversation_id": "conv_20260405_001",
  "customer_id": "cust_123",
  "messages": [
    {
      "role": "customer",
      "text": "عندكم شقة بالعزيزية 3 غرف؟",
      "timestamp": "2026-04-05T10:00:00"
    },
    {
      "role": "agent",
      "raw_output": {
        "intent": "property_search",
        "action": "respond",
        "data_used": ["prop_044", "prop_067"],
        "response_text": "...",
        "confidence": "high"
      },
      "validation": {
        "passed": true,
        "errors": [],
        "checks_run": ["property_exists", "price_correct", "location_correct"]
      },
      "sent_to_customer": true,
      "timestamp": "2026-04-05T10:00:02"
    }
  ],
  "outcome": "resolved | escalated | abandoned",
  "customer_satisfaction": null,
  "review_status": "auto_approved | pending_review | reviewed_ok | reviewed_issue"
}
```

---

## 6. لوحة علي — ما يشوفه المالك

```
┌─────────────────────────────────────────────────┐
│  📊 اليوم — 5 أبريل 2026                        │
├─────────────────────────────────────────────────┤
│                                                 │
│  محادثات: 47        حُلّت تلقائياً: 38 (81%)     │
│  تصعيد: 6           أخطاء مُنعت: 3              │
│                                                 │
│  ⚡ أكثر الأسئلة:                                │
│  1. استفسار أسعار (18)                          │
│  2. حجز معاينة (12)                             │
│  3. موقع العقار (8)                             │
│                                                 │
│  ⚠️ تنبيهات:                                    │
│  - 3 عملاء سألوا عن "تمويل" وما عندك رد جاهز   │
│  - عقار prop_044 انسأل عنه 7 مرات (طلب عالي)    │
│                                                 │
│  🔴 أخطاء مُنعت اليوم:                          │
│  - 10:23 ذكر سعر خطأ لعقار prop_012 ← تم        │
│    التصحيح تلقائياً                              │
│  - 14:45 وعد عميل بخصم ← تم الحجب والتصعيد      │
│  - 16:02 ذكر عقار محذوف ← تم الحجب وإعادة الرد  │
│                                                 │
│  💡 اقتراحات:                                   │
│  - ضيف رد جاهز لسؤال "هل تدعمون تمويل؟"        │
│  - حدّث سعر prop_067 (آخر تحديث قبل 30 يوم)     │
│                                                 │
└─────────────────────────────────────────────────┘
```

---

## 7. الخلاصة المعمارية

```
لا تثق بالنموذج → تحقق منه

الثقة بالنموذج اللغوي = 0%
الثقة بالكود + البيانات = 100%

كل معلومة في رد الوكيل لازم تكون:
  ✓ موجودة في قاعدة البيانات
  ✓ متحقق منها برمجياً
  ✓ مسجّلة بالـ id المرجعي

أي شي ثاني = مرفوض قبل ما يوصل للعميل
```

**القاعدة الذهبية:**

```
النموذج يصيغ — الكود يتحقق — البيانات هي المرجع الوحيد
```
