# 🕌 نظام إدارة عدة جوامع — Implementation Specification

## 1. الهدف

بناء نظام ويب احترافي لإدارة عدة جوامع/فروع، بحيث يكون لكل جامع بياناته المستقلة، مع وجود مدير مركزي للجوامع يملك الصلاحيات الكاملة، ومدير مستقل لكل جامع، وأساتذة بصلاحيات قابلة للتخصيص.

النظام يجب أن يدعم:

- Multi-Mosque / Multi-Branch
- RBAC + Fine-Grained Permissions
- صلاحيات على مستوى العملية CRUD
- صلاحيات على مستوى النطاق Scope
- صلاحيات على مستوى الحقل Field-Level Permissions
- Custom Fields ديناميكية
- Approval Workflows
- Audit Logs
- الصفوف والشعب والطلاب
- الأساتذة والجداول والحضور والامتحانات والدرجات والواجبات والدروس
- فصل كامل لبيانات كل جامع

---

# 2. Hierarchy

```text
مدير الجوامع / Super Admin
│
├── جامع 1
│   ├── مدير الجامع
│   ├── الأساتذة
│   ├── الصفوف
│   │   ├── شعبة A
│   │   └── شعبة B
│   └── الطلاب
│
├── جامع 2
│   ├── مدير الجامع
│   ├── الأساتذة
│   ├── الصفوف
│   └── الطلاب
│
└── جامع N
```

## قواعد العزل

- كل سجل تابع لجامع يجب أن يرتبط بـ `mosque_id`.
- مدير الجامع لا يرى إلا بيانات جامعه.
- الأستاذ يرى فقط البيانات التي تسمح بها صلاحياته ونطاقه.
- مدير الجوامع يستطيع الوصول إلى جميع الجوامع.
- يجب فرض العزل على مستوى Backend/Database، وليس الواجهة فقط.
- يمنع الاعتماد على إخفاء عناصر الواجهة كوسيلة أمنية.

---

# 3. الأدوار الأساسية

## 3.1 مدير الجوامع

صلاحيات كاملة على مستوى جميع الجوامع:

- إدارة الجوامع
- إدارة المستخدمين
- إدارة الأدوار
- إدارة الصلاحيات
- إدارة الطلاب
- إدارة الأساتذة
- إدارة الصفوف
- إدارة الشعب
- إدارة المواد
- إدارة الجداول
- اعتماد الجداول
- إدارة الحضور
- إدارة الامتحانات
- اعتماد الدرجات
- إدارة الواجبات
- إدارة الدروس
- الإعلانات
- الرسائل
- التقارير
- الحقول المخصصة
- Audit Log

## 3.2 مدير الجامع

يعمل ضمن جامع واحد فقط.

الصلاحيات الافتراضية:

- الطلاب: View/Create/Update/Delete
- الأساتذة: View/Create/Update/Delete
- الصفوف: View/Create/Update/Delete
- الشعب: View/Create/Update/Delete
- المواد: View/Create/Update/Delete
- الجداول: View/Create/Update + Approval حسب الإعداد
- الحضور: View/Manage
- الامتحانات: View/Create/Update
- الدرجات: View/Review/Approve حسب الصلاحية
- التقارير
- الإعلانات
- الرسائل

## 3.3 الأستاذ

صلاحيات افتراضية:

- مشاهدة صفوفه وشعبه (وفق ربط الأستاذ في §5.2 فقط — لا يرى صفوفاً غير مرتبط بها)
- مشاهدة طلابه (طلاب صفوفه/شعبه المرتبطة فقط)
- تسجيل الحضور
- تعديل سجل الحضور إذا سمحت الصلاحية
- إنشاء واجبات
- تصحيح الواجبات
- إنشاء امتحانات حسب الصلاحية
- إدخال الدرجات
- إرسال الدرجات للاعتماد
- إضافة دروس
- رفع ملفات
- إدارة جدوله كمقترح (إنشاء `draft` وإرساله للاعتماد — §14)
- إرسال رسائل للإدارة
- تعديل ملفه الشخصي (عبر صلاحية `profile.update` بنطاق `own` — لا توجد صلاحية "ملف" سابقة)

---

# 4. نظام الصلاحيات

لا تعتمد على Role فقط.

استخدم:

```text
Role
+
Permissions
+
Scope
```

## Permission format

استخدم نمط:

```text
resource.action
```

أمثلة:

```text
students.view
students.create
students.update
students.delete
students.archive
students.transfer

teachers.view
teachers.create
teachers.update
teachers.delete

classes.view
classes.create
classes.update
classes.delete

sections.view
sections.create
sections.update
sections.delete

subjects.view
subjects.create
subjects.update
subjects.delete

schedule.view
schedule.create
schedule.update
schedule.delete
schedule.approve

attendance.view
attendance.create
attendance.update

exams.view
exams.create
exams.update
exams.delete

grades.view
grades.create
grades.update
grades.submit
grades.approve

homeworks.view
homeworks.create
homeworks.update
homeworks.delete
homeworks.grade

lessons.view
lessons.create
lessons.update
lessons.delete

announcements.view
announcements.create
announcements.update
announcements.delete

messages.view
messages.create

reports.view
reports.export

users.view
users.create
users.update
users.delete

roles.view
roles.create
roles.update
roles.delete

permissions.manage

custom_fields.view
custom_fields.create
custom_fields.update
custom_fields.delete

audit_logs.view
profile.view
profile.update
```

> ملاحظات على القائمة:
>
> - **`assignments.*` أُعيدت تسميتها `homeworks.*`** (الواجبات): الاسم الموحد يطابق قاعدة البيانات والمسارات (`homeworks` / `homework_submissions`) — كانا في قوائم سابقة باسم `assignments`. رمزا `assignments.*` القديمان يجب أن يُزالا من كتالوج الصلاحيات (§55).
> - **`attendance.approve` أُزيلت عمداً**: لا توجد دورة اعتماد للحضور ضمن هذا النطاق (§15)، فلا تُمنح ولا تُستخدم.
> - **`profile.view` / `profile.update`** تُمنح لكل الأدوار بنطاق `own` (تعديل الملف الشخصي — §3.3).
> - **صلاحيات `*.approve`** (مثل `schedule.approve` و `grades.approve`) تمنح صاحبها حق الاعتماد `approve` وحق الرفض `reject` معاً — لا حاجة لصلاحيات `reject` منفصلة (§43).

---

# 5. Scope

كل Permission تعمل مع Scope، والنطاق يُقيَّم دائماً في الـ Backend بعد التحقق من الصلاحية وقبل تنفيذ العملية (انظر تسلسل §33).

القيم المعتمدة (لا توجد قيم أخرى):

```text
global
mosque
class
section
own
```

## 5.1 معنى كل نطاق وقاعدة تقييمه

