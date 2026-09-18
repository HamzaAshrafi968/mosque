# دليل الجدول الدراسي والاختبارات الإلكترونية

> هذا المستند يشرح بالتفصيل كيف يعمل نظام **الجدول الدراسي (Timetable)** وكيف يعمل نظام **الاختبارات الإلكترونية والأتمتة (Automated Exams)** في المشروع، مع مسارات الملفات وأرقام الأسطر لأهم المنطق البرمجي.

---

## نظرة عامة سريعة

| الجزء | الكيان الأساسي | الخدمة المسؤولة | أين تُدار الواجهة |
|---|---|---|---|
| الجدول الدراسي | `ScheduleSlot` | `ScheduleConflictService` + `SessionService` | `/timetable` (إدارة) و`/portal/schedule` (طالب) و`/portal/teacher/schedule` (أستاذ) |
| الاختبارات | `Exam` + `ExamQuestion` + `ExamAttempt` + `ExamAnswer` | `ExamService` | `/exams` (إدارة/أستاذ) و`/portal/exams` (طالب) و`/portal/teacher/exams` (أستاذ) |

نقطة مهمة: **لا يوجد مولّد جدول تلقائي** ولا بنك أسئلة/اختيار عشوائي. "الأتمتة" في هذا المشروع تعني حصراً:
- فحص التعارضات آلياً عند إدخال أي حصة.
- التصحيح الآلي للامتحان + المؤقت + التسليم التلقائي.

---

# الجزء الأول: الجدول الدراسي (Timetable)

## 1. المفهوم والبنية

لا يوجد موديل اسمه `Schedule` أو `Timetable`. الكيان الأساسي هو **`ScheduleSlot`**: حصة أسبوعية متكررة تنتمي لشعبة (`Section`) وتحمل المادة والأستاذ الخاصين بها.

```
Section (شعبة)
   └── ScheduleSlot (حصة أسبوعية: يوم + من/إلى + مادة + أستاذ)
           └── ClassSession (استثناء ليوم واحد: إلغاء / تأجيل)
```

- **`ScheduleSlot`** = القالب المتكرر كل أسبوع.
- **`ClassSession`** = استثناء لحصة في تاريخ محدد (ملغاة أو مؤجلة). لا يوجد صف لكل أسبوع.
- **لا يوجد فصل دراسي/قاعة per-slot**: جدول `rooms` أُلغي (`database/migrations/2026_08_19_000005_drop_rooms_table.php`)، والمكان الوحيد هو `sections.location` (نص حر).
- الأيام: `0=الأحد ... 6=السبت` (الثابت `ScheduleConflictService::DAY_NAMES` في `app/Services/ScheduleConflictService.php:30`).

## 2. مخطط قاعدة البيانات

### 2.1 جدول `schedule_slots`
`database/migrations/2026_08_16_120210_create_schedule_slots_table.php` + التعديلات اللاحقة:

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | uuid PK | |
| `section_id` | uuid FK → sections | `cascadeOnDelete` |
| `subject_id` | uuid FK → subjects nullable | أُضيف في `2026_08_19_120000_move_subject_teacher_to_schedule_slots.php` |
| `teacher_id` | uuid FK → teachers nullable | نفس التعديل، `nullOnDelete` |
| `day_of_week` | tinyint (0..6) | |
| `start_time` / `end_time` | time | |
| timestamps | | |

قيد فريد: `(section_id, day_of_week, start_time, end_time)` من `2026_08_17_000000_add_schedule_slots_unique_window.php`.
فهارس: `(day_of_week, start_time, end_time)`, `(teacher_id, day_of_week)`, `(subject_id)`, `(section_id)`.

> ملاحظة: المادة والأستاذ كانا في `sections` ثم نُقلا إلى كل حصة على حدة، فأصبح بإمكان الشعبة الواحدة دراسة أكثر من مادة مع أكثر من أستاذ.

### 2.2 جدول `class_sessions` (الاستثناءات)
`database/migrations/2026_08_17_000001_create_class_sessions_table.php`:

