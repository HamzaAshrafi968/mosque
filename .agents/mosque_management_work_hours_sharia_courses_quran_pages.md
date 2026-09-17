# Mosque Management — Work Hours, Sharia Courses, Quran Pages & Branding

> مواصفات الميزات الجديدة: ساعات عمل المشرفين، شبكة أشهر اختبارات الحفاظ، أسابيع برنامج الإجازة، الدورة الشرعية، اسم المؤسسة، وتسميع القرآن بالصفحات.
>
> المرجع الأساسي: `mosque_management_implementation.md` + `mosque_management_quran_programs.md` + `mosque_management_portals_finance.md`.
> تاريخ الإعداد: 2026-09-12 — الحالة: مواصفات معتمدة (لم تُنفَّذ بعد).

---

# 0. القرارات المعيارية المعتمدة

| القرار | التفاصيل | البدائل المرفوضة |
|---|---|---|
| المشرفون | «المشرف» في هذا النظام = سجل `Teacher` الحالي (المعلم/الشيخ). لا فئة مستخدمين جديدة. | إنشاء كيان `Supervisor` منفصل |
| شكل ساعات العمل | جدول أسبوعي متكرر: لكل يوم من أيام الأسبوع فترة بداية/نهاية، والإجمالي الأسبوعي محسوب لا مخزّن. | إجمالي شهري يدوي / فترات بتواريخ مطلقة |
| طلاب الدورة الشرعية | سجل مستقل تماماً (`sharia_course_students`) منفصل عن جدول `students`. | الاختيار من طلاب المدرسة / الدمج |
| أسابيع الإجازة | 4 تقييمات أسبوعية داخل كل شهر + يبقى `ijazah_monthly_evaluations` كملخص وقاعدة الإكمال. | استبدال الشهري بالأسابيع / عرض فقط |
| شبكة الأشهر | تُطبَّق على اختبارات الحفاظ الشهرية (`hafiz_monthly_exams`) فقط. | تطبيقها على الإجازة أيضاً |
| صفحات القرآن | عرض صفحة المصحف كاملة + تحديد «من صفحة → إلى صفحة» في التسميع، وتخطيط المصحف المدني 604 صفحات. | أرقام صفحات فقط بدون عرض |
| اسم المؤسسة | «مؤسسة السفرة للعلوم والتنمية» في `APP_NAME` والواجهات. | إبقاء الاسم المختصر |
| نطاق التسليم الحالي | ملف المواصفات هذا فقط؛ التنفيذ على المراحل المذكورة في §12. | — |

**قواعد عامة ملزمة لكل الميزات:**

- كل جدول جديد يتبع نمط المشروع: `uuid` مفتاح أساسي، `tenant_id` مع `MultiTenantTrait`، `UuidTrait`، `FlushesTenantCache` عند الحاجة.
- كل عملية كتابة تُسجَّل في `AuditLogger` (§31 من المواصفة الأساسية).
- كل مسار إداري محمي بـ `role:admin` + `permission:` المناسب، ومسارات المعلم بـ `role:teacher` + فحص نطاق في الـ Backend.
- العزل بين الجوامع مفروض في قاعدة البيانات/الـ Backend، وليس بإخفاء عناصر الواجهة.
- أي عملية حذف/أرشفة تتطلب تأكيداً في الواجهة.

---

# 1. ساعات عمل المشرفين (Teacher Work Hours)

## 1.1 الهدف

تمكين مدير الجامع (ومدير الجوامع داخل سياق الجامع) من تحديد ساعات عمل المعلمين/المشرفين يدوياً على شكل جدول أسبوعي متكرر، وعرضها للمعلم في بوابته.

## 1.2 نموذج البيانات

### جدول `teacher_work_hours` (جديد)

```text
id            uuid PK
tenant_id     uuid FK tenants (cascade)
teacher_id    uuid FK teachers (cascade)
day_of_week   unsignedTinyInteger  0..6   (0 = الأحد ... 6 = السبت)
start_time    time
end_time      time
notes         text nullable
created_by    uuid nullable FK users
created_at
updated_at

index (tenant_id, teacher_id, day_of_week)
index (tenant_id, teacher_id)
```

- لا يوجد قيد فريد لأن اليوم قد يحتوي أكثر من فترة (مثال: 8:00–12:00 و 14:00–17:00).
- الحقل `day_of_week` يبدأ بالأحد `0` اتساقاً مع أسبوع العمل في المنطقة.

### النموذج `App\Models\TeacherWorkHour`

- Traits: `MultiTenantTrait`, `UuidTrait`, `FlushesTenantCache`, `HasFactory`.
- `$fillable`: tenant_id, teacher_id, day_of_week, start_time, end_time, notes, created_by.
- `casts`: `day_of_week` integer، `start_time`/`end_time` string (أو `datetime:H:i` حسب الحاجة).
- العلاقات: `teacher()` BelongsTo، `creator()` BelongsTo(User, created_by).
- أضف علاقة `workHours()` HasMany في `Teacher`.
- دوال مساعدة: `dayLabel()` (اسم اليوم بالعربية)، `durationHours()` (فرق الساعتين بالساعات العشرية)، `weeklyTotalHours(teacherId)`.

### Enum `App\Enums\WorkDay` (اختياري لكن موصى به)

```text
Sunday=0, Monday=1, Tuesday=2, Wednesday=3, Thursday=4, Friday=5, Saturday=6
label(): الأحد ... السبت
```

## 1.3 قواعد التحقق (Backend إلزامي)

1. `day_of_week` بين 0 و 6.
2. `start_time` و `end_time` بصيغة `H:i` صحيحة.
3. `end_time` > `start_time` وإلا خطأ تحقق عربي.
4. منع تداخل الفترات لنفس المعلم/اليوم: أي فترة جديدة تتقاطع مع فترة قائمة (نفس `teacher_id` + `day_of_week`) تُرفض مع رسالة توضح الفترة المتعارضة.
5. حد أقصى معقول لكل فترة (مثال: 12 ساعة) لكل يوم.
6. المعلم المستهدف يجب أن يكون ضمن نفس جامع المستخدم (`Rule::exists('teachers','id')->where('tenant_id', ...)`) — مدير الجوامع داخل سياق الجامع يمر بنفس الفحص.
7. لا يُسمح بتعديل `tenant_id` أو `teacher_id` من الطلب.

## 1.4 الصلاحيات

| الكود | الوصف | افتراضي مدير الجامع | افتراضي المعلم |
|---|---|---|---|
| `work_hours.view` | مشاهدة ساعات العمل | `mosque` | `own` |
| `work_hours.manage` | إدارة ساعات العمل (إضافة/تعديل/حذف) | `mosque` | — |

