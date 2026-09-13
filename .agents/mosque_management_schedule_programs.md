# تخصصات الجداول والبرامج — Schedule Programs / Specializations

> مواصفة ميزة «تخصصات الجداول»: لكل جامع برامج (تخصصات) مستقلة — برنامج التحفيظ،
> برنامج الإجازة، اختبارات الحفظ، الدورات الشرعية، البرامج القرآنية، وأي برنامج
> يضيفه المدير بخصائصه. ولكل برنامج **فتراته** و**خصائصه المخصصة**، وتُربط الحصص
> بالبرنامج والفترة والدوام (الدوام الأول/الثاني).

---

# 1. الكيانات

## 1.1 البرنامج (Program)

```text
programs
- id                uuid
- tenant_id         → tenants (cascade)
- name              مثال: برنامج التحفيظ الصيفي
- code              فريد داخل الجامع (يُولَّد تلقائياً إذا تُرك فارغاً)
- type              tahfeez | ijazah | hafiz_exams | sharia_courses | quran | custom
- description       nullable
- color             nullable (hex)
- is_active         boolean (default true)
- sort_order        ترتيب الظهور
- created_at / updated_at
```

الأنواع (`App\Enums\ScheduleProgramType`) مع ألوان افتراضية وانتقال سريع للوحدة:

| النوع | الاسم | الوحدة المرتبطة |
|---|---|---|
| `tahfeez` | برنامج التحفيظ | التسميع |
| `ijazah` | برنامج الإجازة | برنامج الإجازة |
| `hafiz_exams` | اختبارات الحفظ | اختبارات الحفاظ |
| `sharia_courses` | الدورات الشرعية | الدورات الشرعية |
| `quran` | البرامج القرآنية | البرامج القرآنية |
| `custom` | برنامج مخصص | — |

## 1.2 الفترة (ProgramPeriod)

```text
program_periods
- id            uuid
- tenant_id     → tenants (cascade)
- program_id    → programs (cascade)
- name          مثال: الفترة الأولى
- starts_at     nullable (H:i)
- ends_at       nullable (H:i)
- sort_order
- is_active
- created_at / updated_at
```

القواعد:

- لكل برنامج فتراته الخاصة، ويمكن أن يكون له **فترة واحدة فقط** (مثال: الفترة
  الأولى في برنامج التحفيظ دون الثانية) أو أكثر.
- لا تتداخل فترتان مفعّلتان لهما أوقات كاملة داخل البرنامج نفسه.
- عند حذف فترة مرتبطة بحصص تُرفض العملية برسالة واضحة (يُعطَّل بدلها).
- حذف البرنامج يحذف فتراته وخصائصه، ويُرفض إذا كانت له حصص في الجداول.

## 1.3 الخصيصة (ProgramAttribute)

```text
program_attributes
- id            uuid
- tenant_id     → tenants (cascade)
- program_id    → programs (cascade)
- name          مثال: عدد الأجزاء الأسبوعية
- field_key     فريد داخل البرنامج (يُولَّد تلقائياً)
- field_type    text|textarea|number|date|boolean|select|multiselect
- required      boolean
- options_source manual|students (افتراضي manual)
- options       json nullable (لقوائم select/multiselect اليدوية)
- options_config json nullable (مرشّحات خيارات الطلاب)
- value         text nullable (قيمة البرنامج، تُخزَّن مُطبَّعة)
- sort_order
- is_active
- created_at / updated_at
```

- تُعاد استخدام أنواع الحقول من `App\Enums\CustomFieldType` (مع تسمياتها العربية).
- القيم: boolean → `'1'|'0'`، multiselect → JSON array، والباقي نص.
- التحقق: الحقول المطلوبة لا تقبل الفراغ، وقيم القوائم محصورة في `options`.

## 1.4 مصادر الخيارات (options_source)

لخصائص `select`/`multiselect` مصدران (`App\Enums\AttributeOptionSource`):

1. **كتابة يدوية (`manual`)** — كما كان: قائمة نصوص في `options`.
2. **اختيار من الطلاب (`students`)** — تُحسب الخيارات لحظياً من جدول `students`
   داخل الجامع نفسه، مع مرشّحات في `options_config`:

| المرشّح | القيم | الأثر |
|---|---|---|
| `student_status` | `active` (افتراضي) \| `all` | حالة الطالب |
| `gender` | `all` \| `male` \| `female` | الجنس |
| `age_from` / `age_to` | أعداد صحيحة | العمر من `birth_date` (من/إلى، شامل) |
| `juz_from` / `juz_to` | 0–30 | عدد الأجزاء المحفوظة (30 = خاتم القرآن) |
| `classroom_id` | uuid أو فراغ | الصف |
| `label_mode` | `name` \| `name_age` \| `name_juz` \| `name_age_juz` | وسم الخيار (الاسم ومعه الصفات) |