| النطاق | لمن | قاعدة التقييم |
|---|---|---|
| `global` | الأدوار العامة فقط (`roles.tenant_id = NULL` مثل مدير الجوامع) | `can()` تُرجع true مباشرة دون فحص عزل. ⚠️ **يمنع منح `global` لأي دور تابع لجامع** (منع في الواجهة وفي الـ Backend معاً) لأنه يتجاوز عزل الجوامع ويمنح عملياً صلاحية شاملة. |
| `mosque` | مدير الجامع والصلاحيات الإدارية | `subject.tenant_id == user.tenant_id` وإلا تُرفض العملية. |
| `class` | الأستاذ على صفوفه | الصف المرتبط بالموضوع `subject.classroom_id` موجود ضمن جداول ربط الأستاذ `classroom_teacher` (§5.2). |
| `section` | الأستاذ على شعبه | الشعبة المرتبطة بالموضوع `subject.section_id` موجودة ضمن جداول ربط الأستاذ `section_teacher` (§5.2). الطالب بلا `section_id` يُحكم عليه عبر ربط صفه. |
| `own` | الموارد المملوكة ذاتياً (درجة أدخلها، جدول أنشأه، ملفه...) | مسند ملكية خاص بالمورد يُمرَّر عند الاستدعاء (`can(user, perm, subject, owns)`)؛ **إذا لم يُمرَّر مسند ملكية تُرفض العملية** ولا تُمرَّر كأنها `mosque`. |

أمثلة:

```text
students.view = mosque   → مدير الجامع يرى طلاب جامعه فقط.
students.view = section  → الأستاذ يرى طلاب شعبه فقط (عبر section_teacher).
grades.update = own      → الأستاذ يعدّل الدرجات التي أدخلها فقط.
```

مثال التقييم الفعلي:

```text
can(user, "students.delete", student)
  1) هل يملك المستخدم students.delete أصلاً؟  (لا → رفض)
  2) ما نطاق الصلاحية الممنوحة لدوره؟
     - mosque:  student.tenant_id == user.tenant_id؟
     - class:   student.classroom_id ضمن classroom_teacher للمستخدم؟
     - section: student.section_id ضمن section_teacher للمستخدم؟
  3) تفحص واحد من الفحوص أعلاه فاشل → رفض.
```

## 5.2 ربط الأستاذ بالصفوف والشعب (Membership)

الأستاذ لا يرى إلا "صفوفه/شعبه/طلابه"، والربط يكون ببيانات صريحة عبر جدولين (ولا يُستنتج من الجداول الدراسية كالجداول الزمنية أو المواد):

```text
classroom_teacher:  classroom_id  +  teacher_id    (فريدان معاً)
section_teacher:    section_id    +  teacher_id    (فريدان معاً)
```

قواعد الاشتقاق:

- طلاب الأستاذ = الطلاب النشطون الذين `classroom_id` صفّهم في `classroom_teacher` للأستاذ نفسه، أو `section_id` شعبتهم في `section_teacher` له.
- لا يعتبر ربطاً للأستاذ: صفوف `schedules` ولا `subjects.teacher_id`؛ هي بيانات تدريسية وليست نطاقات وصول (باستثناء `schedule.view` و `attendance.create` المقيدة بجدول الأستاذ نفسه).
- تُدار هذه الجداول من مدير الجامع/مدير الجوامع، وتُسجَّل تغييراتها في Audit Log (§31).

---



# 6. مصفوفة الصلاحيات

يجب توفير شاشة لإدارة الصلاحيات.

مثال:

| المورد | مشاهدة | إضافة | تعديل | حذف | اعتماد |
|---|---:|---:|---:|---:|---:|
| الطلاب | ✓ | ✓ | ✓ | ✓ | - |
| الأساتذة | ✓ | ✓ | ✓ | ✓ | - |
| الصفوف | ✓ | ✓ | ✓ | ✓ | - |
| الشعب | ✓ | ✓ | ✓ | ✓ | - |
| الجداول | ✓ | ✓ | ✓ | - | ✓ |
| الحضور | ✓ | ✓ | ✓ | - | - |
| الدرجات | ✓ | ✓ | ✓ | - | ✓ |

يجب أن يستطيع مدير الجوامع:

- إنشاء Role
- تعديل Role
- حذف Role
- نسخ Role
- تحديد Permissions
- تحديد Scope
- تحديد الجامع الذي يعمل ضمنه Role
- تعيين Role للمستخدم

---

# 7. الجوامع

## Mosque

> الاسم في قاعدة البيانات: جدول `tenants`، وعمود الجامع في كل الجداول هو `tenant_id` (راجع قاموس المصطلحات §53).

الحقول الأساسية:

```text
id
name
code          (فريد — مطلوب)
phone
email
address
logo
status        active | inactive | archived
created_at
updated_at
```

الحالات (مصدر واحد للحالة):

```text
active
inactive
archived
```

ملاحظات:

- **`is_active` عمود قديم (legacy)** يُقرأ ويُكتب للتوافق فقط؛ الحالة الرسمية الواحدة هي `status`.
- `archived` قيمة تُضاف حالياً (قاعدة البيانات تحوي active/inactive فقط) — راجع §55.
- **لا حذف فعلي للجامع أبداً**: زر الحذف يعمل أرشفة (`status = archived`)، وكل بياناته تبقى محفوظة.
- `deleted_at` غير مستخدم مع الجوامع (§39 يخص الطلاب والأساتذة).

الوظائف:

- Create
- Read
- Update
- Archive
- Delete (أرشفة فقط — مع تأكيد في الواجهة)

---

# 8. المستخدمون

## User

```text
id
name
email         (فريد عالمياً)
phone
password      (hash قوي — كان الاسم القديم password_hash)
status        active | inactive      ← إضافة (لا يوقف حساب مدير الجوامع إلا مركزياً)
tenant_id     (mosque_id) nullable — لا يملكه إلا مدير الجوامع العام
role          (سلسلة قديمة للتوافق: teacher|admin|super_admin)
created_at
updated_at
```

العلاقة:

```text
User
 ├── Roles
 └── Permissions
```

المستخدم يمكن أن يمتلك أكثر من Role إذا كان النظام يسمح بذلك.

قواعد إلزامية:

- **مزامنة الهوية**: `users.role` (السلسلة القديمة) وجداول `role_user` يجب أن يبقيا متزامنين — أي تعديل لأحدهما يحدّث الآخر (§55). عند تفعيل طبقة الصلاحيات تكون pivots هي المرجع الفعلي.
- المستخدم الذي `tenant_id = NULL` وليس مدير جوامع لا يمكنه استخدام صلاحيات أي جامع (يُرفض في `can()`).

---

# 9. الطلاب

## Student

```text
id
tenant_id (mosque_id)
student_number       ← إضافة (فريد داخل الجامع — يسمح بالبحث به)
name
gender
birth_date          (كان الاسم القديم date_of_birth؛ والعمر يُحسب منه ولا يُخزَّن)
guardian_name
guardian_phone
phone               ← إضافة
classroom_id (class_id)   nullable
section_id                nullable
status             active | archived | transferred | inactive
notes
deleted_at         (حذف ناعم §39)
created_at
updated_at
```

الوظائف:

- إضافة طالب
- تعديل طالب
- حذف/أرشفة (أرشفة = `status = archived`، حذف = `deleted_at` — §39)
- نقل طالب بين الصفوف (§40)
- نقل طالب بين الشعب (§40)
- البحث
- مشاهدة التفاصيل
- مشاهدة الحضور
- مشاهدة الدرجات
- مشاهدة الامتحانات
- مشاهدة الواجبات
- مشاهدة السجل الأكاديمي

---

# 10. الصفوف

## Class

> الاسم في قاعدة البيانات: `Classroom` (جدول `classrooms`) وعمود الصف في بقية الجداول هو `classroom_id`.

```text
id
tenant_id (mosque_id)
name
level             ← إضافة
description       ← إضافة
status            ← إضافة  active | inactive
created_at
updated_at
```

مثال:

```text
الصف الأول
الصف الثاني
الصف الثالث
```