- تُضاف إلى `PermissionCatalog::ITEMS` و `MOSQUE_MANAGER` و `TEACHER`.
- المعلم يرى ساعاته فقط عبر نطاق `own` مع مسند ملكية (`$owns = fn($user, $subject) => $subject?->teacher?->user_id === $user->id`).

## 1.5 المسارات

### لوحة المدير

```text
GET    admin/work-hours                                  admin.work-hours.index
GET    admin/teachers/{teacher}/work-hours               admin.teachers.work-hours.index
POST   admin/teachers/{teacher}/work-hours               admin.teachers.work-hours.store
PATCH  admin/work-hours/{workHour}                       admin.work-hours.update
DELETE admin/work-hours/{workHour}                       admin.work-hours.destroy
```

- `index`: نظرة عامة لكل معلمي الجامع مع إجمالي الساعات الأسبوعية وعدد الفترات + فلترة (بحث بالاسم، اليوم).
- صفحة المعلم: جدول أسبوعي (7 أيام) يعرض الفترات، مع نموذج إضافة فترة (اليوم/من/إلى/ملاحظات) وتعديل/حذف مباشر.
- يمكن دمج صفحة الإدارة داخل `admin/teachers/show` كقسم إضافي مع إبقاء مسار مستقل للنظرة العامة.

### بوابة المعلم

```text
GET teacher/work-hours    teacher.work-hours.index
```

- تعرض جدول المعلم الأسبوعي + إجمالي الساعات + «ساعات اليوم».
- تُضاف بطاقة «ساعات عملي اليوم» في `teacher/dashboard`.

## 1.6 الواجهات (Front-end)

- مكوّن مشترك `resources/views/components/weekly-hours-grid.blade.php`:
  - Props: `hours` (Collection مجمّعة حسب اليوم), `editable` (bool), `teacher`.
  - يعرض 7 بطاقات أيام، كل بطاقة فتراتها + إجمالي اليوم.
  - حالة فراغ (empty state): «لم تُحدد ساعات عمل بعد».
- مكوّن `resources/views/components/work-hour-form.blade.php` (إضافة/تعديل فترة).
- في `admin/teachers/show`: قسم جديد بعنوان «ساعات العمل» + زر «إدارة ساعات العمل».
- تصميم RTL متسق مع Tailwind 4 والبطاقات الحالية (`rounded-2xl border border-gray-200 bg-white shadow-sm`).
- تأكيد الحذف عبر `onsubmit="return confirm(...)"` كما في بقية الواجهات.

## 1.7 التدقيق والاختبارات

- Audit: `work_hours.created`, `work_hours.updated`, `work_hours.deleted` مع القيم قبل/بعد.
- الاختبارات `tests/Feature/WorkHoursTest.php`:
  - مدير الجامع ينشئ فترة لمعلم في جامعه → 302 + صف في القاعدة.
  - معلم من جامع آخر → رفض (404/403) ولا صف.
  - `end_time <= start_time` → خطأ تحقق.
  - تداخل الفترات → خطأ تحقق.
  - معلم يرى ساعاته فقط (`teacher.work-hours.index`) ولا يرى ساعات غيره.
  - مستخدم بلا `work_hours.manage` → 403.

## 1.8 Definition of Done

- [ ] هجرة الجدول + النموذج + العلاقات.
- [ ] إدارة كاملة (إضافة/تعديل/حذف) من مدير الجامع ومدير الجوامع داخل الجامع.
- [ ] تحقق التعارض وصحة الأوقات في الـ Backend.
- [ ] عرض في بوابة المعلم والداشبورد.
- [ ] صلاحيات في الكتالوج + تدقيق + اختبارات خضراء.

---

# 2. شبكة أشهر السنة لاختبارات الحفاظ

## 2.1 الهدف

عرض اختبارات الحفاظ الشهرية كشبكة 12 شهراً للسنة المختارة، وعند النقر على شهر تظهر تفاصيله (نفس جدول الاختبارات الحالي).

## 2.2 السلوك

```text
اختبارات الحفاظ
   ↓
شبكة السنة (12 بطاقة شهر + ملخص الحالات)
   ↓ نقر على شهر
تفاصيل الشهر (جدول الحفاظ: الحالة/الدرجة/تاريخ الاختبار/الإعادات)
   ↓ نقر على حافظ
صفحة الاختبار (تسجيل النتيجة/الإعادات) — كما هي حالياً
```

## 2.3 المتحكم

`Admin\HafizExamController` و `Teacher\HafizExamController`:

- `index(Request)`: يعرض شبكة السنة.
  - `$year = validYear($request->input('year'))` (افتراضي السنة الحالية، ورفض القيم غير الصحيحة بالعودة للسنة الحالية).
  - `$months`: 12 عنصراً لكل شهر `YYYY-MM` مع:
    - `label` (اسم الشهر عربي + السنة).
    - `hafiz_count` (عدد الحفاظ).
    - `tested`, `not_tested`, `passed`, `failed` (تجميع من `hafiz_monthly_exams` الموجودة دون إنشاء صفوف).
    - `evaluated` (عدد الصفوف المسجلة).
  - **مهم:** لا تستدعِ `ensureMonthlyExamRows()` لشهور السنة في شبكة العرض؛ التجميع من الصفوف الموجودة فقط. إنشاء صفوف «لم يُختبر» يتم فقط عند فتح تفاصيل الشهر.
- `month(Request, string $month)`: يتحقق من الصيغة `YYYY-MM` ثم:
  - `ensureMonthlyExamRows($hafizIds, $month, $user)` كما في السلوك الحالي.
  - يعرض الجدول التفصيلي (نفس منطق `index` الحالي).
- تبقى `show` و `grade` و `storeRevision` و `completeRevision` و `approveRevision` دون تغيير.

## 2.4 المسارات

```text
GET admin/quran/exams                      admin.quran.exams.index     → شبكة السنة
GET admin/quran/exams/month/{month}        admin.quran.exams.month     → تفاصيل الشهر
GET admin/quran/exams/{exam}               admin.quran.exams.show
POST admin/quran/exams/{exam}/grade        admin.quran.exams.grade
... (بقية مسارات الإعادات كما هي)

GET teacher/quran/exams                    teacher.quran.exams.index   → شبكة السنة
GET teacher/quran/exams/month/{month}      teacher.quran.exams.month   → تفاصيل الشهر
```