| العمود | النوع | ملاحظات |
|---|---|---|
| `id` | uuid PK | |
| `school_id` | FK → schools | |
| `section_id` | FK → sections | |
| `schedule_slot_id` | FK → schedule_slots nullable | `nullOnDelete` |
| `teacher_id` | FK → teachers nullable | |
| `date` | date | تاريخ الحصة الأصلية |
| `start_time` / `end_time` | time | |
| `status` | string | `scheduled / postponed / cancelled / completed` |
| `reason` | text nullable | سبب الإلغاء/التأجيل |
| `postponed_date` / `postponed_start_time` / `postponed_end_time` | date/time nullable | الموعد الجديد |
| `changed_by` | FK → users nullable | من قام بالتغيير |

قيد فريد: `(section_id, schedule_slot_id, date)`.
الـ enum: `app/Enums/SessionStatus.php`.

## 3. الموديلات والعلاقات

| الموديل | المسار | العلاقات |
|---|---|---|
| `ScheduleSlot` | `app/Models/ScheduleSlot.php` | `section()` (16)، `subject()` (21)، `teacher()` (26) |
| `ClassSession` | `app/Models/ClassSession.php` | `section()` (41)، `scheduleSlot()` (46)، `teacher()` (51)، `changer()` (56)، `isChanged()` (61) |
| `Section` | `app/Models/Section.php:64` | `scheduleSlots()` HasMany |
| `Teacher` | `app/Models/Teacher.php:52` | `scheduleSlots()` HasMany + `teachesSubject()` (69) |
| `Subject` | `app/Models/Subject.php:31` | `scheduleSlots()` + `teachingTeacherIds()` (95) |

`Subject::teachingTeacherIds()` (الأسطر 95–106) تجمع أساتذة المادة من ثلاث جهات: الإسناد الرسمي (pivot) + أساتذة الدروس + أساتذة الحصص الحالية. هذه هي القائمة التي تظهر في نموذج إضافة الحصة.

## 4. كيف تُنشأ الحصة (الجدول)؟

الحصة = `(section_id, subject_id, teacher_id, day_of_week, start_time, end_time)`.

### المسار أ — الإضافة السريعة من صفحة الجدول (الأكثر استخداماً)

الملف: `app/Livewire/Timetable/SlotCreate.php` — دالة `save()` (الأسطر 169–221):

1. التحقق من الصلاحية `sections.create` (سطر 174) ومن أن كل المعرّفات تابعة لنفس المدرسة (176–183).
2. تجميع مصفوفة الحصة (188–194).
3. أخذ **قفل مسمّى** على الأستاذ وحساب المستخدم: `withScheduleLock(['teacher:...', 'user:...'])` (197–201).
4. داخل `DB::transaction` (203):
   - `ScheduleConflictService::assertSlotForSection($section, $slot)` (205) → يرمي `ScheduleConflictException` عند أي تعارض.
   - `$section->scheduleSlots()->create($slot)` (207).
5. عند التعارض: تُعرض الرسالة في حقل `time` (211–215). عند النجاح: إشعار + حدث `slot-created` (217–220).

تعبئة تلقائية ذكية: عند اختيار الشعبة يتم جلب المادة والأستاذ من دورة الشعبة (`prefillCourseData()`، الأسطر 96–110)، ويمكن تعديلهما يدوياً. قائمة الأساتذة محدودة بمن يدرّس المادة المختارة (`teacherOptions()`، 152–167).

نقاط الدخول في الواجهة: `resources/views/livewire/timetable/index.blade.php` — زر «+ حصة» لكل خلية (216–220) وزر «+ حصة لهذا اليوم» (95–99).

### المسار ب — إنشاء/تعديل الشعبة عبر الـ API

`app/Http/Controllers/Api/SectionController.php`:

- `store()` (61–101): يستقبل مصفوفة `schedule[]`، يقفل كل الأساتذة المعنيين (79–80)، ثم `assertSlotsSchedule()` (83) داخل معاملة، ثم ينشئ الشعبة والحصص (85–92).
- `update()` (114–146): يفحص الجدول الجديد (130)، ثم **يحذف كل الحصص القديمة ويعيد إنشاءها** (133–137).

### المسار ج — نسخ الجدول عند إغلاق الدورة

`app/Services/CourseCloseService.php:157-165` — عند فتح دورة جديدة تُنسخ كل الحصص (المادة، الأستاذ، اليوم، الوقت) إلى شعبة الدورة الجديدة.

### المسار د — Seeder/الاختبارات

`database/seeders/DemoSeeder.php:244-251` و`tests/Pest.php:107-111` (حصة أحد 16:00–18:00).

## 5. كشف التعارضات — `ScheduleConflictService`

