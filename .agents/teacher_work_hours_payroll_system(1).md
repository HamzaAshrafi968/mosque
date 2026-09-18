# نظام إدارة ساعات عمل المدرسين والرواتب

## 1. الهدف

بناء نظام مركزي لإدارة ساعات عمل المدرسين، بحيث يتم تسجيل العمل على شكل **فترات زمنية (Time Slots)** من وقت بداية إلى وقت نهاية، ثم تجميعها تلقائياً على مستويات:

**Slot → Day → Week → Month → Payroll → Payment**

النظام يجب أن يحافظ على السجل التاريخي ولا يقوم بحذف أو تصفير بيانات الأشهر السابقة.

---

# 2. إعادة تقييم المتطلبات

بعد تحليل الفكرة، أفضل تصميم للنظام هو فصل 4 مفاهيم عن بعضها:

1. **سجل العمل الفعلي**: متى عمل المدرس بالضبط.
2. **سعر الساعة**: السعر المطبق على العمل في فترة زمنية محددة.
3. **كشف الراتب الشهري (Payroll)**: نتيجة تجميع العمل والسعر.
4. **عملية الدفع (Payment)**: تسجيل أن الراتب تم دفعه فعلياً.

هذا الفصل مهم حتى لا يؤدي تعديل إعداد أو دفع راتب إلى تغيير التاريخ المالي السابق.

---

# 3. مفهوم Time Slot

الـ Slot هو الوحدة الأساسية للنظام.

مثال:

الأحد:

- 06:00 → 08:00 = ساعتان
- 14:00 → 20:00 = 6 ساعات

إجمالي اليوم = **8 ساعات**

لا يتم إدخال "8 ساعات" كبيان أساسي؛ بل يتم حسابها من أوقات البداية والنهاية.

## مثال

```text
Date: 2026-09-20
Teacher: أحمد

Slot 1:
Start: 06:00
End:   08:00
Duration: 120 minutes

Slot 2:
Start: 14:00
End:   20:00
Duration: 360 minutes

Daily Total: 480 minutes = 8 hours
```

### لماذا؟

لأن النظام يستطيع لاحقاً عرض:

- متى بدأ العمل
- متى انتهى
- عدد الفترات
- مجموع ساعات اليوم
- مجموع ساعات الأسبوع
- مجموع ساعات الشهر

---

# 4. التسلسل الهرمي للبيانات

```text
Teacher
   │
   └── Work Day
          │
          ├── Slot
          ├── Slot
          └── Slot
                │
                ▼
         Daily Total
                │
                ▼
          Weekly Total
                │
                ▼
          Monthly Total
                │
                ▼
            Payroll
                │
                ▼
            Payment
```

العرض في الواجهة:

```text
Month
 └── Week
      └── Day
           └── Time Slots
```

---

# 5. مثال واقعي

## الأحد 20/09/2026

```text
06:00 ───────── 08:00
      2 ساعات

14:00 ─────────────────────── 20:00
              6 ساعات

الإجمالي: 8 ساعات
```

إذا كان سعر الساعة €20:

```text
8 × €20 = €160
```

لكن الراتب الشهري لا يعتمد على هذا اليوم وحده، بل على مجموع كل Slots خلال الشهر.

---

# 6. الصفحات الرئيسية

## 6.1 Dashboard

يقدم ملخصاً سريعاً:

- عدد المدرسين النشطين
- ساعات العمل اليوم
- ساعات العمل هذا الأسبوع
- ساعات العمل هذا الشهر
- الرواتب المستحقة
- الرواتب المدفوعة
- الرواتب المتبقية
- حالة الشهر الحالي

مثال:

```text
Dashboard

Teachers: 12
Hours This Month: 384h
Payroll: €7,680
Paid: €5,200
Remaining: €2,480
```

---

# 7. صفحة المدرسين

```text
Teachers

[ + Add Teacher ]

Teacher        Hourly Rate    This Month    Salary     Status
----------------------------------------------------------------
Ahmed          €20             84h 30m       €1,690     Active
Mohamed        €18             72h           €1,296     Active
Sara           €22             91h           €2,002     Active
```

يفضل توفير:

- Search
- Filter by status
- Filter by branch إن تمت إضافة الفروع لاحقاً
- Sort
- فتح ملف المدرس