- `{month}` مقيّد بـ `where('month', '\d{4}-(0[1-9]|1[0-2])')`.
- الروابط القديمة `?month=YYYY-MM` تُحوَّل إلى مسار الشهر الجديد (redirect 301 داخلي) للحفاظ على التوافق.

## 2.5 الواجهات

- مكوّن جديد `resources/views/components/year-months-grid.blade.php`:
  - Props: `year`, `months` (array), `routeName`, `extraParams` (اختياري).
  - رأس: منتقي السنة (السنة السابقة/التالية + `<input type="number">` أو `select`).
  - 12 بطاقة responsive (`grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3`):
    - اسم الشهر، شارة السنة.
    - عدد الحفاظ.
    - شارات ملونة: ناجح (أخضر)، راسب (أحمر)، مُختبر (أزرق)، لم يُختبر (رمادي).
    - الشهر الحالي مُبرز بإطار ذهبي.
  - حالة فراغ: «لا يوجد حفاظ مسجلون».
- `admin/quran/exams/year.blade.php` (واجهة جديدة) + إعادة تسمية/هيكلة `index.blade.php` الحالي إلى `month.blade.php` مع إضافة شريط «← العودة لشبكة الأشهر» وأزرار الشهر السابق/التالي.
- نفس المكوّن يُستخدم في `teacher/quran/exams/year.blade.php` و `month.blade.php`.

## 2.6 الاختبارات

`tests/Feature/HafizExamMonthsTest.php`:

- شبكة السنة ترجع 200 وتعرض 12 شهراً.
- سنة غير صحيحة → العودة للسنة الحالية دون خطأ 500.
- فتح شهر صحيح يعرض صفوف الحفاظ لنفس الشهر فقط.
- شهر بصيغة خاطئة → 404 (بسبب قيد المسار).
- العزل: مدير جامع آخر لا يرى حفاظ الجامع الأول.

## 2.7 Definition of Done

- [ ] شبكة 12 شهراً + منتقي سنة في لوحتي المدير والمعلم.
- [ ] النقر على الشهر يفتح التفاصيل الصحيحة.
- [ ] عدم إنشاء صفوف لشهور غير مفتوحة.
- [ ] اختبارات خضراء.

---

# 3. برنامج الإجازة — الشهر يحتوي أربعة أسابيع

## 3.1 الهدف

توسيع برنامج الإجازة الشهري بحيث يحتوي كل شهر على 4 تقييمات أسبوعية، مع بقاء التقييم الشهري (`ijazah_monthly_evaluations`) كملخص وقاعدة الإكمال الحالية.

## 3.2 نموذج البيانات

### جدول `ijazah_weekly_evaluations` (جديد)

```text
id               uuid PK
tenant_id        uuid FK tenants (cascade)
student_id       uuid FK students (cascade)
month            string(7)        YYYY-MM
week             unsignedTinyInteger  1..4
week_start       date nullable
week_end         date nullable
amount           decimal(6,2)     المقدار
recited_portion  string nullable  اسم المقروء
result           string           passed | needs_review | failed
evaluated_by     uuid nullable FK teachers
notes            text nullable
created_at
updated_at

unique (tenant_id, student_id, month, week)
index  (tenant_id, student_id, month)
```

### النموذج `App\Models\IjazahWeeklyEvaluation`

- Traits: `MultiTenantTrait`, `UuidTrait`, `FlushesTenantCache`, `HasFactory`.
- `casts`: `week` int، `week_start`/`week_end` date، `amount` decimal:2، `result` → `QuranEvaluationResult`.
- العلاقات: `student()`, `evaluatedBy()` (Teacher).
- إضافة علاقة `ijazahWeeklyEvaluations()` في `Student`.

## 3.3 قواعد العمل

1. `week` بين 1 و 4 فقط.
2. قيد فريد `(tenant_id, student_id, month, week)` — لا تقييمان لنفس الأسبوع؛ التعديل عبر `PATCH`.
3. الطالب يجب أن يكون ملتحقاً ببرنامج الإجازة بحالة `active` (`ProgramEnrollment`).
4. الأسابيع السابقة لا تُستبدل — التعديل متاح للتقييم القائم نفسه فقط.
5. `result = passed` تُحتسب في ملخص الشهر، لكن **قاعدة الإكمال تبقى على `ijazah_monthly_evaluations`** (`IJAZAH_MIN_PASSED_MONTHS`) دون تغيير.
6. عند حفظ التقييم الشهري (الحالي) يظهر في صفحة الشهر كملخص أعلى الأسابيع.
7. كل كتابة تُسجَّل: `ijazah.weekly_recorded` / `ijazah.weekly_updated` مع Audit.

## 3.4 المسارات

### المدير

```text
GET    admin/quran/ijazah/{student}/month/{month}          admin.quran.ijazah.month
POST   admin/quran/ijazah/weekly                            admin.quran.ijazah.weekly.store
PATCH  admin/quran/ijazah/weekly/{evaluation}               admin.quran.ijazah.weekly.update
DELETE admin/quran/ijazah/weekly/{evaluation}               admin.quran.ijazah.weekly.destroy
```

### المعلم (مع فحص النطاق: الطالب ضمن شعبه/برامجه)

```text
GET    teacher/quran/ijazah/{student}/month/{month}         teacher.quran.ijazah.month
POST   teacher/quran/ijazah/weekly                          teacher.quran.ijazah.weekly.store
PATCH  teacher/quran/ijazah/weekly/{evaluation}             teacher.quran.ijazah.weekly.update
```

- التحقق: `month` بصيغة `YYYY-MM`، `week` 1..4، `amount` رقمي ≥ 0، `result` ضمن القيم، `evaluated_by` ضمن نفس الجامع (للمدير)، وللمعلم يُضبط تلقائياً على سجل المعلم الحالي.

## 3.5 الواجهات

- `admin/quran/ijazah/month.blade.php` + `teacher/quran/ijazah/month.blade.php`:
  - رأس: اسم الطالب، الشهر (اسم عربي + سنة)، زر «← العودة لبرنامج الإجازة».
  - بطاقة الملخص الشهري: نتيجة `ijazah_monthly_evaluations` إن وُجدت + عدد الأسابيع الناجحة من 4 + زر «تسجيل التقييم الشهري».
  - 4 بطاقات أسابيع (`grid grid-cols-1 md:grid-cols-2 gap-4`):
    - الأسبوع 1..4 + تواريخ الأسبوع (اختياري، تُحسب من الشهر إن لم تُدخل).
    - نموذج: المقدار، المقروء، النتيجة، المشرف/المعلم (للمدير)، ملاحظات.
    - حفظ/تعديل حسب وجود التقييم.
    - شارة النتيجة ملونة (أخضر/أصفر/أحمر) + شارة «لم يُقيَّم» رمادية.
  - مؤشر تقدم: `2 / 4 أسابيع`.