عند فتح الصف تظهر الشعب التابعة له.

---

# 11. الشعب

## Section

```text
id
tenant_id (mosque_id)
classroom_id (class_id)
name
capacity          ← إضافة
room              ← إضافة
status            ← إضافة  active | inactive
created_at
updated_at
```

العلاقة:

```text
Class
 └── Sections
      └── Students
```

---

# 12. الأستاذ

## Teacher

```text
id
tenant_id (mosque_id)
user_id        nullable (ربط بحساب دخول)
name
gender
phone
email
specialty      (كان الاسم القديم specialization)
hired_at
is_active      (legacy يُقرأ مع status) — أو status active | inactive
deleted_at     (حذف ناعم §39)
created_at
updated_at
```

يمكن ربط الأستاذ بـ:

- صفوف (عبر `classroom_teacher` — §5.2)
- شعب (عبر `section_teacher` — §5.2)
- مواد (عبر `subjects.teacher_id`)
- جداول (عبر `schedules.teacher_id`)

---

# 13. المواد

## Subject

```text
id
tenant_id (mosque_id)
name
teacher_id   nullable (موجود في قاعدة البيانات — أستاذ المادة الأساسي)
description         ← إضافة
weekly_lessons
status              ← إضافة  active | inactive
created_at
updated_at
```

---

# 14. الجداول

## Schedule

الجدول موجود حالياً في التطبيق بأعمدة `tenant_id, classroom_id, section_id (nullable), subject_id, teacher_id, day_of_week, starts_at, ends_at` **بدون دورة اعتماد**. الحقول الكاملة المستهدفة (الإضافات تُنفَّذ بهجرة — §55):

```text
id
tenant_id (mosque_id)
teacher_id
classroom_id (class_id)
section_id nullable
subject_id
day_of_week        (0-6)
starts_at          (كان الاسم القديم start_time)
ends_at            (كان الاسم القديم end_time)
room               ← إضافة
status             ← إضافة  draft | submitted | approved | rejected | cancelled (§43)
status_by          ← إضافة  uuid → users
status_at          ← إضافة
rejection_reason   ← إضافة  (مطلوب عند الرفض)
created_at
updated_at
```

الحالات (تطابق محرك §43 — لا توجد أسماء موازية مثل `pending_approval`):

```text
draft
submitted
approved
rejected
cancelled
```

## Workflow

```text
ينشئ الأستاذ (أو المدير) جدولاً
        ↓
draft
        ↓  إرسال (submit) — بصلاحية schedule.create/update
submitted
        ↓  مدير الجامع / مدير الجوامع — بصلاحية schedule.approve
approved        أو        rejected (مع سبب إلزامي)
```

القواعد:

- الأستاذ يبني جدول مقترحه كـ `draft` (بنطاق `own`)، والمدير يدير الجداول الأساسية ويقترح أيضاً.
- **التعديل/الحذف مسموح في `draft` و `rejected` فقط** لمن يملك `schedule.update` وللمنشئ نفسه.
- `submitted` مجمّدة: لا تعديل ولا حذف حتى يُعتمد أو يُرفض.
- الرفض يعيد الحالة إلى `rejected` ويُرسل السبب للمنشئ الذي يعدّل ثم يعيد `submit` (§43).
- **`approved` نهائية**: لا تعديل ولا حذف؛ الإلغاء فقط عبر انتقال `cancelled` بصلاحية `schedule.approve` مع سبب.
- بعد الاعتماد يظهر الجدول للمستخدمين حسب الصلاحيات؛ غير المعتمدة تظهر لمنشئها وللإدارة فقط.

---

# 15. الحضور

## Attendance

الحقول المعتمدة (مطابقة لقاعدة البيانات الحالية — لا أعمدة `class_id/section_id/check_in`؛ الصف والشعبة يُستنتجان من الطالب):

```text
id
tenant_id (mosque_id)
student_id nullable   (سجل حضور طالب — يسجله الأستاذ)
teacher_id nullable   (سجل حضور أستاذ — يسجله المدير)
recorded_by           (uuid → users: من سجّل/عدّل — كان الاسم القديم created_by)
date
status                present | absent | late | excused
notes                 (كان الاسم القديم note)
created_at
updated_at
```

Status:

```text
present
absent
late
excused
```

القواعد:

- يُملأ **أحد** الحقلين `student_id` أو `teacher_id` فقط (سجلان منفصلان بنفس الجدول).
- التفرد: `(tenant_id, student_id, date)` و `(tenant_id, teacher_id, date)` — التسجيل تكرار لليوم نفسه = تحديث (upsert).
- `excused` **تتطلب تعبئة `notes` إلزامياً** (قاعدة تحقق تُضاف حالياً — التحقق القائم يسمح بثلاث قيم فقط).
- تعديل سجل ماضٍ يخضع لصلاحية `attendance.update` ويُسجَّل في Audit Log (§31).
- لا توجد دورة اعتماد للحضور (أُزيلت `attendance.approve`) — المراجعة تتم عبر `attendance.view` والتقارير.
- (مستقبلي/خارج النطاق) ربط الحضور بحصة معينة من الجدول المعتمد.

---

# 16. الامتحانات

## Exam

الحقول المعتمدة (مطابقة لقاعدة البيانات + إضافة دورة النشر):

```text
id
tenant_id (mosque_id)
subject_id
classroom_id (class_id)
section_id nullable
teacher_id nullable
title                 (كان الاسم القديم name)
exam_date
total_marks           (الدرجة العظمى — كانت max_score)
pass_marks
status                ← إضافة  draft | published | cancelled
status_by             ← إضافة  uuid → users
status_at             ← إضافة
created_at
updated_at
```

الحالات (دورة نشر وليست اعتماداً — لا تستخدم محرك §43):

```text
draft
published
cancelled
```

- المعلم ينشئ امتحانه كـ `draft` (أو المدير لأي امتحان) بصلاحية `exams.create`.
- النشر إلى `published` لمن يملك `exams.update` على الامتحان (منشئه أو المدير).
- **التعديل/الحذف في `draft` فقط**؛ بعد `published` لا تعديل إلا بإعادته `draft` من الناشر، والحذف ممنوع — إلغاؤه يكون بـ `cancelled`.

---

# 17. الدرجات

## Grade

الجدول موجود حالياً بثلاث حالات (`draft/submitted/approved`) **بدون أعمدة فاعل/وقت**. الحقول الكاملة المستهدفة (محرك §43):

```text
id
tenant_id (mosque_id)
exam_id
student_id
score decimal(6,2)   (≤ exam.total_marks؛ تفرد: exam_id + student_id)
status               draft | submitted | approved | rejected | cancelled
status_by            ← إضافة  uuid → users (آخر من غيّر الحالة)
status_at            ← إضافة
rejection_reason     ← إضافة  (مطلوب عند الرفض)
notes                (كان الاسم القديم note)
created_at
updated_at
```

Workflow (حالات محرك §43 — لا توجد أسماء موازية):

```text
Teacher
  ↓
draft
  ↓  إرسال للاعتماد (grades.submit)
submitted
  ↓  مدير الجامع/مدير الجوامع (grades.approve)
approved   أو   rejected (مع سبب إلزامي)
```

القواعد:

- **draft**: الأستاذ يدخل الدرجات ويعدّلها (`grades.create/update` بنطاق `own`) — الدرجات غير المعتمدة فقط.
- **submitted**: تُجمَّد الدفعة — لا يعدّل الأستاذ حتى تُرفض (التحديث الحالي يسمح بالتعديل حتى الاعتماد؛ يُغلَق).
- **approved**: نهائية **إلى الأبد** — أي كتابة على امتحان فيه درجة معتمدة مرفوضة (سلوك `SaveGradesAction` الحالي يُحافظ عليه ويُعمَّم: لا تعديل ولا حذف).
- **rejected**: المدير يرفض مع سبب → الدرجات تعود قابلة للتعديل من الأستاذ (الحالة `rejected`)، فيعدّل ثم يعيد `submit`.
- **cancelled**: إلغاء دفعة امتحان كاملة من حامل `grades.approve` مع سبب.
- كل انتقال يسجَّل في `status_by/status_at` وفي Audit Log (§31) ويُنشئ إشعاراً (§42).

---

# 18. الواجبات

## Homework (الواجبات)

> الاسم الموحد في النظام: `Homework` (الواجبات) — كان يسمى `Assignment` في مسودات سابقة، والصلاحيات المقابلة هي `homeworks.*` (§4). قاعدة البيانات والمسارات: `homeworks` / `homework_submissions`.

```text
id
tenant_id (mosque_id)
teacher_id
subject_id
classroom_id (class_id)
section_id nullable
title
description
due_date
pass_marks
attachment_path      (ملف يرفعه الأستاذ)
status               ← إضافة  draft | published | cancelled (نشر للطلاب — خارج نطاق بوابة الطالب حالياً)
created_at
updated_at
```

## Submission (تسليم الطالب)

```text
id
homework_id (assignment_id)
student_id
status         pending | submitted | graded
file           ← إضافة (ملف تسليم الطالب — غير موجود بعد؛ يتطلب بوابة طالب "مرحلة لاحقة")
grade
feedback
submitted_at
created_at
updated_at
```

التنفيذ الحالي:

- المعلم ينشئ الواجب وتُولَّد صفوف تسليم مسبقة لكل طالب نشط في الصف/الشعبة (حالة `pending`).
- المعلم يصحح ويقيّم (`homeworks.grade`) من `pending` إلى `graded`.
- مرحلة لاحقة (خارج النطاق الحالي حتى وجود بوابة الطالب): تسليم الطالب الفعلي عبر `file` + `submitted_at` وقراءة الواجبات والدرجات.

---

# 19. الدروس

## Lesson

```text
id
tenant_id (mosque_id)
teacher_id
subject_id
classroom_id (class_id) nullable
title
description
type            file | link | video | presentation (مطابق للتحقق الحالي)
file_path
url
created_at
updated_at
```

يدعم:

- PDF / PowerPoint (عبر `file_path`)
- فيديو
- روابط
- ملفات

(الحقول `content/status/section_id` غير مستخدمة — تمييز نوع الوسيط يتم عبر `type`.)

---

# 20. الإعلانات

## Announcement

الحقول المعتمدة (مطابقة لقاعدة البيانات + توسيع الاستهداف):

```text
id
tenant_id (mosque_id)
user_id            (الناشر — كان الاسم القديم created_by)
title
body               (كان الاسم القديم content)
audience           all | teachers | students | guardians | class | section
classroom_id nullable   (حين audience = class)
section_id  nullable    ← إضافة (حين audience = section)
published_at nullable
created_at
updated_at
```

Target (الجمهور المستهدف):

```text
all        (الجميع داخل الجامع)
teachers
students   (قابل للتسجيل الآن؛ يظهر فعلياً عند وجود بوابة الطالب "مرحلة لاحقة")
guardians  (كانت parents في مسودات سابقة؛ ويطابق معنى أولياء الأمور)
class      (صف محدد عبر classroom_id)
section    (شعبة محددة عبر section_id — إضافة)
```

(أُلغي النموذج القديم `target_type/target_id` متعدد الأشكال — الاستهداف يُعبَّر عنه بالحقلين أعلاه.)

---

# 21. الرسائل

## Message

الحقول المعتمدة (مطابقة لقاعدة البيانات):

```text
id
tenant_id (mosque_id)
sender_id           (uuid → users)
recipient_id        (كان الاسم القديم receiver_id)
subject
body                (كان الاسم القديم content)
read_at
created_at
updated_at
```

---

# 22. Custom Fields

هذه ميزة أساسية في النظام.

يجب ألا تكون معلومات المستخدم/الطالب/الصف/الشعبة محصورة في Schema ثابت.

## CustomField

```text
id
mosque_id nullable
entity_type
name
key
field_type
description
is_required
is_active
is_global
sort_order
created_by
created_at
updated_at
```

## Entity Types

```text
student
teacher
class
section
mosque
```

## Field Types

```text
text
textarea
number
phone
email
date
time
datetime
boolean
select
multi_select
file
image
```

## 22.1 التقييم المتدرج لصلاحية الوصول إلى الحقل

وصول أي مستخدم لحقل مخصص يمر بثلاث طبقات **كلها إلزامية** (تُنفَّذ في الـ Backend):

1. **صلاحية الكيان**: `custom_fields.view` / `custom_fields.create` / `custom_fields.update` عبر `can(user, code, subject)` بنطاق الجامع.
2. **تفعيل الحقل ونطاقه**: `is_active = true` و (`is_global = true` أو `custom_field.mosque_id == مستخدم.tenant_id` — §26).
3. **مصفوفة الحقل**: سجل `CustomFieldPermission` الخاص بدور المستخدم في جامعه (`can_view` / `can_create` / `can_update`).

نتائج القرار (سلوك موحد في الواجهة والـ API):

```text
الطبقة 3 can_view = false  → الحقل مخفي تماماً، وقيمته مرفوضة في الإدخال API 403
can_view = true, can_create = false → عند إنشاء كيان لا يظهر الحقل للإدخال
can_view = true, can_update = false → القيمة مقروءة فقط (read-only)
```

- إخفاء الحقل في الواجهة **ليس حماية**: كل طبقة تُفحص في الـ Backend قبل القراءة والكتابة.
- `custom_field_id` بلا سجل صفوف مصفوفة = يعامل كأن الدور لا يملك أي صلاحية على الحقل.

---

# 23. مثال Custom Field

المدير يضغط:

```text
إضافة حقل
```

ويحدد:

```text
اسم الحقل:
رقم ولي الأمر

النوع:
phone

يطبق على:
student

إجباري:
yes
```

بعد الحفظ يظهر الحقل عند جميع الطلاب.

---

# 24. Custom Field Values

لا تضع قيمة الحقل داخل جدول الطلاب مباشرة.

استخدم:

## CustomFieldValue

```text
id
custom_field_id
entity_type
entity_id
value
created_at
updated_at
```

مثال:

```text
custom_field_id = 10
entity_type = student
entity_id = 500
value = 0912345678
```

---

# 25. صلاحيات الحقول

كل Custom Field يجب أن يدعم صلاحيات منفصلة.

## CustomFieldPermission

```text
id
custom_field_id
role_id
can_view
can_create
can_update
```

معاني الأعلام (يُقيَّم وفق التدرج في §22.1):

```text
can_view   =  الحقل يظهر ويُقرأ
can_create =  يمكن إدخال قيمة عند إنشاء الكيان
can_update =  يمكن تعديل القيمة
```

مثال:

```text
رقم ولي الأمر

مدير الجوامع:
View ✓
Create ✓
Update ✓

مدير الجامع:
View ✓
Create ✓
Update ✓

الأستاذ:
View ✓
Create ✗
Update ✗
```