---

# 8. ملف المدرس

يجب أن يكون الملف مركز المعلومات الخاص بالمدرس.

## المعلومات الشخصية

- الاسم
- الصورة
- الهاتف
- البريد الإلكتروني
- تاريخ الانضمام
- الحالة
- سعر الساعة الحالي

## ملخص الشهر

```text
September 2026

Total Hours: 84h 30m
Hourly Rate: €20
Gross Salary: €1,690
Paid: €1,690
Remaining: €0
Status: PAID
```

## سجل العمل

```text
Week 1
 ├── Sunday
 │    ├── 06:00 → 08:00
 │    └── 14:00 → 20:00
 │
 ├── Monday
 │    └── 09:00 → 13:00
 │
 └── Tuesday
      └── 14:00 → 18:00
```

---

# 9. شاشة تسجيل ساعات العمل

هذه أهم شاشة للاستخدام اليومي.

```text
Add Work Slot

Teacher: [ Ahmed ▼ ]

Date: [ 20/09/2026 ]

Start: [ 06:00 ]
End:   [ 08:00 ]

Duration: 2h

Notes:
[........................]

[ Save ]
```

بعد الحفظ يمكن إضافة Slot آخر لنفس اليوم:

```text
[ + Add Another Slot ]

14:00 → 20:00
```

ويظهر:

```text
Daily Total: 8h
```

---

# 10. قواعد التحقق من الـ Slots

هذه نقطة أساسية في الـ Backend.

يجب منع:

### 10.1 وقت النهاية قبل البداية

```text
14:00 → 10:00
```

إلا إذا تم دعم العمل الذي يمتد بعد منتصف الليل، وهو ليس مطلوباً في النسخة الأولى.

### 10.2 Slots متداخلة

مثال غير مسموح:

```text
06:00 → 10:00
08:00 → 12:00
```

لأن هناك تداخل ساعتين.

### 10.3 Slot صفر المدة

```text
10:00 → 10:00
```

غير مسموح.

### 10.4 Slot طويل بشكل غير منطقي

يفضل وضع حد إعدادات، مثلاً 24 ساعة كحد تقني، مع إمكانية تغييره.

### 10.5 تعديل Slot

كل تعديل يجب أن يعيد حساب:

```text
Day Total
Week Total
Month Total
Payroll
```

وفق حالة الشهر.

---

# 11. العرض الأسبوعي

العرض الأسبوعي يجب أن يكون واضحاً وسريعاً للاستخدام.

```text
Week: 14 - 20 September

Sunday
06:00 → 08:00   2h
14:00 → 20:00   6h
Total: 8h

Monday
09:00 → 13:00   4h
Total: 4h

Tuesday
14:00 → 18:00   4h
Total: 4h

-------------------------
Week Total: 16h
```

يمكن توفير Calendar View بالإضافة إلى List View.

---

# 12. العرض الشهري

```text
September 2026

Total Hours: 84h 30m
Hourly Rate: €20
Salary: €1,690

▼ Week 1       20h
▼ Week 2       22h 30m
▼ Week 3       18h
▼ Week 4       24h

-------------------------
Total           84h 30m
```

كل أسبوع قابل للفتح.

كل يوم قابل للفتح.

كل Slot قابل للتعديل.

---

# 13. الراتب

المعادلة الأساسية:

```text
Salary = Total Worked Minutes / 60 × Applicable Hourly Rate
```

مثال:

```text
84h 30m = 5070 minutes

5070 / 60 = 84.5 hours

84.5 × €20 = €1,690
```

## مهم

يفضل الحساب الداخلي بالدقائق وليس بالساعات العشرية.

```text
480 minutes
```

أفضل من:

```text
8.0 hours
```

لتجنب أخطاء التقريب.

---

# 14. سعر الساعة

لا أنصح بتخزين السعر الحالي فقط.

يجب وجود **سجل تاريخي للأسعار**.

```text
Hourly Rate History

01/01/2026 → €18
01/06/2026 → €20
01/09/2026 → €22
```

كل سعر له:

- effective_from
- effective_to

وبذلك نعرف أي سعر كان مطبقاً عند تسجيل العمل.

---