- تعديل `admin/quran/ijazah/index.blade.php` و `teacher/quran/ijazah/index.blade.php`: إضافة عمود «آخر شهر / عرض الأسابيع» ورابط لصفحة الشهر.
- تحديث `journey.blade.php` لعرض أسابيع الشهر الحالي عند برنامج الإجازة.

## 3.6 الاختبارات

`tests/Feature/IjazahWeeksTest.php`:

- تسجيل 4 أسابيع لنفس الطالب/الشهر → 4 صفوف.
- إعادة تسجيل نفس الأسبوع → خطأ تحقق (أو يُوجَّه للتعديل).
- `week = 5` → خطأ تحقق.
- طالب غير ملتحق → خطأ تحقق.
- صفحة الشهر تعرض 4 أسابيع والملخص.
- معلم من خارج نطاق الطالب → 403.
- قاعدة الإكمال الشهري لم تتأثر.

## 3.7 Definition of Done

- [ ] جدول + نموذج + علاقات.
- [ ] صفحة شهر بـ 4 أسابيع في لوحتي المدير والمعلم.
- [ ] قيد فريد + تحقق + تدقيق.
- [ ] الملخص الشهري وقاعدة الإكمال يعملان كما قبل.
- [ ] اختبارات خضراء.

---

# 4. الدورة الشرعية (Sharia Course)

## 4.1 الهدف

وحدة مستقلة لإدارة دورات شرعية تحتوي على دروس ومحاضرات، وطلاب يُسجَّلون في سجل مستقل تماماً عن طلاب المدرسة، مع تسجيل الحضور والغياب.

## 4.2 نموذج البيانات

### جدول `sharia_courses`

```text
id            uuid PK
tenant_id     uuid FK tenants (cascade)
name          string
description   text nullable
location      string nullable
start_date    date nullable
end_date      date nullable
status        string default active    draft | active | completed | cancelled
source        string default mosque    mosque | super_admin   -- §4.9
created_by    uuid nullable FK users
created_at
updated_at

index (tenant_id, status)
index (tenant_id, source)
```

> تحديث §4.9: حُذف `supervisor_id` واستُبدل بجدول وسيط `sharia_course_supervisor` (مشرفون متعددون).

### جدول `sharia_course_lessons`

```text
id               uuid PK
tenant_id        uuid FK tenants (cascade)
course_id        uuid FK sharia_courses (cascade)
title            string
type             string    lesson (درس) | lecture (محاضرة)
date             date
start_time       time nullable
end_time         time nullable
teacher_id       uuid nullable FK teachers (set null)
description      text nullable
attachment_path  string nullable
created_at
updated_at

index (tenant_id, course_id, date)
```

### جدول `sharia_course_students` (سجل مستقل + ربط اختياري بالطلاب)

```text
id                     uuid PK
tenant_id              uuid FK tenants (cascade)
course_id              uuid FK sharia_courses (cascade)
student_id             uuid nullable FK students (set null)   -- §4.9
name                   string
phone                  string nullable
gender                 string nullable   male | female
birth_date             date nullable
guardian_phone         string nullable
notes                  text nullable
status                 string default active   active | inactive
memorization_status    string nullable   not_memorized | parts_memorized | half_memorized | memorized   -- §4.9
memorization_notes     text nullable
memorization_updated_by uuid nullable FK users
memorization_updated_at timestamp nullable
created_at
updated_at

index (tenant_id, course_id, status)
index (tenant_id, course_id, name)
unique (course_id, student_id)          -- عند وجود student_id
```

- **الإضافة اليدوية تبقى سجلاً مستقلاً** عن `students` (`student_id = null`)، بينما «تسجيل طالب موجود» ينسخ بياناته ويربطه بـ `student_id` (يُحدَّث عند حذف الطالب إلى null مع الاحتفاظ بالاسم).
- لا يُسمح بحذف طالب له سجلات حضور إلا بحذف متسلسل (cascade) أو أرشفته (`status = inactive`)؛ القرار: الحذف الفعلي لصف الطالب يحذف حضوره معه (cascade) مع تأكيد في الواجهة وتسجيل Audit.

### جدول `sharia_course_attendance`

```text
id          uuid PK
tenant_id   uuid FK tenants (cascade)
course_id   uuid FK sharia_courses (cascade)
lesson_id   uuid nullable FK sharia_course_lessons (cascade)
student_id  uuid FK sharia_course_students (cascade)
date        date
status      string   present | absent | late | excused
notes       text nullable
recorded_by uuid nullable FK users
created_at
updated_at

unique (lesson_id, student_id)          -- عند وجود lesson_id
index  (tenant_id, course_id, date)
index  (tenant_id, student_id)
```

- عند عدم وجود `lesson_id` (تحضير يومي عام) يُمنع التكرار لنفس `(course_id, student_id, date)` في منطق الخدمة.
- `excused` يتطلب `notes` إلزامياً (نفس قاعدة الحضور العام في §15 من المواصفة الأساسية).

### Enums

```text
App\Enums\ShariaCourseStatus: draft | active | completed | cancelled  + label()
App\Enums\ShariaLessonType:   lesson | lecture                          + label()
App\Enums\ShariaAttendanceStatus: present | absent | late | excused     + label()
App\Enums\ShariaMemorizationStatus: not_memorized | parts_memorized | half_memorized | memorized  + label() + badgeClass()  -- §4.9
```

### النماذج

`ShariaCourse`, `ShariaCourseLesson`, `ShariaCourseStudent`, `ShariaCourseAttendance` — كلها بـ `MultiTenantTrait`, `UuidTrait`, `FlushesTenantCache`, `HasFactory` وعلاقات متبادلة.

## 4.3 الخدمة

`App\Services\ShariaCourseService`:

- `saveAttendance(ShariaCourse $course, ?ShariaCourseLesson $lesson, string $date, array $marks, User $actor)`: upsert آمن لكل طالب (تحديث الموجود وإنشاء الناقص) داخل Transaction واحدة.
- `attendanceSummary(ShariaCourse $course, ?ShariaCourseStudent $student = null): array` (present/absent/late/excused + النسبة).
- `assertCourseAccess(Teacher $teacher, ShariaCourse $course)`: المشرف أو معلم محاضرة/درس فقط.
- تسجيل Audit لكل عمليات الحضور والطلاب والدروس.