- عند `students` يُخزَّن `options` فارغاً وتبقى `options_config` مصدر الحقيقة،
  ويُحسب الوسم من `ProgramService::resolvedOptions()` / `studentOptions()`.
- يُتجاهل الدوام النشط عند توليد الخيارات (كل طلاب الجامع) ليطابق ما يراه النموذج.
- مرشّحا «العمر» و«الأجزاء» يُتحققان منطقياً (`من ≤ إلى`) برسالة عربية.
- `classroom_id` يجب أن يخص الجامع نفسه، وقيم القوائم النهائية محصورة
  بالخيارات المحسوبة (يرفض الخادم أي قيمة خارجها).
- الواجهة: خانة الخيارات تتيح «كتابة يدوية» أو «اختيار من الطلاب» مع لوحة
  المرشّحات، وخانة القيمة تصبح قائمة اختيار/اختيار متعدد مبنية على الخيارات
  الفعلية (يدوية أو محسوبة من الطلاب).
- API: `ProgramResource` يُعيد `options` (المحسوبة)، `options_source`،
  و`options_config` لكل خصيصة.

## 1.5 امتداد الحصص (schedules)

```text
schedules + program_id, program_period_id, study_session_id (nullable)
subject_id ← أصبح nullable (حصة برنامج بلا مادة)
```

- اختيار فترة يملأ وقت البداية/النهاية تلقائياً من أوقات الفترة (واجهة + خادم).
- الفترة يجب أن تنتمي للبرنامج المختار، والبرنامج/الفترة/الدوام داخل الجامع نفسه.
- `subject_id` مطلوب فقط عند غياب البرنامج.
- الدوام النشط في الشريط العلوي يرشّح الجداول أيضاً (مثل الطلاب والأساتذة).

## 1.6 تخصيص البرامج حسب الدوام (program_study_session)

```text
program_study_session
- program_id       → programs (cascade)
- study_session_id → study_sessions (cascade)
- primary(program_id, study_session_id)
```

- لكل دوام برامجه المتاحة: مثال — الدوام الأول للتحفيظ والإجازة، والثاني
  للتسميع فقط، وهكذا (أو الدورات الشرعية).
- الدوام بلا ارتباطات = كل البرامج المفعّلة متاحة (متوافق مع البيانات القديمة).
- واجهة `/admin/sessions`: لكل دوام قائمة اختيار البرامج المتاحة وعرضها،
  ويُحفظ عبر `POST /admin/sessions/{session}/programs` بصلاحية `sessions.update`.
- نماذج الجداول (إضافة/توليد/فلترة) تُرشّح قائمة البرامج حسب الدوام النشط أو
  المختار في النموذج (JavaScript عبر خريطة `sessionProgramMap`)، والخادم يرفض
  أي برنامج غير متاح للدوام في `ResolveScheduleProgramAction` (ويب + API).
- صفحة `/admin/programs` تعرض دوامات كل برنامج («كل الدوامات» عند غياب التخصيص).
- API: `GET /api/v1/admin/schedules?study_session_id=` و
  `GET /api/v1/admin/programs?study_session_id=` يرشّحان البرامج،
  و`ProgramResource` يعرض `study_sessions`.

---

# 2. البرامج الافتراضية

`ProgramService::provisionTenantPrograms()` يُنشئ لكل جامع (جديد أو قائم) البرامج
الخمسة بشكل idempotent: التحفيظ، الإجازة، اختبارات الحفظ، الدورات الشرعية،
البرامج القرآنية. قابلة للتعديل والتعطيل والحذف، ويمكن إضافة برامج مخصصة.

- يُستدعى عند إنشاء جامع من لوحة مدير الجوامع (`MosqueController::store`).
- Migration `2026_09_13_000006` تزرعها للجوامع الموجودة مسبقاً.
- البذور التجريبية (`DatabaseSeeder`) تنشئ للبرامج الخمسة في «جامع النور» فتراتها
  وجدولاً أسبوعياً (الأحد–الخميس) للفترة الأولى من كل برنامج، مع الفترة الثانية
  لبرنامج التحفيظ فقط.

---

# 3. الصلاحيات

| الصلاحية | الوصف | نطاق المدير الافتراضي |
|---|---|---|
| `programs.view` | مشاهدة البرامج والتخصصات | `mosque` |
| `programs.create` | إضافة برنامج/تخصص | `mosque` |
| `programs.update` | تعديل برنامج/تخصص (والفترات والخصائص) | `mosque` |
| `programs.delete` | حذف برنامج/تخصص | `mosque` |