---

# 26. Global vs Mosque Custom Fields

إذا:

```text
is_global = true
```

فالحقل يظهر في جميع الجوامع.

إذا:

```text
is_global = false
mosque_id = X
```

فالحقل خاص بجامع واحد.

---

# 27. واجهة الصفوف والشعب

## الصفوف

```text
الصف الأول       3 شعب    65 طالب
الصف الثاني      2 شعب    44 طالب
الصف الثالث      4 شعب    82 طالب
```

فتح الصف:

```text
الصف الأول

شعبة A    25 طالب
شعبة B    21 طالب
شعبة C    19 طالب
```

فتح الشعبة:

```text
الصف الأول / شعبة A

الأستاذ: أحمد

الطلاب:
- محمد علي
- عبدالله خالد
- أحمد حسن
```

فتح الطالب:

```text
محمد علي

المعلومات الشخصية
الحضور
الدرجات
الامتحانات
الواجبات
السجل الأكاديمي
```

---

# 28. Dashboard مدير الجوامع

يعرض:

- عدد الجوامع
- عدد الطلاب
- عدد الأساتذة
- عدد الصفوف
- عدد الشعب
- نسبة الحضور
- الغياب
- آخر النشاطات
- الطلبات التي تحتاج موافقة
- إحصائيات كل جامع

---

# 29. Dashboard مدير الجامع

يعرض:

- عدد الطلاب
- عدد الأساتذة
- الصفوف
- الشعب
- حضور اليوم
- غياب اليوم
- الجداول
- طلبات اعتماد الجداول
- طلبات اعتماد الدرجات
- الإعلانات
- آخر النشاطات

---

# 30. Dashboard الأستاذ

يعرض:

- جدول اليوم
- عدد الحصص
- الصفوف
- الشعب
- الطلاب
- الغياب
- الواجبات
- الامتحانات
- الدرجات التي تحتاج إرسال
- الإشعارات

---

# 31. Audit Log

كل عملية حساسة يجب تسجيلها.

## AuditLog

```text
id
mosque_id nullable
user_id
action
entity_type
entity_id
old_values
new_values
ip_address
user_agent
created_at
```

أمثلة:

```text
CREATE student
UPDATE student
DELETE student
TRANSFER student
APPROVE schedule
REJECT schedule
APPROVE grades
CHANGE permission
CREATE custom field
```

مثال سجل:

```text
مدير الجامع أحمد
قام بتعديل الطالب محمد علي

قبل:
phone = 091111111

بعد:
phone = 092222222

الوقت:
2026-09-05 16:30
```

## خريطة التسجيل الإلزامية

تسجيل مضمون (لا يعتمد على تذكّر المطور) لكل عملية حساسة:

```text
CREATE / UPDATE / DELETE   (الطلاب، الأساتذة، الصفوف، الشعب، المواد، المستخدمون، الأدوار)
TRANSFER student           (§40)
APPROVE / REJECT / CANCEL  (schedule و grades — §43)
CHANGE permission          (تعديل مصفوفة دور أو تخصيصه لمستخدم)
CREATE/UPDATE custom field + تغيير مصفوفة حقوله (§25)
```

آلية التنفيذ:

- عمليات الكتابة القياسية: **Observer مركزي** على النماذج يسجل الفاعل (`user_id`) وقيم `old_values/new_values` قبل/بعد التغيير.
- العمليات المركّبة (نقل، اعتماد/رفض، تغيير صلاحيات): تسجيل **صريح** في الخدمة المنفذة.
- **قراءات GET لا تُسجَّل** إطلاقاً.
- تُحفظ الحقول المتغيرة فقط (قبل/بعد) لتقليل الحجم، مع `ip_address` و `user_agent`.
- `mosque_id` يُملأ من سياق الطلب؛ والاطلاع على السجل عبر `audit_logs.view` بنطاق الجامع (مدير الجامع يرى جامعه، مدير الجوامع يرى الكل).

---

# 32. الأمان

يجب تطبيق:

- Password hashing قوي
- Session/JWT حسب المعمارية
- Authorization على Backend
- Tenant/Mosque isolation
- Validation
- Rate limiting
- CSRF protection إذا كان مناسباً للمعمارية
- XSS protection
- SQL injection protection
- File upload validation
- Audit logging
- Soft delete للبيانات الحساسة
- عدم كشف IDs الحساسة عند الحاجة
- عدم السماح بتغيير `mosque_id` من Client مباشرة

---

# 33. قواعد Backend المهمة

كل Request يجب أن يمر تقريباً بهذا التسلسل:

```text
Authentication
      ↓
Identify User
      ↓
Load Roles
      ↓
Load Permissions
      ↓
Check Permission
      ↓
Check Scope
      ↓
Check Mosque Access
      ↓
Validate Input
      ↓
Execute Action
      ↓
Create Audit Log
```

مثال:

```text
DELETE /students/123
```

لا يكفي:

```text
hasPermission("students.delete")
```

بل يجب أيضاً:

```text
student.mosque_id == currentUser.mosque_id
```

إلا إذا كان المستخدم Global.

---

# 34. API Modules

قسم الـAPI إلى Modules:

```text
/auth
/mosques
/users
/roles
/permissions
/students
/teachers
/classes
/sections
/subjects
/schedules
/attendance
/exams
/grades
/homeworks
/lessons
/announcements
/messages
/custom-fields
/reports
/audit-logs
```

---

# 35. API Examples

## Students

```http
GET    /api/students
POST   /api/students
GET    /api/students/:id
PUT    /api/students/:id
DELETE /api/students/:id
POST   /api/students/:id/archive
POST   /api/students/:id/transfer
```

## Custom Fields

```http
GET    /api/custom-fields
POST   /api/custom-fields
GET    /api/custom-fields/:id
PUT    /api/custom-fields/:id
DELETE /api/custom-fields/:id
```

## Schedule

```http
POST /api/schedules
POST /api/schedules/:id/submit
POST /api/schedules/:id/approve
POST /api/schedules/:id/reject
```

## Grades

```http
POST /api/grades
PUT  /api/grades/:id
POST /api/grades/:id/submit
POST /api/grades/:id/approve
```

---

# 36. Database Relationships

```text
Mosque
 ├── Users
 ├── Students
 ├── Teachers
 ├── Classes
 ├── Sections
 ├── Subjects
 ├── Schedules
 ├── Attendance
 ├── Exams
  ├── Grades
  ├── Homeworks (الواجبات)
  ├── Lessons
  ├── Announcements
  └── CustomFields

Class
 └── Sections

Section
 └── Students

Teacher
 ├── Subjects
 ├── Classes (عبر classroom_teacher — §5.2)
 ├── Sections (عبر section_teacher — §5.2)
 └── Schedules

Exam
 └── Grades

Homework
 └── HomeworkSubmissions

CustomField
 └── CustomFieldValues
```

---

# 37. Navigation

## مدير الجوامع

```text
Dashboard
الجوامع
الطلاب
الأساتذة
الصفوف
الشعب
المواد
الجداول
الحضور
الامتحانات
الدرجات
الواجبات
الدروس
الإعلانات
الرسائل
التقارير
المستخدمون
الأدوار والصلاحيات
الحقول المخصصة
سجل العمليات
الإعدادات
```

## مدير الجامع

نفس النظام لكن البيانات مقيدة بالجامع.

