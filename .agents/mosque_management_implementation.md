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

- مشاهدة صفوفه وشعبه
- مشاهدة طلابه
- تسجيل الحضور
- تعديل سجل الحضور إذا سمحت الصلاحية
- إنشاء واجبات
- تصحيح الواجبات
- إنشاء امتحانات حسب الصلاحية
- إدخال الدرجات
- إرسال الدرجات للاعتماد
- إضافة دروس
- رفع ملفات
- إدارة جدوله كمقترح
- إرسال رسائل للإدارة
- تعديل ملفه الشخصي

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
attendance.approve

exams.view
exams.create
exams.update
exams.delete

grades.view
grades.create
grades.update
grades.submit
grades.approve

assignments.view
assignments.create
assignments.update
assignments.delete
assignments.grade

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
```

---

# 5. Scope

كل Permission يجب أن تعمل مع Scope.

القيم المقترحة:

```text
global
mosque
class
section
own
```

أمثلة:

```text
students.view = mosque
```

يعني المدير يرى طلاب جامعه فقط.

```text
students.view = own
```

يعني الأستاذ يرى طلابه فقط.

```text
students.view = global
```

يعني مدير الجوامع يستطيع رؤية الطلاب في كل الجوامع.

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
| الحضور | ✓ | ✓ | ✓ | - | ✓ |
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

الحقول الأساسية:

```text
id
name
code
phone
email
address
status
logo
created_at
updated_at
deleted_at
```

الحالات:

```text
active
inactive
archived
```

الوظائف:

- Create
- Read
- Update
- Archive
- Delete

---

# 8. المستخدمون

## User

```text
id
name
email
phone
password_hash
status
mosque_id nullable
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

---

# 9. الطلاب

## Student

```text
id
mosque_id
student_number
name
date_of_birth
age
phone
status
class_id
section_id
created_at
updated_at
deleted_at
```

الوظائف:

- إضافة طالب
- تعديل طالب
- حذف/أرشفة
- نقل طالب بين الصفوف
- نقل طالب بين الشعب
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

```text
id
mosque_id
name
level
description
status
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
mosque_id
class_id
name
capacity
room
status
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
mosque_id
user_id
name
phone
email
specialization
status
created_at
updated_at
```

يمكن ربط الأستاذ بـ:

- صفوف
- شعب
- مواد
- جداول

---

# 13. المواد

## Subject

```text
id
mosque_id
name
description
weekly_lessons
status
created_at
updated_at
```

---

# 14. الجداول

## Schedule

```text
id
mosque_id
teacher_id
class_id
section_id
subject_id
day_of_week
start_time
end_time
room
status
created_by
approved_by
approved_at
created_at
updated_at
```

الحالات:

```text
draft
pending_approval
approved
rejected
cancelled
```

## Workflow

```text
الأستاذ ينشئ جدول
       ↓
draft
       ↓
إرسال
       ↓
pending_approval
       ↓
مدير الجامع / مدير الجوامع
       ↓
approved
```

بعد الاعتماد يظهر الجدول للمستخدمين حسب الصلاحيات.

---

# 15. الحضور

## Attendance

```text
id
mosque_id
student_id
class_id
section_id
date
status
check_in
note
created_by
updated_by
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

---

# 16. الامتحانات

## Exam

```text
id
mosque_id
name
subject_id
class_id
section_id
exam_date
max_score
status
created_by
created_at
updated_at
```

---

# 17. الدرجات

## Grade

```text
id
mosque_id
exam_id
student_id
score
note
status
entered_by
approved_by
approved_at
created_at
updated_at
```

Workflow:

```text
Teacher
  ↓
Draft
  ↓
Submit
  ↓
Pending Approval
  ↓
Manager
  ↓
Approved
```

---

# 18. الواجبات

## Assignment

```text
id
mosque_id
teacher_id
class_id
section_id
title
description
due_date
status
created_at
updated_at
```

## Submission

```text
id
assignment_id
student_id
file
submitted_at
score
feedback
status
```

---

# 19. الدروس

## Lesson

```text
id
mosque_id
teacher_id
class_id
section_id
subject_id
title
description
content
status
created_at
updated_at
```

يدعم:

- PDF
- PowerPoint
- فيديو
- روابط
- ملفات

---

# 20. الإعلانات

## Announcement

```text
id
mosque_id
title
content
target_type
target_id
created_by
published_at
status
created_at
updated_at
```

Target:

```text
mosque
class
section
teachers
students
parents
```

---

# 21. الرسائل

## Message

```text
id
mosque_id
sender_id
receiver_id
subject
content
read_at
created_at
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
/assignments
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
 ├── Assignments
 ├── Lessons
 ├── Announcements
 └── CustomFields

Class
 └── Sections

Section
 └── Students

Teacher
 ├── Subjects
 ├── Classes
 ├── Sections
 └── Schedules

Exam
 └── Grades

Assignment
 └── Submissions

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

استخدم:

```text
deleted_at
```

للطلاب والأساتذة وغيرها عند الحاجة.

واجهة الحذف يجب أن تعرض Confirmation.

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

أنواع الإشعارات:

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

---

# 43. حالات الموافقة العامة

صمم Approval Engine قابل لإعادة الاستخدام:

```text
draft
pending
approved
rejected
cancelled
```

ويستخدم مع:

- الجداول
- الدرجات
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

### Schedule
- [ ] Create
- [ ] Submit
- [ ] Approve
- [ ] Reject
- [ ] View

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

### Security
- [ ] Backend authorization
- [ ] Mosque isolation
- [ ] Validation
- [ ] Secure file uploads
- [ ] Authentication
- [ ] Rate limiting

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
20. Student transfer

## Phase 4 — Academic Operations

21. Schedule
22. Schedule approval
23. Attendance
24. Exams
25. Grades
26. Grade approval
27. Assignments
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

## End of Specification