## 4.4 الصلاحيات

| الكود | الوصف | مدير الجامع | المعلم |
|---|---|---|---|
| `sharia_courses.view` | مشاهدة الدورات | `mosque` | `own` (دوراته فقط) |
| `sharia_courses.create` | إنشاء دورة | `mosque` | — |
| `sharia_courses.update` | تعديل دورة/دروس/طلاب | `mosque` | `own` (دوراته) |
| `sharia_courses.delete` | حذف دورة | `mosque` | — |
| `sharia_courses.attendance` | تسجيل الحضور | `mosque` | `own` (دوراته) |
| `sharia_courses.memorization` | تحديث حالة حفظ طلاب الدورة | `mosque` | `own` (دوراته) — §4.9 |

- تُضاف إلى `PermissionCatalog` وافتراضيات الأدوار.
- المعلم لا يرى إلا الدورات التي هو أحد `supervisors` (pivot) أو التي له فيها درس/محاضرة.

## 4.5 المسارات والواجهات

### المدير

```text
GET    admin/sharia-courses                              admin.sharia-courses.index
GET    admin/sharia-courses/create                       admin.sharia-courses.create
POST   admin/sharia-courses                              admin.sharia-courses.store
GET    admin/sharia-courses/{course}                     admin.sharia-courses.show
GET    admin/sharia-courses/{course}/edit                admin.sharia-courses.edit
PATCH  admin/sharia-courses/{course}                     admin.sharia-courses.update
DELETE admin/sharia-courses/{course}                     admin.sharia-courses.destroy

POST   admin/sharia-courses/{course}/lessons             admin.sharia-courses.lessons.store
PATCH  admin/sharia-courses/lessons/{lesson}             admin.sharia-courses.lessons.update
DELETE admin/sharia-courses/lessons/{lesson}             admin.sharia-courses.lessons.destroy

POST   admin/sharia-courses/{course}/students            admin.sharia-courses.students.store
POST   admin/sharia-courses/{course}/students/existing   admin.sharia-courses.students.existing        -- §4.9
PATCH  admin/sharia-courses/students/{student}           admin.sharia-courses.students.update
PATCH  admin/sharia-courses/students/{student}/memorization  admin.sharia-courses.students.memorization  -- §4.9
DELETE admin/sharia-courses/students/{student}           admin.sharia-courses.students.destroy

POST   admin/sharia-courses/{course}/attendance          admin.sharia-courses.attendance.store
```

### مدير الجوامع (إنشاء مركزي — §4.9)

```text
GET  super-admin/sharia-courses                          super-admin.sharia-courses.index
GET  super-admin/sharia-courses/create                   super-admin.sharia-courses.create
GET  super-admin/sharia-courses/options?mosque_id=       super-admin.sharia-courses.options   (JSON)
POST super-admin/sharia-courses                          super-admin.sharia-courses.store
```

### المعلم (المشرف)

```text
GET   teacher/sharia-courses                             teacher.sharia-courses.index
GET   teacher/sharia-courses/{course}                    teacher.sharia-courses.show
POST  teacher/sharia-courses/{course}/attendance         teacher.sharia-courses.attendance.store
PATCH teacher/sharia-courses/students/{student}/memorization  teacher.sharia-courses.students.memorization  -- §4.9
```

### الواجهات

- `admin/sharia-courses/index.blade.php`: بطاقات/جدول الدورات (الاسم، المشرف، التواريخ، الحالة، عدد الطلاب، عدد الدروس).
- `admin/sharia-courses/create.blade.php` + `edit.blade.php`: بيانات الدورة + المشرف.
- `admin/sharia-courses/show.blade.php`: تبويبات (Tabs) بدون مكتبات خارجية:
  - **الدروس والمحاضرات**: جدول (العنوان، النوع، التاريخ، الوقت، المعلم، مرفق) + نموذج إضافة.
  - **الطلاب**: جدول السجل المستقل (الاسم، الجوال، الجنس، الحالة) + نموذج إضافة/تعديل/أرشفة.
  - **الحضور**: اختيار الدرس/التاريخ → جدول الطلاب مع أزرار present/absent/late/excused + ملاحظة لكل طالب + حفظ جماعي (نفس نمط `attendance-marks-form`).
  - **التقرير**: ملخص لكل طالب (حضور/غياب/تأخر/إذن + النسبة).
- `teacher/sharia-courses/index.blade.php` + `show.blade.php` (عرض + حضور فقط).
- روابط في القائمة الجانبية:
  - المدير: «الدورات الشرعية» (أيقونة مناسبة مثل `quran`/`lessons`).
  - المعلم: «الدورات الشرعية».

## 4.6 التدقيق

```text
sharia_course.created / updated / deleted
sharia_course.student_added / student_updated / student_removed
sharia_course.lesson_added / lesson_updated / lesson_removed
sharia_course.attendance_saved
```

## 4.7 الاختبارات

`tests/Feature/ShariaCoursesTest.php`:

- إنشاء دورة في الجامع → 302 + صف.
- إضافة طالب مستقل → لا يظهر في جدول `students` إطلاقاً.
- تسجيل الحضور لكل الحالات الأربع + رفض `excused` بلا ملاحظة.
- منع التكرار لنفس (الدرس/الطالب).
- حساب النسبة صحيح (excused خارج المقام كما في سياسة الحضور).
- معلم غير مشرف → 403؛ معلم من جامع آخر → 404.
- حذف دورة يحذف ملحقاتها (cascade).

`tests/Feature/ShariaCourseCentralTest.php` (§4.9): الإنشاء المركزي + إشعار مدير الجامع + العزل، مشرفون متعددون، خيارات الجامع، تسجيل الطلاب الموجودين مرة واحدة، وحالة الحفظ (مدير/مشرف/غير مشرف).

## 4.8 Definition of Done

- [ ] 4 جداول + 4 نماذج + Enums.
- [ ] إدارة كاملة للمدير + عرض/حضور للمشرف.
- [ ] طلاب مستقلون تماماً + حضور/غياب + تقرير.
- [ ] صلاحيات + تدقيق + اختبارات خضراء.

## 4.9 تحديث: الإنشاء المركزي ومشرفون متعددون وربط الطلاب وحالة الحفظ

### البيانات