## الأستاذ

```text
Dashboard
جدولي
صفوفي
شعبي
طلابي
الحضور
الواجبات
الامتحانات
الدرجات
الدروس
الرسائل
ملفي الشخصي
```

---

# 38. البحث والتصفية

كل قائمة مهمة يجب أن تدعم:

- Search
- Pagination
- Sort
- Filters
- Export حسب الصلاحية

مثال الطلاب:

```text
بحث بالاسم
بحث برقم الطالب
الصف
الشعبة
الحالة
```

---

# 39. Soft Delete

لا تحذف البيانات الحساسة مباشرة.

السياسة الموحدة (مساران متكاملان وليسا بديلين):

```text
أرشفة  = تغيير حالة (status) مع بقاء السجل ظاهراً لمن يريد استعراض المؤرشف
حذف    = deleted_at (Soft Delete): يختفي السجل من كل الاستعلامات والتقارير
```

```text
deleted_at
```

- يُطبَّق `deleted_at` (ناعم) على: **الطلاب والأساتذة** والبيانات الحساسة المشابهة.
- معنى زر "حذف" في الواجهات = `delete()` الناعم، لا `delete` الفعلي — وأي حذف فعلي ممنوع في كود الإنتاج.
- `students.status = archived` (المستخدمة حالياً في التطبيق) هي **أرشفة ظاهرة**، بينما الحذف الناعم `deleted_at` يزيل السجل من النتائج نهائياً — الاثنان يتكاملان (أرشفة ثم حذف اختياري).
- الواجهة تعرض Confirmation قبل أي حذف، والحذف يُسجَّل في Audit Log (§31).
- الجوامع لا تُحذف ولا تُؤرشف إلا عبر `status = archived` (§7).

---

# 40. النقل بين الصفوف والشعب

يجب توفير:

```text
Transfer Student
```

مثال:

```text
من:
الصف الأول / شعبة A

إلى:
الصف الثاني / شعبة B
```

مع تسجيل العملية في Audit Log.

ويجب الاحتفاظ بالسجل السابق إذا كان النظام الأكاديمي يحتاج History.

---

# 41. التقارير

التقارير الأساسية:

```text
تقرير الطلاب
تقرير الأساتذة
تقرير الصفوف
تقرير الشعب
تقرير الحضور
تقرير الغياب
تقرير الدرجات
تقرير الامتحانات
تقرير أداء الجامع
تقرير النشاطات
```

مدير الجوامع يستطيع المقارنة بين الجوامع.

---

# 42. Notifications

## نموذج البيانات (جدول جديد — هجرة §55)

```text
id
tenant_id (mosque_id)
user_id        (المستلم)
type           (نصي يمثل الحدث: schedule.submitted / grades.approved / ...)
data           (JSON: روابط وسياق)
read_at nullable
created_at
```

القاعدة: الإشعارات **أثر جانبي آلي** للأحداث في §43 و §31 — لا تُنشأ يدوياً من الواجهات (إنشاء الإشعار يقع داخل نفس Transaction للعملية).

## أنواع الإشعارات

```text
طلب جدول جديد
اعتماد جدول
رفض جدول
طلب اعتماد درجات
اعتماد درجات
إعلان جديد
رسالة جديدة
تعديل صلاحيات
```

ربط كل نوع بحدثه المولّد له:

- طلب جدول جديد ← عند `submit` لجدول من أستاذ: إلى مديري الجامع (حملة `schedule.approve`).
- اعتماد/رفض جدول ← عند `approve/reject`: إلى منشئ الجدول.
- طلب اعتماد درجات ← عند `grades.submit`: إلى المديرين.
- اعتماد/رفض درجات ← إلى الأستاذ المدرِج.
- إعلان جديد ← حسب جمهور الإعلان في §20.
- رسالة جديدة ← إلى المستلم.
- تعديل صلاحيات ← للمستخدم المتأثر عند تغيير دوره أو مصفوفة صلاحياته.

---

# 43. حالات الموافقة العامة — Approval Engine موحّد

صمم محرك موافقات واحداً قابلاً لإعادة الاستخدام يُستعمل مع الجداول (§14) والدرجات (§17) وأي Workflow مستقبلي.

## الحالات المعيارية (خمس حالات فقط — بدون مرادفات)

```text
draft
submitted
approved
rejected
cancelled
```

> الحالة الواحدة لها اسم واحد في النظام كله؛ أسماء مثل `pending` أو `pending_approval` غير معتمدة. `submitted` = قيد المراجعة/بانتظار الاعتماد.

## مصفوفة الانتقالات المسموحة

| من | العملية | إلى | الفاعل المسموح |
|---|---|---|---|
| `draft` | submit | `submitted` | المنشئ (أستاذ/مدير) |
| `submitted` | approve | `approved` | حامل صلاحية `*.approve` |
| `submitted` | reject | `rejected` | حامل صلاحية `*.approve` (مع `rejection_reason` إلزامي) |
| `rejected` | submit | `submitted` | المنشئ (بعد التعديل) |
| `draft` | cancel | `cancelled` | المنشئ أو حامل `*.approve` |
| `approved` | cancel | `cancelled` | حامل `*.approve` فقط (مع سبب) |

قواعد إلزامية:

- لا انتقال خارج الجدول أعلاه (مثال: `draft → approved` أو `submitted → draft` ممنوعان).
- صلاحية `*.approve` تمنح حقّي الاعتماد والرفض معاً — لا حاجة لصلاحية `reject` منفصلة.
- التعديل/الحذف مسموح في `draft` و `rejected` فقط، والمجمّد `submitted` لا يُلمس حتى القرار.
- كل انتقال يُسجَّل: الفاعل والوقت والسبب في أعمدة `status_by/status_at/rejection_reason`، مع إدخال في Audit Log (§31) وإشعار (§42).

## الأعمدة العامة (تُضاف لكل جدول يستخدم المحرك)

```text
status            string  draft | submitted | approved | rejected | cancelled
status_by         uuid nullable → users      (آخر من غيّر الحالة)
status_at         timestamp nullable
rejection_reason  text nullable              (إلزامي عند reject/cancel)
```

## الوحدات المستخدمة معها

- الجداول (§14)
- الدرجات (§17)
- الامتحانات إذا لزم
- أي Workflow مستقبلي

---

# 44. Frontend Components

أنشئ Components قابلة لإعادة الاستخدام:

```text
DataTable
SearchBar
FilterBar
Pagination
Modal
ConfirmDialog
FormBuilder
CustomFieldRenderer
PermissionMatrix
RoleEditor
ApprovalDialog
StatusBadge
EntityDetails
FileUploader
NotificationCenter
AuditTimeline
```

---

# 45. Custom Field Form Builder

يجب أن يكون هناك UI مثل:

```text
+ إضافة حقل

اسم الحقل
[________________]

نوع الحقل
[ Phone ▼ ]

يطبق على
[x] الطلاب
[ ] الأساتذة
[ ] الصفوف
[ ] الشعب

إجباري
[x]

Global
[ ]

الصلاحيات
```

وعند فتح نموذج الطالب:

```text
المعلومات الأساسية
------------------
الاسم
العمر
الهاتف

المعلومات المخصصة
------------------
رقم ولي الأمر
اسم ولي الأمر
مكان السكن
```

يتم توليد هذه الحقول ديناميكياً.

---

# 46. UX Rules