# 15. قرار مهم: كيف نتعامل مع تغيير سعر الساعة؟

أفضل قاعدة:

> الـ Payroll الشهري بعد الإغلاق يجب ألا يتأثر بتغيير سعر الساعة مستقبلاً.

قبل الإغلاق، يمكن إعادة الحساب حسب قواعد النظام.

بعد الإغلاق، يتم تثبيت Snapshot للراتب:

```text
Payroll
----------------
Total Minutes: 5070
Hourly Rate: €20
Gross Salary: €1690
Status: CLOSED
```

وبذلك حتى لو أصبح السعر لاحقاً €25، يبقى شهر September محفوظاً كما تم اعتماده.

---

# 16. Payroll

نحتاج كيان مستقل للراتب الشهري.

مثال:

```text
Payroll
Teacher: Ahmed
Period: September 2026

Worked: 84h 30m
Rate: €20
Gross: €1,690

Paid: €1,690
Remaining: €0

Status: PAID
```

الحالات المقترحة:

```text
DRAFT
CALCULATED
PARTIALLY_PAID
PAID
CLOSED
```

---

# 17. الدفع

لا يجب أن يكون زر "تم الدفع" مجرد Boolean.

الأفضل إنشاء Payment Record.

```text
Payment

Payroll: September 2026
Amount: €1,690
Paid At: 30/09/2026
Method: Bank Transfer
Notes: September salary
```

هذا يسمح لاحقاً بالدفع الجزئي.

مثال:

```text
Salary: €1,690

Payment 1: €1,000
Payment 2: €690

Remaining: €0
```

---

# 18. هل يتصفر الراتب في بداية الشهر؟

نعم من ناحية **الشهر الحالي**، لكن لا يتم حذف أو تصفير الشهر السابق.

مثال:

```text
September
Hours: 84h 30m
Salary: €1,690
Paid: YES

October
Hours: 0h
Salary: €0
Paid: NO
```

الشهر الجديد يبدأ تلقائياً بسجل جديد.

أما September فيبقى محفوظاً.

---

# 19. إغلاق الشهر

أنصح بشدة بإضافة مفهوم:

**Payroll Closing**

بعد انتهاء الشهر ومراجعة الساعات:

```text
September 2026

Total Teachers: 12
Total Hours: 384h
Total Payroll: €7,680

[ Close Payroll ]
```

بعد الإغلاق:

- لا يتم تعديل الساعات بشكل مباشر.
- لا يتغير الراتب تلقائياً.
- التاريخ المالي يصبح ثابتاً.
- أي تعديل استثنائي يجب أن يتم عبر صلاحية خاصة.

يفضل وجود:

```text
Reopen Payroll
```

للمستخدم الإداري المصرح له فقط.

---

# 20. سجل التدقيق Audit Log

هذه إضافة مهمة جداً إذا كان النظام سيستخدم فعلياً لإدارة الرواتب.

نسجل:

- من أضاف Slot
- من عدل Slot
- من حذف Slot
- من غير سعر الساعة
- من أنشأ Payroll
- من سجل Payment
- من أغلق الشهر
- من أعاد فتح الشهر

مثال:

```text
Audit Log

Ahmed Admin
20/09/2026 10:30
Changed Slot:
14:00 → 19:00
to
14:00 → 20:00
```

---

# 21. قاعدة البيانات المقترحة

## teachers

```text
id
name
phone
email
photo
status
joined_at
created_at
updated_at
```

## hourly_rates

```text
id
teacher_id
rate
effective_from
effective_to
created_at
```

## work_days

```text
id
teacher_id
date
notes
created_at
updated_at
```

## work_slots

```text
id
work_day_id
start_time
end_time
duration_minutes
notes
created_at
updated_at
```

## payroll_periods

```text
id
teacher_id
year
month
total_minutes
hourly_rate_snapshot
gross_amount
paid_amount
remaining_amount
status
closed_at
created_at
updated_at
```

## payments

```text
id
payroll_period_id
amount
paid_at
payment_method
reference
notes
created_at
```

## audit_logs

```text
id
user_id
entity_type
entity_id
action
old_values
new_values
created_at
```

---

# 22. العلاقات