الملف: `app/Services/ScheduleConflictService.php` (651 سطراً) وهو **المصدر الوحيد للحقيقة** لكل فحوصات التعارض. التوثيق الداخلي (الأسطر 17–27) يُلزم كل نقاط الكتابة بالمرور من هنا تحت قفل + معاملة.

### قاعدة التداخل الزمني

```
يتداخل الوقتان إذا: A.start < B.end  &&  B.start < A.end
```
(`timeRangesOverlap()` الأسطر 605–609، و`timesOverlap()` 625–633). حصتان متجاورتان (16:00–18:00 و18:00–20:00) **لا** تتعارضان.

### أنواع التعارض المفحوصة

| النوع | الدالة | الشرح |
|---|---|---|
| تداخل داخلي | `internalOverlapConflicts()` (188–218) | حصتان متداخلتان داخل نفس الجدول المقترح (نفس الشعبة) |
| تعارض الأستاذ | `teacherConflicts()` (169–180) | الأستاذ لديه حصة أخرى متداخلة في أي شعبة بنفس المدرسة (يُستثنى جدول الشعبة نفسها عند التعديل) |
| تعارض عبر المدارس | `crossSchoolConflicts()` (225–257) | نفس حساب المستخدم له ملف أستاذ في معهد آخر ووقت متداخل (بدون Global Scopes) |
| تعارض الطالب | `studentHasConflict()` (38–54) | عند التسجيل: وقت حصص شعبة جديدة يتداخل مع حصص شعب الطالب المسجّلة |
| تعارض التأجيل | `postponementConflicts()` (484–544) | الموعد الجديد للأستاذ يتداخل مع حصصه الأسبوعية أو مع تأجيلات أخرى |

### الدوال العامة (Entry Points)

| الدالة | السطر | الاستخدام |
|---|---|---|
| `assertSlotForSection(Section, array $slot)` | 426 | إضافة حصة واحدة → يرمي استثناء |
| `assertSlotsSchedule(Collection, ?excludeSectionId)` | 367 | جدول كامل (إنشاء/تعديل شعبة) → يرمي استثناء |
| `sectionSlotConflicts()` | 382 | نسخة ترجع الرسائل بدون رمي |
| `slotsScheduleConflicts()` | 317 | فحص شامل: تداخل داخلي + كل أستاذ + عبر المدارس |
| `studentHasConflict()` / `studentConflictDetails()` | 38 / 62 | عند تسجيل الطالب (يُستدعى من `EnrollmentService`) |
| `findTeacherConflicts(teacherIds)` | 266 | عرض تعارضات أستاذ في بوابته |
| `withScheduleLock(keys, callback)` | 441 | قفل MySQL مسمّى (انظر الفقرة 8) |

### دمج التعارض مع التسجيل

`app/Services/EnrollmentService.php`:
- `assertCanJoin()` (124–146) يفحص تعارض الطالب + السعة.
- يُستدعى في `request()` (43)، `approve()` (86)، `enroll()` (177)، ونقل الطلاب `SectionTransferService` (70–77 و135–153).

## 6. إلغاء/تأجيل حصة واحدة — `ClassSession`

الخدمة: `app/Services/SessionService.php` (205 أسطر).

| العملية | السطر | المنطق |
|---|---|---|
| `cancel()` | 26–47 | يتحقق أن التاريخ يطابق يوم الحصة (`guardDateMatchesSlot` 197–204) ثم ينشئ/يحدّث `ClassSession` بحالة `cancelled` + إشعار |
| `postpone()` | 52–97 | نفس التحقق + قفل + `postponeConflicts()` (72) ويرمي عند التعارض + حفظ الموعد الجديد + إشعار |
| `restore()` | 102–117 | حذف الاستثناء وإرجاع الحصة لطبيعتها |
| `postponeConflicts()` | 125–136 | يفوّض لـ `ScheduleConflictService` |
| `notify()` | 177–195 | إشعار الإدارة والطلاب المسجّلين عبر `SessionChangedNotification` |

الواجهة: `app/Livewire/Timetable/SessionActions.php` — فتح التأجيل (87–95)، تحديث التعارضات لحظياً `updated()` (97–108)، إلغاء (133–149)، تأجيل (151–185)، استرجاع (187–195). وفي بوابة الأستاذ يُتحقق أن الحصة له عبر `TeacherPortalService::profileIds()` (69–75).

## 7. الواجهات والمسارات