- **مشرفون متعددون**: `sharia_course_supervisor` (course_id + teacher_id + timestamps، primary مركب) مع ترحيل `supervisor_id` القديم ثم حذفه (migrations `2026_09_17_000001` / `000002`). العلاقات: `ShariaCourse::supervisors()` (BelongsToMany) و`Teacher::supervisedShariaCourses()`.
- **مصدر الدورة**: `sharia_courses.source` = `mosque` (إدارة الجامع) أو `super_admin` (مدير الجوامع) — migration `2026_09_17_000004`، مع شارة «من مدير الجوامع» في الواجهات.
- **ربط الطلاب**: `sharia_course_students.student_id` nullable FK → `students` مع `unique (course_id, student_id)` (migration `2026_09_17_000003`)؛ الطالب الموجود يُنسخ اسمه/جنسه/تاريخ ميلاده/هاتف وليه، والمضاف يدوياً يبقى بلا رابط.
- **حالة الحفظ**: `memorization_status` + `memorization_notes` + `memorization_updated_by/at` على سجل الطالب، بقيم `ShariaMemorizationStatus`: لم يحفظ / حفظ أجزاء منه / حفظ النصف / حفظ كامل.

### الخدمة

- `ShariaCourseService::assertCourseAccess` و`coursesFor` تفحصان pivot المشرفين بدل العمود المحذوف.
- `syncEnrolledStudents(ShariaCourse, array $studentIds, User $actor)`: تسجيل الطلاب الموجودين idempotent (يتجاهل المسجَّل، ويرفض طالباً من جامع آخر) مع Audit.
- `updateMemorization(ShariaCourseStudent, ?ShariaMemorizationStatus, ?string, User)`: يحفظ الحالة والملاحظات ومن حدّثها ومتى + Audit.
- `EnsurePermission` يعامل `ShariaCourse` بمنطق «ملكية = أحد مشرفي الـpivot» لنطاق `own` (بعد حذف `supervisor_id`)، ويبقى `assertCourseAccess` حارس المعلم الفعلي.

### واجهة مدير الجوامع

- `SuperAdmin\ShariaCourseController` (index/create/options/store) مع `withoutGlobalScope('tenant')` و`tenant_id` صريح عند الإنشاء.
- نموذج الإنشاء: اختيار الجامع (المكان) → تحميل المشرفين والطلاب عبر JSON (`options`) → تحديد مشرفين متعددين + طلاب موجودين (checkbox grid + فلتر) + إضافة طلاب جدد inline + خصائص الدورة (اسم/وصف/مكان/تواريخ/حالة).
- عند الحفظ: إنشاء الدورة + مزامنة المشرفين + تسجيل الطلاب + إشعار كل مديري الجامع المستهدف (`role = admin`) عبر `PortalNotification` مباشرة (لا عبر `NotificationService` لتفادي فلترة نطاق الجامع الحالي لمدير الجوامع) بعنوان «دورة شرعية جديدة».
- `super-admin/sharia-courses/index.blade.php` + `create.blade.php` + رابط Sidebar «الدورات الشرعية».

### واجهات الجامع

- تبويب «الطلاب» في الإدارة والمعلم: قسم «تسجيل طلاب موجودين» + جدول حالة الحفظ (شارة ملوّنة + تعديل للمدير أو مشرفي الدورة فقط).
- `admin.sharia-courses.students.existing` و`*.students.memorization` (admin + teacher) بصلاحية `sharia_courses.memorization` (backfill `2026_09_17_000005`).

### DoD التحديث

- [x] pivot مشرفين + ترحيل البيانات وحذف العمود.
- [x] إنشاء مركزي من مدير الجوامع + إشعار مدير الجامع + عزل الجامعات.
- [x] طلاب موجودون (ربط) + طلاب جدد (سجل مستقل) + منع التكرار.
- [x] حالة الحفظ لكل طالب + صلاحية + Audit + واجهات admin/teacher.
- [x] `ShariaCourseCentralTest` + تحديث `ShariaCoursesTest` و`UserPermissionOverrideTest`.

---

# 5. اسم المؤسسة

## 5.1 الاسم المعتمد

```text
مؤسسة السفرة للعلوم والتنمية
```

## 5.2 نطاق التغيير

| الملف | التغيير |
|---|---|
| `.env` | `APP_NAME="مؤسسة السفرة للعلوم والتنمية"` |
| `.env.example` | نفس القيمة |
| `config/app.php` | القيمة الاحتياطية `env('APP_NAME', 'مؤسسة السفرة للعلوم والتنمية')` |
| `resources/views/layouts/app.blade.php` | عنوان الصفحة (fallback) + نص الشعار الجانبي (سطران 64 و 89) |
| `resources/views/layouts/guest.blade.php` | عنوان الصفحة + `h1` (سطر 39) + نص `alt` + التذييل |
| أي موضع آخر يظهر فيه «مؤسسة السفرة» | يُستبدل بالاسم الكامل |

- بعد التعديل: `php artisan config:clear`.
- الشعارات (`logo-mark.png` وغيرها) تبقى دون تغيير.
- لا تغيير على عناوين الصفحات الداخلية (مثل «لوحة إدارة الجامع»).

## 5.3 التحقق

- فحص شامل: `grep -r "مؤسسة السفرة" resources/ config/ .env .env.example` لا يعيد أي تطابق مختصر.
- فتح صفحة الدخول والداشبورد: الاسم الكامل يظهر في التبويب والواجهة.

---

# 6. تسميع القرآن بالصفحات + عرض صفحات المصحف

## 6.1 الهدف

1. إضافة أرقام الصفحات (وأرقام الأجزاء) إلى بيانات الآيات.
2. تسجيل التسميع بـ «من صفحة → إلى صفحة» مع حساب المقدار تلقائياً.
3. عرض صفحة المصحف كاملة داخل النظام.

## 6.2 البيانات (خطوة تأسيسية إلزامية)

### هجرة `add_page_and_juz_to_quran_ayahs_table`

```text
quran_ayahs:
+ page  unsignedSmallInteger nullable  (1..604)
+ juz   unsignedTinyInteger  nullable  (1..30)
+ index (page)
+ index (juz)
```

### ملف التخطيط `database/data/quran_pages.json`

- 604 عنصراً، كل عنصر:

```json
{ "page": 1, "start": "1:1", "end": "1:7" }
```

- `start`/`end` بصيغة `surah:ayah`، ويُسمح بأن تمتد الصفحة بين سورتين.
- **سكربت التوليد** `database/fetch-quran-pages.php`: يجلب `https://api.alquran.cloud/v1/meta` مرة واحدة ويكتب الملف أعلاه (يُشغَّل يدوياً، والملف الناتج يُحفظ في المستودع). إن تعذّر المصدر، يُستبدل بمصدر موثوق آخر للتخطيط المدني مع توثيق المصدر أعلى الملف.