- واجهة عربية RTL.
- Responsive.
- Desktop + Tablet + Mobile.
- Navigation واضحة.
- Breadcrumbs.
- Confirmation للحذف.
- Toast للعمليات.
- Empty states.
- Loading states.
- Error states.
- Pagination.
- منع الوصول للصفحات غير المسموحة.
- إخفاء Actions غير المسموحة من UI مع بقاء الحماية Backend.

---

# 47. Definition of Done

المشروع لا يعتبر مكتملًا إلا إذا:

### Multi-Mosque
- [ ] إنشاء عدة جوامع
- [ ] عزل بيانات الجوامع
- [ ] مدير لكل جامع
- [ ] مدير مركزي

### Permissions
- [ ] Roles
- [ ] Permissions
- [ ] CRUD permissions
- [ ] Scope
- [ ] Field-level permissions

### Students
- [ ] CRUD
- [ ] Search
- [ ] Transfer
- [ ] Details
- [ ] Attendance
- [ ] Grades

### Academic Structure
- [ ] Classes
- [ ] Sections
- [ ] Teachers
- [ ] Subjects
- [ ] Teacher ↔ class/section membership bindings (§5.2)

### Schedule
- [ ] Create
- [ ] Submit
- [ ] Approve
- [ ] Reject
- [ ] View
- [ ] Approved schedules immutable (cancel only)
- [ ] Workflow columns + actor/time recorded

### Grades
- [ ] 5-state workflow: draft / submitted / approved / rejected / cancelled (§43)
- [ ] Submit freezes grades (no edit while submitted)
- [ ] Approve locks forever (no write after approval)
- [ ] Reject (with reason) → teacher resubmits
- [ ] Cancel
- [ ] Actor + time recorded per transition

### Custom Fields
- [ ] Create field
- [ ] Edit field
- [ ] Delete/archive field
- [ ] Dynamic rendering
- [ ] Field types
- [ ] Global fields
- [ ] Mosque-specific fields
- [ ] Field permissions

### Audit
- [ ] CRUD logs
- [ ] Permission changes
- [ ] Approval logs
- [ ] Transfer logs
- [ ] Observer-based automatic capture for standard writes (§31)

### Notifications
- [ ] Database notifications (tenant/user/read_at)
- [ ] Notification created automatically on each workflow transition
- [ ] New message / announcement notifications

### Reports & Export
- [ ] Report screens (students/teachers/classes/attendance/grades...)
- [ ] Export requires `reports.export` permission (CSV minimum)

### Soft Delete & Archive
- [ ] `deleted_at` soft delete on students/teachers
- [ ] Archive status vs deleted_at semantics documented and enforced
- [ ] Confirmation dialogs on all destructive actions

### Security
- [ ] Backend authorization
- [ ] Mosque isolation
- [ ] Validation
- [ ] Secure file uploads
- [ ] Authentication
- [ ] Rate limiting
- [ ] Attendance status `excused` requires note

---

# 48. ترتيب التنفيذ

نفذ المشروع بهذا الترتيب ولا تبدأ بالميزات الثانوية قبل الأساس:

## Phase 1 — Foundation

1. Project setup
2. Database
3. Authentication
4. User model
5. Mosque model
6. Roles
7. Permissions
8. Scope
9. Authorization middleware

## Phase 2 — Mosque Management

10. Mosque CRUD
11. Mosque manager
12. User management
13. Role management
14. Permission matrix

## Phase 3 — Academic Structure

15. Students
16. Teachers
17. Classes
18. Sections
19. Subjects
19b. Teacher↔class/section membership (جداول الربط §5.2 + واجهة ربط)
20. Student transfer

## Phase 4 — Academic Operations

21. Schedule
22. Schedule approval (محرك §43: submit/approve/reject/resubmit/cancel)
23. Attendance
24. Exams
25. Grades
26. Grade approval (محرك §43 كاملاً: reject + resubmit + cancel)
27. Homeworks (الواجبات — كانت Assignments)
28. Lessons

## Phase 5 — Communication

29. Announcements
30. Messages
31. Notifications

## Phase 6 — Dynamic System

32. Custom Fields
33. Custom Field Values
34. Field-level permissions
35. Dynamic forms

## Phase 7 — Reporting & Security

36. Reports
37. Audit Logs
38. Export
39. Security hardening
40. Testing

---

# 49. Testing Requirements

اكتب Tests على الأقل لهذه الحالات:

## Authorization

```text
Super Admin can access all mosques
Mosque Manager cannot access another mosque
Teacher cannot access another mosque
Teacher cannot delete student without permission
Teacher cannot modify protected custom field
```

## Data Isolation

```text
Student from Mosque A
must never appear
in Mosque B queries
```

## Workflow

```text
Teacher creates schedule
→ Pending

Manager approves
→ Approved

Manager rejects
→ Rejected
```

## Custom Fields

```text
Create field
→ field appears on all applicable entities

Global field
→ appears in all mosques

Mosque field
→ appears only in selected mosque

Field permission denied
→ field hidden / read-only according to permission
```

## Workflow Reject / Resubmit

```text
Teacher submits grades
→ submitted (frozen: further writes rejected)

Manager rejects (with reason)
→ rejected (teacher notified)

Teacher edits and resubmits
→ submitted

Manager approves
→ approved

Any write on approved grades
→ rejected/blocked (approval is permanent)
```

## Scope & Membership

```text
Teacher bound to section A only
→ student of section B never appears in roster/APIs

Class-scope grant
→ rows of other classes denied

Own-scope grant without ownership predicate
→ denied (no silent mosque-scope fallback)

Global scope grant on a tenant-scoped role
→ rejected by validation (UI + backend)
```

## Notifications

```text
Schedule/grade submitted
→ notification created for mosque managers

Approve/reject
→ notification created for the owner

Announcement created
→ notifications per audience

Message sent
→ notification to recipient
```

## Export

```text
Export route without reports.export
→ 403

CSV export with filters
→ 200 and correct rows
```

---

# 50. قاعدة ذهبية للمشروع

لا تجعل الصلاحيات موزعة داخل كل Controller بشكل عشوائي.

استخدم Authorization Layer مركزية:

```text
can(user, permission, resource)
```

مثال:

```text
can(
    currentUser,
    "students.delete",
    student
)
```

وتتحقق من:

```text
1. Authentication
2. Permission
3. Role
4. Scope
5. Mosque ownership
6. Resource ownership
```

---

# 51. النتيجة المطلوبة

المنتج النهائي يجب أن يسمح لمدير الجوامع بأن يدير شبكة كاملة من الجوامع من مكان واحد، مع إمكانية الدخول إلى كل جامع بشكل منفصل، وإدارة المديرين والأساتذة والطلاب والصفوف والشعب والجداول والحضور والدرجات وغيرها.

ويجب أن تكون الصلاحيات مرنة بحيث يمكن تغييرها بدون تعديل الكود.

كما يجب أن يستطيع المسؤول إضافة حقول جديدة مثل:

```text
رقم ولي الأمر
اسم ولي الأمر
مكان السكن
ملاحظات
أي معلومة مستقبلية
```

ويتم تعريف:

```text
اسم الحقل
نوع الحقل
الكيان
هل هو إجباري
هل هو Global
من يستطيع مشاهدته
من يستطيع تعديله
```

ثم يظهر الحقل ديناميكياً في النظام.

---

# 52. ملاحظة تنفيذية مهمة

إذا كان المشروع سيُنفذ باستخدام Stack محدد، لا تبدأ بكتابة الواجهات فقط.