### الويب (`routes/web.php`)

| المسار | المكوّن | السطر |
|---|---|---|
| `GET /timetable` | `App\Livewire\Timetable\Index` — جدول الحضور | 127 |
| `GET /portal/schedule` | `Portal\StudentSchedule` | 179 |
| `GET /portal/teacher/schedule` | `Portal\TeacherSchedule` | 184 |

### الـ API (`routes/api.php` — كلها تحت `auth:sanctum`)

| المسار | الوجهة | السطر |
|---|---|---|
| `GET /timetable` | `TimetableController@index` (قراءة فقط) | 114 |
| `GET /sections/{section}/schedule-slots` | `SectionController@scheduleSlots` | 111 |
| `GET /teachers/{teacher}/schedule` | `TeacherController@schedule` | 77 |
| `POST /sections` و`PUT /sections/{section}` | كتابة الجدول ضمن الشعبة | 107 / 109 |
| `GET /portal/schedule` | جدول الطالب | 246 |
| `GET /portal/teacher/schedule` | جدول الأستاذ | 256 |

> لا يوجد Endpoint مستقل لإضافة/حذف حصة واحدة عبر الـ API؛ الكتابة تمر عبر إنشاء/تعديل الشعبة.

### مكوّنات العرض الرئيسية

| الملف | الوظيفة |
|---|---|
| `app/Livewire/Timetable/Index.php` | مصفوفة أسبوعية (شعبة × يوم) + عرض يومي + تمييز التعارضات (185–250)، فلترة بالصف/الشعبة/المادة/الأستاذ/الوقت |
| `app/Livewire/Timetable/SlotCreate.php` | نافذة إضافة حصة |
| `app/Livewire/Timetable/SlotDetails.php` | تفاصيل حصة + الطلاب المسجّلون والأرصدة |
| `app/Livewire/Timetable/SessionActions.php` | إلغاء/تأجيل/استرجاع حصة |
| `app/Livewire/Portal/TeacherSchedule.php` | جدول الأسبوع للأستاذ مع تعارضاته (`findTeacherConflicts` 33–42) |
| `app/Livewire/Portal/StudentSchedule.php` | جدول الطالب + التأجيلات/الإلغاءات القادمة |

## 8. الحماية من التزامن (Concurrency)

`ScheduleConflictService::withScheduleLock()` (الأسطر 441–469):
- على MySQL: `GET_LOCK` باسم مشتق `schedule_` + `sha256(key)` ثم `RELEASE_LOCK`.
- على SQLite (بيئة الاختبارات): يُنفَّذ مباشرة بدون قفل.
- المفاتيح المستخدمة: `teacher:{id}` و`user:{user_id}` (في `SlotCreate` 197–201، `SectionController` 79–95/126–139، `SessionService` 64–69).

الهدف: منع سباق حفظ جدولين لنفس الأستاذ في نفس اللحظة. بالإضافة لذلك يوجد قيد فريد على مستوى قاعدة البيانات يمنع تكرار نفس نافذة الشعبة، ويُترجم في الواجهة إلى رسالة خطأ (`tests/Feature/ScheduleConflictPreventionTest.php:121-127`).

## 9. الاختبارات الخاصة بالجدول

| الملف | التغطية |
|---|---|
| `tests/Unit/ScheduleConflictServiceTest.php` | التداخل الجزئي/الكامل/المتجاور، تعارض الأستاذ، عبر المدارس، التأجيل |
| `tests/Feature/ScheduleConflictPreventionTest.php` | رفض الإضافة من `SlotCreate` ومن API، وقيد قاعدة البيانات |
| `tests/Feature/SessionScheduleTest.php` | إلغاء/تأجيل/استرجاع، شرط تطابق اليوم، معاينة التعارض لحظياً |
| `tests/Feature/SectionGradeTimetableTest.php` | فلترة الجدول بالصف وشارة تعارض التسجيل |

---

# الجزء الثاني: الاختبارات الإلكترونية والأتمتة

## 1. المفهوم

«المذاكرة/الكويز» ليست موديلاً مستقلاً؛ هي `Exam` بقيمة `kind = 'quiz'`. لا يوجد **بنك أسئلة** ولا اختيار عشوائي ولا توليد أسئلة. الأسئلة تُكتب يدوياً لكل امتحان، والأتمتة هي:

1. **التصحيح الآلي** عند التسليم (عدا المقالي).
2. **المؤقت** والتسليم التلقائي عند انتهاء الوقت.
3. **التحقق** من مجموع العلامات قبل النشر.

الكيانات الأربعة: `Exam` → `ExamQuestion` → `ExamAttempt` → `ExamAnswer`.

## 2. الموديلات والـ Enums

| الموديل | المسار | أهم الحقول |
|---|---|---|
| `Exam` | `app/Models/Exam.php` | `school_id, grade_id, subject_id, lesson_id, teacher_id, title, kind, exam_date, duration_minutes, total_marks, passing_marks, mode, attachment_key, status, published_at` |
| `ExamQuestion` | `app/Models/ExamQuestion.php` | `exam_id, type, text, marks, options(JSON), correct_answer, sort_order` |
| `ExamAttempt` | `app/Models/ExamAttempt.php` | `exam_id, student_id, started_at, submitted_at, score, status` |
| `ExamAnswer` | `app/Models/ExamAnswer.php` | `attempt_id, question_id, answer_text, selected_options(JSON), is_correct, marks_awarded` |

علاقات مهمة في `Exam`: `questions()` (83)، `attempts()` (88)، `schools()` BelongsToMany عبر جدول `exam_school` (68) للامتحانات المشتركة، `visibleForStudent()` (137) لتحديد ما يراه الطالب.

### الـ Enums

| Enum | القيم |
|---|---|
| `QuestionType` (`app/Enums/QuestionType.php`) | `mcq`, `checkbox`, `true_false`, `short`, `essay` |
| `ExamKind` | `exam`, `quiz` |
| `ExamStatus` | `draft`, `published`, `closed` |
| `DeliveryMode` | `onsite`, `online`, `hybrid` |

## 3. مخطط قاعدة البيانات

`database/migrations/2026_08_16_120234_create_exams_tables.php`:

- **`exams`**: uuid PK، `school_id`، `subject_id`، `lesson_id` nullable، `teacher_id` nullable، `title`، `exam_date` datetime nullable، `duration_minutes`، `total_marks` (افتراضي 100)، `passing_marks` nullable، `mode` (افتراضي onsite)، `status` (افتراضي draft)، `published_at`، softDeletes. لاحقاً أُضيف `kind`، `grade_id`، وملف الـ PDF (`attachment_key/name`).
- **`exam_questions`**: `exam_id`، `type`، `text`، `marks`، `options` JSON، `correct_answer` نص، `sort_order`.
- **`exam_attempts`**: `exam_id`، `student_id`، `started_at`، `submitted_at`، `score`، `status` (`in_progress | submitted | graded`). فهرس `(exam_id, student_id)`.
- **`exam_answers`**: `attempt_id`، `question_id`، `answer_text`، `selected_options` JSON، `is_correct`، `marks_awarded`.
- **`exam_school`** (pivot): `exam_id` + `school_id` — للامتحان المشترك بين المعاهد (`2026_08_19_095604_create_exam_school_table.php`).
- **`teachers.default_exam_school_ids`** JSON — يتذكّر آخر معاهد اختارها الأستاذ.

## 4. دورة حياة الامتحان

```
draft ──(نشر)──► published ──► (استخدام/إغلاق) closed
```

### الإنشاء (draft دائماً)
`ExamService::create()` (`app/Services/ExamService.php:21-24`) يفرض `status = draft` مهما كانت المدخلات.

### النشر — `ExamService::publish()` (43–64)
شروط إلزامية:
1. يوجد سؤال واحد على الأقل **أو** ملف PDF مرفق (45–47).
2. للامتحان الإلكتروني/الهجين: مجموع علامات الأسئلة = `total_marks` بفارق ≤ 0.01 (49–55).
3. `passing_marks ≤ total_marks` (57–59).
ثم يضبط `published` + `published_at`.

### بدء المحاولة — `ExamService::start()` (66–98)
- يجب أن يكون منشوراً، و`exam_date` ليس في المستقبل.
- **محاولة واحدة فقط** لكل طالب (80–90): إن وُجدت محاولة `in_progress` غير منتهية يتم استئنافها؛ إن انتهى وقتها يُرفض؛ وإن كانت مكتملة يُرفض («أديت هذا الامتحان مسبقاً»).