```text
Teacher
  │
  ├────────────── Hourly Rates
  │
  ├────────────── Work Days
  │                   │
  │                   └──── Work Slots
  │
  └────────────── Payroll Periods
                       │
                       └──── Payments
```

---

# 23. قواعد العمل Business Rules

## Rule 1

كل Slot يجب أن يكون تابعاً لمدرس.

## Rule 2

كل Slot يجب أن يحتوي:

```text
date
start_time
end_time
```

## Rule 3

لا يسمح بتداخل Slots لنفس المدرس في نفس التاريخ.

## Rule 4

مدة Slot تحسب من:

```text
end_time - start_time
```

ولا يفضل أن يدخلها المستخدم يدوياً.

## Rule 5

إجمالي اليوم = مجموع Slots في اليوم.

## Rule 6

إجمالي الأسبوع = مجموع أيام الأسبوع.

## Rule 7

إجمالي الشهر = مجموع أيام الشهر.

## Rule 8

Payroll يعتمد على ساعات العمل وسعر الساعة المطبق.

## Rule 9

Payment لا يغير ساعات العمل.

## Rule 10

الدفع الجزئي مسموح.

## Rule 11

إغلاق Payroll يمنع التعديل العادي.

## Rule 12

بيانات الأشهر السابقة لا يتم حذفها عند بدء شهر جديد.

---

# 24. نقطة مهمة: المنطقة الزمنية

يجب تحديد Timezone للنظام.

مثلاً:

```text
Europe/Amsterdam
```

ويجب أن تكون أوقات العمل مرتبطة بالـ timezone الخاص بالمؤسسة.

لا يفضل الاعتماد على UTC فقط في واجهة تسجيل ساعات العمل، لأن المستخدم يتعامل مع وقت محلي.

---

# 25. التعامل مع منتصف الليل

في النسخة الأولى:

```text
22:00 → 02:00
```

يفضل منعه أو جعله Feature مستقلة.

لأن هذه الحالة تجعل Slot يمتد إلى يوم تقويمي آخر.

يمكن دعمها لاحقاً عبر:

```text
start_datetime
end_datetime
```

بدلاً من وقت فقط.

لكن طالما الاستخدام الأساسي مثل:

```text
06:00 → 08:00
14:00 → 20:00
```

فـ `date + start_time + end_time` كافٍ للنسخة الأولى.

---

# 26. بنية الواجهة المقترحة

```text
Dashboard
│
├── Teachers
│    ├── Teacher List
│    └── Teacher Profile
│         ├── Personal Info
│         ├── Current Month
│         ├── Timesheet
│         └── Salary History
│
├── Timesheet
│    ├── Daily
│    ├── Weekly
│    └── Monthly
│
├── Payroll
│    ├── Current Payroll
│    ├── Payments
│    └── Payroll History
│
└── Settings
     ├── General
     ├── Hourly Rates
     └── Payroll
```

---

# 27. تجربة الاستخدام اليومية

أفضل Workflow للمستخدم:

```text
فتح النظام
    ↓
اختيار المدرس
    ↓
اختيار التاريخ
    ↓
إضافة Slot
    ↓
06:00 → 08:00
    ↓
Save
    ↓
+ Add Slot
    ↓
14:00 → 20:00
    ↓
Save
    ↓
النظام يحسب 8 ساعات
```

ثم بشكل تلقائي:

```text
Day = 8h
Week = +8h
Month = +8h
Payroll = يعاد حسابه
```

---

# 28. تحسين مهم للواجهة

أقترح وجود زر سريع من الـ Dashboard:

```text
[ + تسجيل ساعات العمل ]
```

وعند فتحه:

```text
Teacher: [ Select ]
Date:    [ Today ]

Start: [ --:-- ]
End:   [ --:-- ]

[ Save & Add Another ]
[ Save ]
```

وزر:

**Save & Add Another**

مفيد جداً عندما يكون المدرس لديه عدة Slots في نفس اليوم.

---

# 29. التقارير

يفضل دعم:

### تقرير المدرس

```text
Teacher
Month
Total Hours
Hourly Rate
Salary
Payment Status
```

### تقرير شهري لجميع المدرسين

```text
Teacher | Hours | Rate | Salary | Paid | Remaining
```

### تصدير

لاحقاً:

- Excel
- CSV
- PDF

---

# 30. الصلاحيات