ابدأ أولاً بـ:

```text
Database Schema
↓
Authentication
↓
Authorization
↓
Mosque Isolation
↓
Core CRUD
↓
Frontend
```

لأن Multi-Mosque + Permissions هي أساس النظام، وأي خطأ فيها سيؤثر على كل باقي المكونات.

# 53. مطابقة المخطط الحالي لقاعدة البيانات (مرجع الجاهزية)

هذا الملحق يسد الفجوة بين أسماء المستند والأسماء الفعلية في الكود/قاعدة البيانات، ويحدد ما هو موجود وما يُضاف بهجرة (§55).

## قاموس المصطلحات

| في المستند | في قاعدة البيانات/الكود |
|---|---|
| جامع Mosque | `Tenant` (جدول `tenants`) |
| `mosque_id` | `tenant_id` |
| صف Class | `Classroom` (جدول `classrooms`، العمود `classroom_id`) |
| واجب Assignment | `Homework` (جدول `homeworks`، والتسليم `homework_submissions`) |
| صلاحيات الواجبات | `homeworks.*` (وليست `assignments.*`) |
| محتوى الرسالة `content` | `messages.body` |
| `password_hash` | `users.password` |
| `date_of_birth` | `students.birth_date` |
| `specialization` | `teachers.specialty` |
| إشعارات | `notifications` (جدول جديد) |
| سجل العمليات | `audit_logs` (جدول جديد) |
| الحقول المخصصة | `custom_fields` / `custom_field_values` / `custom_field_permissions` (جديدة) |

## حالة الأعمدة لكل وحدة

| الوحدة | موجود في قاعدة البيانات حالياً | يُضاف لاحقاً (هجرة) |
|---|---|---|
| tenants | name, code, phone, email, address, logo, status (active/inactive), is_active (legacy) | قيمة `archived` في status |
| users | name, email, phone, role, gender, password, tenant_id | status (active/inactive) |
| students | name, gender, birth_date, guardian_name, guardian_phone, classroom_id, section_id, status, notes | student_number (فريد لكل جامع)، phone، deleted_at |
| teachers | user_id, name, gender, phone, email, specialty, hired_at, is_active | deleted_at |
| classrooms | name | level، description، status |
| sections | name, classroom_id | capacity، room، status |
| subjects | name, weekly_lessons, teacher_id | description، status |
| schedules | tenant_id, teacher_id, classroom_id, section_id, subject_id, day_of_week, starts_at, ends_at | room + أعمدة workflow (§43): status, status_by, status_at, rejection_reason |
| attendances | كاملة (student_id/teacher_id/recorded_by/date/status/notes) | لا شيء — تفعيل `excused` في التحقق |
| exams | subject_id, classroom_id, section_id, teacher_id, title, exam_date, total_marks, pass_marks | status, status_by, status_at |
| grades | exam_id, student_id, score, status, notes | status_by, status_at, rejection_reason |
| homeworks | teacher_id, subject_id, classroom_id, section_id, title, description, due_date, pass_marks, attachment_path | status (draft/published/cancelled) |
| homework_submissions | homework_id, student_id, status, grade, feedback, submitted_at | file (مرحلة لاحقة — بوابة الطالب) |
| lessons | teacher_id, subject_id, classroom_id, title, description, type, file_path, url | لا شيء |
| announcements | user_id, classroom_id, title, body, audience (all/teachers/guardians/classroom), published_at | section_id + قيم audience إضافية (students/class/section) |
| messages | sender_id, recipient_id, subject, body, read_at | لا شيء |
| جداول ربط الأستاذ (§5.2) | غير موجودة | `classroom_teacher`، `section_teacher` |
| notification / audit / custom fields | غير موجودة | `notifications`، `audit_logs`، `custom_fields`، `custom_field_values`، `custom_field_permissions` |

> ملاحظة: لا توجد أي `deleted_at` في قاعدة البيانات حالياً؛ وكل حالات status أعمدة نصية بلا قيود ENUM (تُدار في الكود/التحقق).

# 54. سجل القرارات المعيارية

| القرار | التفاصيل | البدائل المرفوضة |
|---|---|---|
| حالة موحدة 5 حالات | `draft/submitted/approved/rejected/cancelled` في كل الأنظمة (§43) | `pending`/`pending_approval` (تعدد أسماء) |
| approve تشمل reject | صلاحية `*.approve` تمنح الاعتماد والرفض معاً | إضافة `*.reject` (تضخيم الكتالوج دون قيمة) |
| الربط الصريح | جدولا `classroom_teacher`/`section_teacher` لتحديد نطاقي class/section | الاشتقاق من schedules أو subjects.teacher_id (بيانات تدريسية لا نطاقات) |
| homeworks | تسمية موحدة `homeworks.*` بدل `assignments.*` | الإبقاء على assignments |
| profile | صلاحيتان جديدتان `profile.view/update` (own للجميع) | استثناء مبرمج بلا صلاحية |
| attendance | بلا دورة اعتماد؛ `attendance.approve` أُزيلت؛ excused يتطلب سبباً | دورة موافقة كاملة (ليست مطلوبة) |
| حالة الجامع | `tenants.status` وحده (active/inactive/archived) | الاعتماد على is_active |
| حذف/أرشفة | مساران: `status` (أرشفة ظاهرة) + `deleted_at` (حذف ناعم) للطلاب والأساتذة | الحذف الفعلي المباشر |
| الإعلانات | استهداف عبر `audience` + `classroom_id`/`section_id` وليس target_type/target_id | النموذج متعدد الأشكال |

# 55. مهام المزامنة خارج المستند (قبل تنفيذ المراحل المتبقية)

1. **كتالوج الصلاحيات** (`PermissionCatalog`): إعادة تسمية `assignments.*` → `homeworks.*`، حذف `attendance.approve`، إضافة `profile.view`/`profile.update`.
2. **إصلاح بذرة الكتالوج**: `ensurePermissionCatalog()` ترجع مبكراً إذا وُجد أول كود — تجعل أي إضافة لاحقة لا تُزرع في قواعد البيانات القائمة. تُحوَّل إلى تحديث متدرج (upsert للناقص + تنظيف الأكواد المهجورة).
3. **تفعيل طبقة الصلاحيات**: ربط وسيط `permission:` بالمسارات عند بناء كل ميزة (قرار موثق — حتى اليوم لا يستهلك أي مسار صلاحيات الكتالوج). يُحافظ على `users.role` (السلسلة القديمة) متزامنة مع `role_user` في كل تعديل مستخدم (شاشة الإدارة الحالية تحدّث السلسلة فقط).
4. **حماية `global`**: منع اختيار نطاق `global` لأي دور تابع لجامع في واجهة المصفوفة وفي التحقق الخلفي (`MosqueRoleController`).
5. **هجرات قاعدة البيانات** (حسب §53): أعمدة workflows للجداول والدرجات (status/status_by/status_at/rejection_reason)، room للجداول، exam status، homework status، students (student_number/phone/deleted_at)، teachers deleted_at، announcements (section_id)، `archived` في tenants.status، جداول الربط `classroom_teacher`/`section_teacher`، وجداول `notifications`/`audit_logs`/`custom_fields` وملحقاتها.
6. **اختبارات الحالات**: بعد كل مرحلة يُضاف غطاء من §49 (Workflow Reject/Resubmit، Scope & Membership، Notifications، Export) في `tests/Feature/`.

## End of Specification