### التسليم — `ExamService::submit()` (103–160)
- يقبل الحالة `in_progress` أو `submitted` (لمنع فقدان النتيجة إذا لم تكتمل).
- **الموعد النهائي من جهة السيرفر** = `started_at + duration_minutes + 60 ثانية سماحية` (111–118).
- داخل معاملة: يمر على كل أسئلة الامتحان، يصحّح، يكتب صف `ExamAnswer` لكل سؤال، يجمع العلامات، ويضبط `score` و`status = graded` (120–159).
- إذا كان الامتحان **ورقياً/PDF بلا أسئلة**: `status = submitted` و`score = null` بانتظار التصحيح اليدوي (123–131).

### التصحيح اليدوي — `manualGrade()` (226–238)
يتحقق أن العلامة بين 0 و`total_marks` ثم يضبط `score` و`status = graded`. يُستخدم للامتحانات الورقية والمقالي.

## 5. الأتمتة: التصحيح الآلي

المنطق كله في `ExamService::gradeQuestion()` (268–303) مع دوال مساعدة. **لا توجد علامات سالبة**، والجواب الفارغ = صفر.

| نوع السؤال | قاعدة التصحيح | الدالة |
|---|---|---|
| `mcq` (اختيار واحد) | مقارنة نصية مباشرة بعد trim | `gradeQuestion` 287–295 |
| `mcq` (أكثر من إجابة، صيغة قديمة) | مقارنة مجموعات + تصحيح جزئي | `gradeMultiMcq` 339–360 |
| `checkbox` (متعدد) | كل الخيارات الصحيحة مطلوبة للدرجة الكاملة، وإلا **(المختار الصحيح ÷ مجموع الصحيح) × العلامة** | `gradeCheckbox` 311–333 |
| `true_false` | مقارنة نصية `'true'/'false'` | `gradeQuestion` |
| `short` | مقارنة **غير حساسة لحالة الأحرف** عبر `mb_strtolower` | 297–300 |
| `essay` | لا تصحيح آلي — يدوي من صفحة النتائج | — |

النتيجة: `attempt.score = مجموع marks_awarded` (مقربة لخانتين)، والنجاح = `score >= passing_marks` (`passed()` 243–256).

## 6. المؤقت والتسليم التلقائي

- من جهة العميل: Alpine.js `examTimer` في `resources/views/livewire/portal/my-exams.blade.php:173-195` يعدّ تنازلياً من `remainingSeconds` ويستدعي `$wire.submit()` عند الصفر.
- من جهة السيرفر: `remainingSeconds()` و`isExpired()` (176–188 و162–174)، والسماحية 60 ثانية في `submit()`.
- الطالب يرى ورقة النتيجة مع مفتاح التصحيح عبر `showResult()` في `app/Livewire/Portal/MyExams.php:94-107`.

## 7. الامتحانات المشتركة بين المعاهد

- الأستاذ يختار أكثر من معهد عند الإنشاء، وتُخزَّن في جدول `exam_school` عبر `ExamService::syncSchools()` (31–41) مع استثناء المعهد الأساسي.
- `Exam::visibleForStudent()` (137–166) يُظهر للطالب امتحانات مواده في معهده + امتحانات المعاهد المشتركة المطابقة **باسم المادة**.
- آخر اختيارات الأستاذ تُحفظ في `teachers.default_exam_school_ids` وتُستخدم كقيم افتراضية (`TeacherExamCreate.php:207-228`).

## 8. الصلاحيات والوصول

- Route Model Binding في `routes/web.php:62-86`: يسمح للإدارة/الأستاذ صاحب الملف في المعهد المخدوم/الطالب في معهد مخدوم، وإلا 404.
- `ExamController::authorizeStaff()` (`app/Http/Controllers/Api/ExamController.php:392-405`): admin/superadmin للمعهد أو أستاذ له وصول.
- `ExamPolicy` موجود (`app/Policies/ExamPolicy.php`) لكنه **غير مستدعى** حالياً في أي مكان (كود غير مفعّل).
- رفع PDF: نوع `pdf` فقط على قرص `private` (`attachPdf()` 190–210)، ويُبَث عبر `ExamAttachmentStreamController` بعد التحقق.

## 9. بوابات الخطط (Plans)

ميزة `exams.online` مطلوبة للوضع `online`/`hybrid` في:
`Livewire\Exams\Create.php:89-97`، `Livewire\Exams\Show.php:161-169`، `Portal\TeacherExamCreate.php:119-129`، `Api\ExamController.php:87-93` و`190-196`، `Api\PortalController.php:497-505`.