إذا كان هناك أكثر من مستخدم للنظام:

## Admin

- إدارة المدرسين
- تعديل الساعات
- تغيير الأسعار
- إدارة الرواتب
- الدفع
- إغلاق الشهر
- إعادة فتح الشهر

## Manager

- تسجيل وتعديل ساعات العمل
- مشاهدة التقارير
- مشاهدة الرواتب حسب الصلاحيات

## Viewer

- مشاهدة فقط

يفضل بناء الصلاحيات من البداية حتى لو كان النظام حالياً لمستخدم واحد.

---

# 31. ما لا أنصح به

## لا تخزن فقط:

```text
teacher_id
date
hours = 8
```

لأنك ستفقد:

- وقت البداية
- وقت النهاية
- تعدد الفترات
- إمكانية مراجعة ساعات العمل

## لا تعمل Reset للبيانات

لا تستخدم:

```text
September hours = 0
```

ثم تبدأ October.

بل كل شهر هو Payroll Period مستقل.

## لا تعتمد على Boolean للدفع فقط

بدلاً من:

```text
paid = true
```

استخدم Payments حتى تدعم الدفع الجزئي والتاريخ وطريقة الدفع.

## لا تستخدم السعر الحالي لحساب كل التاريخ

يجب حفظ تاريخ أسعار الساعة.

---

# 32. الهيكل النهائي المقترح

```text
                   ┌─────────────┐
                   │   TEACHER   │
                   └──────┬──────┘
                          │
              ┌───────────┴───────────┐
              │                       │
              ▼                       ▼
      ┌──────────────┐        ┌──────────────┐
      │ HOURLY RATES │        │  WORK DAYS   │
      └──────────────┘        └──────┬───────┘
                                     │
                                     ▼
                              ┌──────────────┐
                              │ WORK SLOTS   │
                              └──────┬───────┘
                                     │
                         ┌───────────┴───────────┐
                         ▼                       ▼
                    WEEK TOTAL              MONTH TOTAL
                                                 │
                                                 ▼
                                         ┌──────────────┐
                                         │   PAYROLL    │
                                         └──────┬───────┘
                                                │
                                                ▼
                                         ┌──────────────┐
                                         │   PAYMENTS   │
                                         └──────────────┘
```

---

# 33. مراحل التنفيذ المقترحة

## Phase 1 — Core

- Teachers
- Teacher Profile
- Time Slots
- Daily View
- Weekly View
- Monthly View
- Hourly Rate
- Automatic calculations

## Phase 2 — Payroll

- Monthly Payroll
- Payment Records
- Partial Payments
- Payroll History
- Close Payroll

## Phase 3 — Administration

- Users
- Roles & Permissions
- Audit Logs
- Settings

## Phase 4 — Reports

- Excel
- CSV
- PDF
- Advanced reports

---

# 34. النتيجة النهائية

المبدأ الأساسي للنظام:

```text
المستخدم يسجل فقط:

من الساعة → إلى الساعة

والنظام يقوم بالباقي.
```

مثال:

```text
الأحد

06:00 → 08:00
14:00 → 20:00
```

النظام يحسب:

```text
اليوم       = 8h
الأسبوع     = مجموع أيام الأسبوع
الشهر       = مجموع أسابيع الشهر
الراتب      = ساعات الشهر × السعر
المدفوع     = مجموع Payments
المتبقي     = الراتب - المدفوع
```

وفي بداية الشهر التالي:

```text
October = يبدأ Payroll جديد
September = يبقى محفوظاً بالكامل
```

وبذلك يصبح النظام **Timesheet + Payroll System** وليس مجرد سجل ساعات.

---

# 35. قرار معماري نهائي

الـ Source of Truth في النظام يجب أن يكون:

**Work Slots**

وليس الراتب، وليس مجموع الساعات.

كل الأرقام الأخرى تكون مشتقة منها:

```text
Work Slots
   ↓
Daily Hours
   ↓
Weekly Hours
   ↓
Monthly Hours
   ↓
Payroll
   ↓
Payment
```

هذا التصميم يقلل أخطاء الحساب، يحافظ على التاريخ، ويسمح بتوسعة النظام مستقبلاً دون إعادة بناء قاعدة البيانات.