### التعبئة

- `QuranPageSeeder` (يُستدعى من `QuranDataSeeder` بعد زرع الآيات إن وُجد الملف) + أمر `php artisan quran:pages` لتعبئة قواعد البيانات القائمة:

```text
قراءة الملف → ترتيب الآيات حسب (sort_order للسورة, ayah_number)
→ لكل صفحة: تحديد أول/آخر آية ضمن مداها → تعيين page و juz للآيات
```

- التحقق بعد التعبئة: عدد الآيات بلا `page` = 0.

### النموذج والخدمة

- `QuranAyah`: إضافة `page`, `juz` إلى `$fillable` و casts (`integer`).
- `App\Services\QuranPageService`:
  - `ayahsForPage(int $page): Collection` — آيات الصفحة مرتبة مع `surah`.
  - `pagesForRange(int $from, int $to): Collection` — مجموعة الصفحات مع رؤوس السور.
  - `maxPage(): int` (604).
  - `surahStartsOnPage(int $page): Collection` — السور التي تبدأ في الصفحة (لرسم رأس السورة والبسملة).

## 6.3 تعديل التسميع

### هجرة `add_page_range_to_quran_recitation_sessions`

```text
quran_recitation_sessions:
+ from_page unsignedSmallInteger nullable
+ to_page   unsignedSmallInteger nullable
```

### القواعد

1. `from_page`/`to_page` اختياريان (يبقى التسميع النصي/المقدار الحر مدعوماً للتوافق).
2. إذا وُجد أحدهما يجب وجود الآخر.
3. `1 <= from_page <= to_page <= 604`.
4. عند إدخال المدى ولم يُدخل `amount`: يُحسب تلقائياً `to_page - from_page + 1` (عدد الصفحات).
5. `recited_portion` يمكن توليده تلقائياً: «من الصفحة X إلى الصفحة Y» إن تُرك فارغاً.
6. التحقق في الـ Backend (وليس الواجهة فقط) في `Admin\QuranTasmeeController` و `Teacher\QuranTasmeeController` والـ API.

## 6.4 المسارات والواجهات

### مسارات العرض

```text
GET quran/pages/{page}            quran.pages.show        → صفحة عرض المصحف (auth + role)
GET quran/pages/{page}/preview    quran.pages.preview     → HTML جزئي للمعاينة داخل Modal
GET quran/pages/{page}/json       quran.pages.json        → JSON (للاستخدام في API/JS)
```

- متاح للمدير (داخل الجامع) والمعلم، بصلاحية `quran.tasmee.view`.
- الـ API: `GET /api/v1/{admin|teacher}/quran/pages/{page}`.

### مكوّن العرض

`resources/views/components/quran-page.blade.php`:

- Props: `page` (int), `ayahs` (Collection), `surahStarts` (Collection).
- العرض بأسلوب المصحف: إطار مزدوج، خلفية ورقية فاتحة، خط `Amiri`/`Scheherazade New` (محضّران مسبقاً في `layouts`).
- رأس السورة عند بداية سورة جديدة، والبسملة في أول آية من كل سورة عدا التوبة.
- علامة نهاية الآية `۝` + رقم الآية بالأرقام العربية-الهندية.
- ترقيم الصفحة أسفل الإطار + أزرار «الصفحة السابقة/التالية».
- حالة فراغ إذا لم تُعبأ البيانات: رسالة «بيانات الصفحات غير مهيأة — شغّل php artisan quran:pages».

### دمج التسميع

- `admin/quran/tasmee/create.blade.php` و `teacher/quran/tasmee/create.blade.php`:
  - حقلا «من صفحة» و «إلى صفحة» (number, 1..604) بجانب المقدار.
  - زر «معاينة الصفحات» يفتح Modal يعرض الصفحات من `quran.pages.preview` (JS بسيط بدون مكتبات — نفس أسلوب `app.js` الحالي).
  - عند تغيير المدى: تعبئة المقدار تلقائياً (`to - from + 1`) مع إمكانية التعديل اليدوي.
- صفحتا `index`: عمود «الصفحات» يعرض «ص X → ص Y» أو «—».
- صفحتا `edit`: نفس الحقول.
- تحديث `QuranRecitationSession` (`$fillable`) و `StoreQuranReviewRequest`/طلبات الـ API إن وُجدت لهذا المسار.

## 6.5 الاختبارات

`tests/Feature/QuranPagesTest.php`:

- تعبئة البيانات: ص1 = الفاتحة، ص2 = البقرة 1..5، ص604 = الناس (تحقق من أمثلة).
- صفحة تمتد بين سورتين: ترجع آيات السورتين بالترتيب الصحيح.
- `ayahsForPage` مرتبة تصاعدياً.
- تسميع بمدى صفحات صحيح → 302 و `amount = to - from + 1`.
- `to_page < from_page` أو قيمة > 604 → خطأ تحقق.
- مدى أحادي الطرف → خطأ تحقق.
- التوافق: تسميع قديم بدون صفحات ما زال يعمل.

## 6.6 Definition of Done

- [ ] عمودا `page`/`juz` + ملف التخطيط + التعبئة + أمر artisan.
- [ ] خدمة صفحات + مكوّن عرض مصحف متجاوب.
- [ ] تسميع «من/إلى صفحة» في لوحتي المدير والمعلم + API.
- [ ] تحقق Backend + Audit (يمر عبر التسجيل الحالي).
- [ ] اختبارات خضراء.

---

# 7. مصفوفة الصلاحيات الجديدة

تُضاف إلى `app/Support/PermissionCatalog.php`:

```text
work_hours.view            مشاهدة ساعات العمل
work_hours.manage          إدارة ساعات العمل

sharia_courses.view        مشاهدة الدورات الشرعية
sharia_courses.create      إنشاء دورة شرعية
sharia_courses.update      تعديل دورة شرعية
sharia_courses.delete      حذف دورة شرعية
sharia_courses.attendance  تسجيل حضور الدورات الشرعية
```

| الكود | MOSQUE_MANAGER | TEACHER |
|---|---|---|
| `work_hours.view` | mosque | own |
| `work_hours.manage` | mosque | — |
| `sharia_courses.view` | mosque | own |
| `sharia_courses.create` | mosque | — |
| `sharia_courses.update` | mosque | own |
| `sharia_courses.delete` | mosque | — |
| `sharia_courses.attendance` | mosque | own |