## 10. المسارات

### الويب
| المسار | المكوّن | السطر |
|---|---|---|
| `GET /exams` | `Exams\Index` | 157 |
| `GET /exams/create` | `Exams\Create` | 158 |
| `GET /exams/{exam}` | `Exams\Show` (إدارة الأسئلة والنتائج) | 159 |
| `GET /exams/{exam}/attachment` | بث PDF | 160 |
| `GET /portal/exams` | `Portal\MyExams` (أداء الامتحان) | 176 |
| `GET /portal/teacher/exams` | `Portal\TeacherExams` | 188 |
| `GET /portal/teacher/exams/create` | `Portal\TeacherExamCreate` | 189 |

### الـ API (تحت `auth:sanctum`)
| المسار | العملية |
|---|---|
| `GET/POST /exams` | عرض/إنشاء |
| `GET/PUT /exams/{exam}` | عرض/تعديل (التعديل ممنوع إن وُجدت محاولات) |
| `POST /exams/{exam}/publish` | نشر |
| `POST/DELETE /exams/{exam}/attachment` | رفع/حذف PDF |
| `POST /exams/{exam}/questions` | إضافة أسئلة دفعة واحدة (1–100) |
| `PUT/DELETE /exams/{exam}/questions/{question}` | تعديل/حذف سؤال |
| `POST /exams/{exam}/start` | بدء المحاولة (`throttle:exam-actions`) |
| `POST /exam-attempts/{attempt}/submit` | تسليم (`throttle:exam-actions`) |
| `POST /exams/{exam}/attempts/{attempt}/manual-grade` | تصحيح يدوي |
| `GET /exams/{exam}/results` | النتائج |
| `GET /portal/exams` / `GET /portal/attempts/{attempt}` | بوابة الطالب |
| `GET/POST /portal/teacher/exams` | بوابة الأستاذ |

محدّد المعدل `exam-actions` = 30/دقيقة لكل مستخدم/IP (`app/Providers/AppServiceProvider.php:35`).

---

# الجزء الثالث: كيف يحطّ الأستاذ الأسئلة؟

## 1. المسار الكامل خطوة بخطوة

1. الأستاذ يدخل `/portal/teacher/exams` ثم `/portal/teacher/exams/create`.
2. في `app/Livewire/Portal/TeacherExamCreate.php`:
   - يختار المعاهد (checkbox متعدد) — تُحفظ كاختيارات افتراضية لاحقاً.
   - المواد المتاحة محصورة بما يدرّسه فعلاً: تُجمع من الإسناد الرسمي + الدروس + حصص الجدول (`teacherSubjects()` 185–202 عبر `Subject::teachingTeacherIds`).
   - يحدد: النوع (امتحان/مذاكرة)، الصف، المادة، العنوان، التاريخ، المدة، العلامة الكلية، علامة النجاح، الوضع، وملف PDF اختياري.
   - عند الحفظ: `ExamService::create` (draft) + `syncSchools` + `attachPdf` (131–151)، ثم تحويل إلى صفحة `exams.show` (155).
3. في صفحة `app/Livewire/Exams/Show.php` (نفس الصفحة التي تستخدمها الإدارة):
   - يختار نوع الأسئلة وعددها (1–100) ويضغط «إنشاء الأسئلة» → `prepareBulk()` (353–370) يبني صفوفاً فارغة.
   - يملأ كل صف: النص، العلامة، الخيارات (A–D)، والإجابة الصحيحة.
   - يضغط حفظ → `saveBulk()` (383–481).
4. بعد اكتمال الأسئلة: زر النشر → `publish()` (313–325) → `ExamService::publish` بالشروط المذكورة أعلاه.
5. بعد النشر يظهر الامتحان للطلاب في `/portal/exams` ويبدأون المحاولة.

## 2. واجهة بناء الأسئلة الجماعية

الواجهة: `resources/views/livewire/exams/show.blade.php` (منطقة بناء الأسئلة 156–260، قائمة الأسئلة والمحرر المضمّن 262–375).