- كل المسارات (ويب + API) محمية بـ `permission:programs.*`.
- المعلم يرى البرنامج والفترة داخل جدوله عبر `schedule.view` دون صلاحيات إدارة.
- Migration `2026_09_13_000006` تمنح الصلاحيات لأدوار مديري الجوامع القائمة.

---

# 4. الواجهات

## ويب (المدير)

- `GET /admin/programs` — بطاقات البرامج مع عدد الفترات والخصائص والحصص، وفلترة
  بالاسم والنوع والحالة.
- `GET /admin/programs/create` + `POST /admin/programs` — نموذج الإنشاء مع مكرّرات
  الفترات والخصائص (JavaScript خفيف).
- `GET /admin/programs/{program}/edit` + `PATCH` — تعديل ومزامنة الفترات/الخصائص.
- `DELETE /admin/programs/{program}` — محظور عند وجود حصص.
- `GET /admin/schedules` — فلترة بالبرنامج والدوام، ونموذج إنشاء يختار البرنامج أولاً
  ثم الفترة (تعبئة الأوقات تلقائياً) والدوام.
- `POST /admin/schedules/generate` — **توليد جدول أسبوعي للفترة**: يختار المدير
  البرنامج ثم الفترة المطلوبة فقط (مثال: الفترة الأولى في برنامج التحفيظ دون
  الثانية)، والدوام، والصف/الشعبة، والمعلم، وأيام الأسبوع (افتراضياً الأحد–الخميس).
  تُنسخ الأوقات من الفترة، وتُتخطى الصفوف المكررة عند إعادة التوليد، ويُرفض
  تعارض المعلم أو الصف/الشعبة مع تراجع كامل عن الدفعة.
- شريط جانبي: «البرامج والتخصصات» بجوار «الجداول الدراسية» (بصلاحية `programs.view`).
- جدول المعلم يعرض عمودي البرنامج والفترة.

## API v1 (المدير)

```text
GET    /api/v1/admin/programs
GET    /api/v1/admin/programs/{program}
POST   /api/v1/admin/programs
PATCH  /api/v1/admin/programs/{program}
DELETE /api/v1/admin/programs/{program}
POST   /api/v1/admin/schedules/generate
```

- `GET /api/v1/admin/schedules` يُعيد أيضاً `programs` (مع فتراتها) لنماذج التطبيق.
- `POST /api/v1/admin/schedules` يقبل `program_id` و`program_period_id` و
  `study_session_id`، ويستنتج الأوقات من الفترة عند غيابها.
- `POST /api/v1/admin/schedules/generate` يقبل `days` (مصفوفة أيام) ويعيد
  `{created, skipped}` بنفس قواعد الويب.
- `ScheduleResource` يعرض `program` و`program_period` و`study_session`.

---

# 5. Definition of Done

- [x] زرع البرامج الخمسة تلقائياً لكل جامع (جديد وقائم) بشكل idempotent.
- [x] إنشاء/تعديل/حذف برنامج مع فترات وخصائص مخصصة.
- [x] منع تداخل الفترات ومنع حذف فترة/برنامج مرتبط بحصص.
- [x] التحقق من الحقول المطلوبة وقوائم الخيارات.
- [x] ربط الحصة بالبرنامج + الفترة + الدوام، مع تعبئة الأوقات من الفترة.
- [x] المادة اختيارية عند وجود برنامج.
- [x] ترشيح الجداول بالبرنامج وبالدوام النشط.
- [x] عرض البرنامج والفترة في جدول المعلم وفي API.
- [x] تخصيص البرامج المتاحة لكل دوام (`program_study_session`) وترشيح النماذج
  والـ API حسب الدوام، مع رفض الخادم لأي برنامج غير متاح للدوام.
- [x] صلاحيات `programs.*` وحراسة كل المسارات + backfill للأدوار القائمة.
- [x] عزل كامل بين الجوامع (404 للموارد الخارجية).
- [x] خيارات الخصائص: كتابة يدوية أو اختيار من الطلاب مع مرشّحات
  (الحالة، الجنس، العمر من/إلى، الأجزاء من/إلى، الصف) ووسم مخصّص.
- [x] إصلاح انهيار `tenant_id` الفارغ لمدير الجوامع داخل جامع (ويب + API).
- [x] توليد جدول أسبوعي لبرنامج/فترة عبر عدة أيام (مثال: الفترة الأولى في التحفيظ دون الثانية).
- [x] تخطي الصفوف المكررة (idempotent) ورفض تعارض المعلم/الصف-الشعبة مع تراجع كامل.
- [x] بذور تجريبية: فترات وجداول أسبوعية للبرامج الخمسة في «جامع النور».
- [x] اختبارات `tests/Feature/ScheduleProgramsTest.php` (21 اختباراً) و
  `tests/Feature/ScheduleGenerationTest.php` (15 اختباراً).