- لا صلاحيات جديدة لصفحات القرآن (تُستخدم `quran.tasmee.view`).
- لا صلاحيات جديدة لشبكة الأشهر أو أسابيع الإجازة (تُستخدم صلاحيات `hafiz_exams.*` و `ijazah.*` الحالية).
- يُراعى §55 من المواصفة الأساسية (تحديث الكتالوج المتدرج وعدم الاعتماد على `ensurePermissionCatalog` المبكر).

---

# 8. ملخص الهجرات

| # | الهجرة | الجدول/التغيير |
|---|---|---|
| 1 | `create_teacher_work_hours_table` | `teacher_work_hours` |
| 2 | `create_ijazah_weekly_evaluations_table` | `ijazah_weekly_evaluations` |
| 3 | `create_sharia_courses_table` | `sharia_courses` |
| 4 | `create_sharia_course_lessons_table` | `sharia_course_lessons` |
| 5 | `create_sharia_course_students_table` | `sharia_course_students` |
| 6 | `create_sharia_course_attendance_table` | `sharia_course_attendance` |
| 7 | `add_page_and_juz_to_quran_ayahs_table` | `quran_ayahs.page/juz` |
| 8 | `add_page_range_to_quran_recitation_sessions` | `quran_recitation_sessions.from_page/to_page` |

- كل الهجرات `down()` متوافقة مع `migrate:rollback`.
- تُراعى قاعدة البيانات الافتراضية SQLite (لا ENUM حقيقي — قيم نصية + تحقق في الكود).

---

# 9. مكونات الواجهة المشتركة (Front-end)

| المكوّن | الاستخدام | ملاحظات |
|---|---|---|
| `x-year-months-grid` | شبكة أشهر اختبارات الحفاظ | responsive، منتقي سنة، شارات حالات |
| `x-weekly-hours-grid` | جدول ساعات العمل الأسبوعي | وضع قراءة/تحرير |
| `x-work-hour-form` | نموذج فترة عمل | تحقق فوري + رسائل خطأ |
| `x-quran-page` | عرض صفحة المصحف | خطوط قرآنية + ترقيم + تنقل |
| `x-sharia-attendance-marks` | شبكة تحضير الدورة | يعاد استخدام نمط `attendance-marks-form` |

**قواعد UX إلزامية:**

- RTL كامل، Responsive (جوال/تابلت/سطح مكتب).
- Breadcrumbs في صفحات التفاصيل.
- Confirm قبل أي حذف/أرشفة.
- Toast للنجاح (موجود في `layouts/app.blade.php`).
- Empty/Loading/Error states.
- عدم إخفاء الأخطاء: رسائل التحقق عربية واضحة.
- لا مكتبات JS جديدة — الاعتماد على `resources/js/app.js` الحالي + JS بسيط داخل الصفحات.
- عدم الاعتماد على إخفاء عناصر الواجهة كوسيلة حماية.

---

# 10. مصفوفة الاختبارات (QA)

| الملف | التغطية |
|---|---|
| `WorkHoursTest` | CRUD، تعارض الأوقات، العزل، صلاحيات، نطاق own |
| `HafizExamMonthsTest` | شبكة 12 شهراً، تفاصيل الشهر، سنة/شهر غير صالح، العزل |
| `IjazahWeeksTest` | 4 أسابيع، القيد الفريد، التحقق، النطاق، عدم تأثر الإكمال |
| `ShariaCoursesTest` | CRUD، طلاب مستقلون، حضور/غياب، النسب، النطاق، Cascade |
| `QuranPagesTest` | التخطيط (ص1/ص2/ص604)، عبر السور، المدى، المقدار، التوافق |
| `BrandingTest` (اختياري) | `config('app.name')` = الاسم الكامل في الصفحات الرئيسية |

- تُشغَّل عبر `composer test` أو `php artisan test --filter=...`.
- يُمنع اعتبار الميزة مكتملة دون اختباراتها.
- إضافة بيانات تجريبية في `DatabaseSeeder` للميزات الأربع (ساعات، أسابيع إجازة، دورة شرعية، صفحات).

---

# 11. خارج النطاق (Out of Scope)

- ربط ساعات العمل بالرواتب أو الحضور المالي.
- بوابة طلاب/أولياء أمور للدورات الشرعية (يمكن إضافتها لاحقاً).
- دورة اعتماد للدورات الشرعية أو التسميع (تبقى تسجيلاً مباشراً).
- تعديل محرك الموافقات (§43) أو ميزات الجدول/الدرجات.
- ترجمة الواجهات للإنجليزية.
- تعديل شعارات المؤسسة أو ألوان الهوية.

---

# 12. ترتيب التنفيذ المقترح

| المرحلة | الميزة | السبب |
|---|---|---|
| 0 | اسم المؤسسة (§5) | تغيير مستقل سريع |
| 1 | بيانات صفحات القرآن + أمر التعبئة (§6.2) | أساس لميزة التسميع ويحتاج تحقق بيانات |
| 2 | تسميع الصفحات + عرض المصحف (§6.3–6.6) | يعتمد على المرحلة 1 |
| 3 | ساعات عمل المشرفين (§1) | مستقل |
| 4 | شبكة أشهر اختبارات الحفاظ (§2) | واجهة + متحكم |
| 5 | أسابيع برنامج الإجازة (§3) | يعتمد على فهم برنامج الإجازة الحالي |
| 6 | الدورة الشرعية (§4) | الأكبر حجماً |
| 7 | التدقيق النهائي + الاختبارات الشاملة + `pint` | ضمان الجودة |

بعد كل مرحلة: `php artisan test` + `vendor/bin/pint --dirty` + مراجعة يدوية للواجهة.

---

# 13. Definition of Done الإجمالي

- [ ] الميزات الست منفذة حسب القرارات المعيارية في §0.
- [ ] هجرات نظيفة تعمل وتتراجع دون أخطاء.
- [ ] صلاحيات الكتالوج محدثة وافتراضيات الأدوار مضبوطة.
- [ ] كل المسارات محمية بـ `role` + `permission` + فحص نطاق.
- [ ] واجهات RTL متجاوبة مع الحالات الفارغة والتأكيدات.
- [ ] تدقيق مسجل لكل العمليات الحساسة.
- [ ] اختبارات الميزات خضراء + `composer test` كامل ينجح.
- [ ] بيانات تجريبية محدثة في `DatabaseSeeder`.
- [ ] `vendor/bin/pint --dirty` نظيف.
- [ ] لا كسر لأي ميزة قائمة (اختبارات الانحدار الحالية تمر).

## End of Specification