| نوع السؤال | ما يُدخله الأستاذ | ما يُخزَّن في `correct_answer` |
|---|---|---|
| `mcq` | نص السؤال + خياران إلى 4 + إجابة صحيحة يجب أن تطابق أحد الخيارات حرفياً | نص الخيار |
| `checkbox` | نص + خيارات + checkbox لأكثر من إجابة صحيحة | `json_encode([...])` |
| `true_false` | نص + اختيار صحيح/خطأ | `'true'` أو `'false'` |
| `short` | نص + نص متوقع اختياري | النص (المقارنة غير حساسة لحالة الأحرف) |
| `essay` | نص فقط | `null` — تصحيح يدوي |

## 3. التحقق من صحة الأسئلة (`saveBulk()`)

- نص السؤال مطلوب لكل صف (398–401).
- العلامة رقم ≥ 0 (403–408).
- `mcq/checkbox`: خياران على الأقل (421–424).
- `mcq`: الإجابة الصحيحة مطلوبة **ويجب أن تطابق أحد الخيارات** (426–433).
- `checkbox`: إجابة صحيحة واحدة على الأقل (440–443).
- `true_false`: القيمة `true` أو `false` (447–450).
- يُحفظ `sort_order = عدد الأسئلة الحالي + الترتيب` (464–475).

## 4. التعديل والحذف

- تعديل سؤال واحد: `editQuestion()` / `saveQuestion()` (`Exams\Show.php:189-311`).
- حذف سؤال: `deleteQuestion()` (483–487).
- **قفل مهم**: `canEdit()` (89–92) يمنع أي تعديل على الامتحان أو أسئلته بمجرد وجود محاولة طالب واحدة — للحفاظ على عدالة التصحيح.

## 5. عبر الـ API

- `POST /api/exams/{exam}/questions` → `ExamController::storeQuestions()` (250–279): دفعة من 1 إلى 100 سؤال من **نوع واحد** لكل طلب (`type` واحد للمصفوفة)، كل سؤال: `text`, `marks`, `options[]` اختياري، `correct_answer` اختياري.
- فرق عن الواجهة: الـ API لا يتحقق من أن إجابة `mcq` تطابق أحد الخيارات، ولا يمنع إضافة أسئلة بعد وجود محاولات.
- تعديل/حذف: `PUT/DELETE /exams/{exam}/questions/{question}` (والتعديل ممنوع بعد وجود محاولات، الأسطر 294–296).

## 6. النتائج والتقارير

- تصحيح تلقائي كامل عند التسليم، ويدوي للمقالي/الورقي.
- `app/Livewire/Exams/Show.php` يعرض نتائج الطلاب، التصحيح اليدوي (489–501)، ومراجعة إجابات الطالب (503–508).
- تقارير عامة: `app/Livewire/Reports/Academic.php:66-84` (متوسطات ونِسَب النجاح) و`Reports/Teachers.php:43-47`.
- عند إغلاق الدورة يُحفظ عدد الامتحانات في `CourseRecord.exams_count` (`CourseCloseService.php:228-236`).

---

# ملاحظات ومخاطر معروفة (يُنصح بمعالجتها)

1. **لا يوجد مولّد جدول تلقائي** — إدخال الحصص يدوي بالكامل (باستثناء النسخ عند إغلاق الدورة). إذا كان مطلوباً «جدول تلقائي» فهذه ميزة يجب بناؤها.
2. **`ExamPolicy` غير مستدعى** — الحماية الحالية عبر Route Binding و`authorizeStaff()` فقط.
3. **لا فحص صلاحيات `exams.*` على مسارات الويب** — مسارات `/exams` محمية بـ `auth` فقط (`routes/web.php:157-160`).
4. **`ExamController::submit` لا يتحقق أن المحاولة تخص الطالب المصادق** — ثغرة محتملة (المقارنة موجودة في `PortalController::showAttempt:114`).
5. **المحاولة المنتهية تبقى `in_progress`** إن لم يُسلّمها المتصفح — السماحية 60 ثانية فقط، وقد تُفقد النتيجة.
6. **تعليق migration `exam_questions.type` قديم** — يذكر 4 أنواع فقط ولا يذكر `checkbox`.
7. **`ScheduleSlot` لا يملك School Scope مباشراً** — النطاق يُشتق من الشعبة، وبعض الاستعلامات تستخدم `withoutGlobalScopes()` عمداً للفحص عبر المدارس.
8. **التعارضات على مستوى الأستاذ/الطالب محمية تطبيقياً فقط** — قفل MySQL المسمّى لا يعمل على SQLite، ويجب الالتزام بتمرير كل الكتابات عبر `ScheduleConflictService`.
