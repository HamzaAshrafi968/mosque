# Quran Programs — Tasmee', Faith Meetings, Qualifying & Ijazah

## 1. Quran Memorization / Tasmee' (التسميع)

Every Tasmee' record is assigned by the teacher to a student and has one of two types:

- `new` — جديد
- `revision` — مراجعة

Recommended fields:

```text
quran_recitation_sessions
- id
- student_id
- teacher_id
- type
- date
- amount
- recited_portion
- result
- notes
- created_at
- updated_at
```

The teacher can record the amount, Quran portion, result, and notes.

The system must preserve every Tasmee' record historically.

---

## 2. Quran Completion / Hafiz Status

When the student completes memorizing the Quran and the completion is confirmed:

```text
Student
  ↓
Quran Completed
  ↓
Hafiz
```

Recommended completion record:

```text
quran_completion
- id
- student_id
- completed_at
- confirmed_by
- notes
```

The completion event must be auditable.

After confirmation, the student becomes a Hafiz automatically according to the configured business rules.

---

# 3. Faith Meetings (اللقاءات الإيمانية)

Create a dedicated page for faith meetings.

Each meeting contains:

```text
faith_meetings
- id
- title
- description
- date
- start_time
- end_time
- supervisor_id
- teacher_id
- location
- notes
- status
- created_at
- updated_at
```

The organizer can be:

- Supervisor
- Teacher
- Sheikh
- Authorized staff member

---

## 3.1 Selecting Students

The organizer can select specific students for each meeting.

Example:

```text
Create Faith Meeting

Title:
Spiritual Development Meeting

Supervisor:
Sheikh Omar

Date:
2026-09-12

Students:
[x] Ahmed Ali
[x] Omar Hassan
[ ] Yusuf Ahmed
[x] Muhammad Ali

Notes:
Focus on sincerity and consistency.
```

Only selected students are attached to the meeting unless a separate bulk/group selection option is used.

Recommended relationship:

```text
faith_meeting_students
- id
- meeting_id
- student_id
- attendance_status
- note
- created_at
- updated_at
```

Attendance:

```text
attended
absent
excused
```

---

## 3.2 Meeting Notes & Suggestions

The meeting should support:

- General notes
- Student-specific notes
- Suggestions
- Action items

Recommended structure:

```text
faith_meeting_notes
- id
- meeting_id
- student_id (nullable)
- note_type
- content
- created_by
- created_at
```

Possible `note_type` values:

```text
note
suggestion
action_item
```

---

# 4. Qualifying Program (البرنامج التأهيلي)

The Qualifying Program is for students who have completed memorizing the Quran.

When the Quran completion is confirmed, the student is automatically enrolled.

```text
Quran Completed
      ↓
Hafiz
      ↓
Automatic enrollment
      ↓
Qualifying Program
```

The Qualifying Program is **weekly**.

---

## 4.1 Qualifying Program Enrollment

```text
program_enrollments
- id
- student_id
- program_type
- started_at
- completed_at
- status
```

Program types:

```text
qualifying
ijazah
```

---

## 4.2 Weekly Qualifying Evaluation

Every week gets a separate evaluation.

Required information:

```text
اسم الطالب
المقدار
اسم المقروء
الأسبوع
النتيجة
المشرف / الأستاذ
ملاحظات
```

Recommended structure:

```text
qualifying_weekly_evaluations
- id
- student_id
- week_start
- week_end
- amount
- recited_portion
- result
- evaluated_by
- notes
- created_at
- updated_at
```

Example:

```text
Qualifying Program — Week 1

Student:
Ahmed Ali

Amount:
5 Juz'

Recited:
Juz' 1–5

Result:
Passed

Supervisor:
Sheikh Omar

Notes:
Good overall performance.
```

Every week creates a new record. Previous weeks must never be overwritten.

Example:

```text
Week 1 → Passed
Week 2 → Passed
Week 3 → Needs Review
Week 4 → Passed
```

---

## 4.3 Qualifying Completion

When the student completes the configured qualifying requirements:

```text
Qualifying Program
       ↓
Completed
       ↓
Automatic enrollment in Ijazah Program
```

The transition must be recorded and audited.

---

# 5. Ijazah Program (برنامج الإجازة)

The Ijazah Program begins after successful completion of the Qualifying Program.

It is **monthly**.

```text
Qualifying Completed
       ↓
Ijazah Program
       ↓
Monthly Evaluations
```

---

## 5.1 Monthly Ijazah Evaluation

Required information:

```text
اسم الطالب
المقدار
اسم المقروء
الشهر
النتيجة
المشرف / الأستاذ
ملاحظات
```

Recommended structure:

```text
ijazah_monthly_evaluations
- id
- student_id
- month
- amount
- recited_portion
- result
- evaluated_by
- notes
- created_at
- updated_at
```

Each month creates a new historical evaluation.

Example:

```text
September → Passed
October   → Passed
November  → Needs Review
December  → Passed
```

---

# 6. Hafiz / Teacher Profile

A person may first be a student and later become a Hafiz and/or teacher.

Do not create a duplicate person just because the role changes.

Example:

```text
Student
  ↓
Quran Completed
  ↓
Qualifying Program
  ↓
Ijazah Program
  ↓
Teacher / Sheikh
```

The same person's student history must remain available.

---

## 6.1 Hafiz Profile Fields

Required fields:

```text
الاسم الثلاثي
الصف
المواليد
تاريخ حفظ القرآن
المجاز
المجيز
الرواية
الإجازة الجزرية
الشهادة العلمية
الدورات الشرعية
الدورات التدريبية
الدراسة الأكاديمية الشرعية
```

Recommended database fields:

```text
full_name
class_id
date_of_birth
quran_completion_date
mujaz
mujiz
riwayah
ijazah_jazariyyah
scientific_certificate
sharia_courses
training_courses
sharia_academic_study
```

Definitions:

```text
المجاز = person who received the Ijazah
المجيز = person who granted the Ijazah
```

---

# 7. Additional Custom Fields

The Hafiz/Teacher profile must support custom fields.

The administrator can create a field and choose:

```text
Field Name
Field Key
Field Type
Required
Active
Display Order
Options
```

Supported types:

```text
text
textarea
number
date
boolean
select
multiselect
```

Example:

```text
Field Name:
Specialization

Type:
Select

Options:
Quran
Tajweed
Fiqh
Arabic
Hadith
```

Another example:

```text
Field Name:
Years of Teaching

Type:
Number
```

Custom fields must be displayed automatically on the relevant profile.

---

# 8. Monthly Hafiz Exams

After Quran completion, the student should enter the monthly Hafiz examination workflow according to the institution's configured rules.

The exam is **monthly**.

Each month has its own exam record.

Required fields:

```text
اسم الحافظ
الشهر
نتيجة الاختبار
الدرجة
مشرف الاختبار
الأجزاء المطلوب إعادتها
ملاحظات
```

Recommended structure:

```text
hafiz_monthly_exams
- id
- student_id
- month
- exam_status
- grade
- result
- supervisor_id
- notes
- exam_date
- created_at
- updated_at
```

Exam status must distinguish:

```text
not_tested
tested
passed
failed
```

`not_tested` is not the same as `failed`.

---

# 9. Failed Exam — Portions to Repeat

If a Hafiz fails specific portions, the supervisor can record exactly what must be repeated.

Example:

```text
Result:
Failed

Grade:
61 / 100

Required Repetition:
Juz' 7
Juz' 8

Supervisor:
Sheikh Omar
```

Recommended structure:

```text
hafiz_exam_revisions
- id
- exam_id
- from_surah
- from_ayah
- to_surah
- to_ayah
- juz
- amount
- status
- notes
```

Possible statuses:

```text
pending
completed
approved
```

This allows the system to track exactly which portions need to be repeated and whether the repetition was completed.

---

# 10. Monthly Hafiz Exam History

Example:

| Month | Status | Grade | Result | Supervisor | Repetition |
|---|---|---:|---|---|---|
| Sep 2026 | Tested | 88 | Passed | Sheikh Omar | — |
| Oct 2026 | Tested | 72 | Passed | Sheikh Ahmed | Juz' 12 |
| Nov 2026 | Not Tested | — | — | Sheikh Omar | — |
| Dec 2026 | Tested | 61 | Failed | Sheikh Omar | Juz' 7–8 |

Previous months must never be overwritten.

---

# 11. Quran Journey Dashboard

Each student/Hafiz should have a clear journey:

```text
✓ Memorization
      ↓
✓ Quran Completed
      ↓
✓ Hafiz
      ↓
✓ Qualifying Program — Weekly
      ↓
✓ Qualifying Completed
      ↓
→ Ijazah Program — Monthly
      ↓
→ Ijazah Completed
      ↓
→ Teacher / Sheikh (if assigned)
```

The system should preserve all stages and their history.

---

# 12. Suggested Quran Dashboard

For each student:

```text
Quran Journey

Current Stage:
Qualifying Program

Quran Completion:
2026-08-30

New Memorization:
2 pages this week

Revision:
10 pages this week

Last Result:
Very Good

Next Evaluation:
Week 3
```

For a Hafiz:

```text
Hafiz Dashboard

Current Program:
Ijazah

Monthly Result:
Passed

Last Exam:
88 / 100

Portions Requiring Revision:
Juz' 12

Next Exam:
October 2026
```

---

# 13. Teacher / Sheikh Dashboard

The teacher should see:

```text
My Students
My Sections
Tasmee'
New Memorization
Revision
Qualifying Students
Ijazah Students
Hafiz Exams
Faith Meetings
Notes
Notifications
```

Useful indicators:

```text
Students needing revision
Students with weak results
Students close to Quran completion
Students newly entered into qualifying
Students in Ijazah
Students not tested this month
Students with repeated failed portions
```

---

# 14. Faith Meeting Suggestions

Recommended additional features:

### Meeting Templates

Allow administrators to create recurring meeting templates:

```text
Weekly Spiritual Meeting
Monthly Parent/Student Meeting
Hifz Motivation Meeting
```

### Action Items

A suggestion can become an action item:

```text
Suggestion:
Increase revision.

Action:
Teacher gives student a 3-day revision plan.

Assigned To:
Sheikh Omar

Due Date:
2026-09-15

Status:
Pending
```

This makes meeting notes actionable rather than just text.

---

# 15. Permissions

Suggested permissions:

```text
quran.tasmee.view
quran.tasmee.create
quran.tasmee.update

quran.completion.view
quran.completion.confirm

faith_meetings.view
faith_meetings.create
faith_meetings.update
faith_meetings.attendance

qualifying.view
qualifying.create
qualifying.update
qualifying.complete

ijazah.view
ijazah.create
ijazah.update
ijazah.complete

hafiz_exams.view
hafiz_exams.create
hafiz_exams.update
hafiz_exams.grade

hafiz_profile.view
hafiz_profile.update

custom_fields.view
custom_fields.create
custom_fields.update
```

---

# 16. Scope / Authorization

Teachers and supervisors should only access:

```text
Students in their assigned sections
+
Students explicitly assigned to their programs
+
Faith meetings they manage
+
Exams they supervise
```

Parents:

```text
Own children only
```

Students:

```text
Own data only
```

All scope checks must be enforced server-side.

Frontend hiding is not sufficient authorization.

---

# 17. Audit Requirements

Audit these events:

```text
Tasmee' created
Tasmee' updated
Tasmee' result changed

Quran completion confirmed
Hafiz status created

Qualifying enrollment created
Weekly qualifying result recorded
Qualifying completion confirmed

Ijazah enrollment created
Monthly Ijazah result recorded
Ijazah completion confirmed

Hafiz exam created
Hafiz exam graded
Exam status changed
Failed portions recorded
Failed portions completed

Faith meeting created
Students added/removed
Meeting attendance changed
Meeting notes changed

Hafiz profile changed
Custom field created/updated
```

Audit records:

```text
actor_id
action
entity_type
entity_id
before
after
created_at
```

---

# 18. Automatic Workflow Rules

## Rule 1 — Quran Completion

```text
Confirmed Quran Completion
        ↓
Student becomes Hafiz
        ↓
Qualifying Program enrollment
        ↓
Monthly Hafiz exam workflow
```

The exact start point of monthly Hafiz exams can be configurable.

## Rule 2 — Qualifying Completion

```text
Qualifying Program Completed
        ↓
Ijazah Program enrollment
```

## Rule 3 — Role Transition

A person who becomes a teacher does not lose their student/Hafiz history.

```text
Existing Person
    ↓
Student
    ↓
Hafiz
    ↓
Teacher
```

Roles should be additive and permission-based.

---

# 19. Main Navigation

```text
Quran
├── Students
├── Tasmee'
│   ├── New
│   └── Revision
├── Quran Completion
├── Hafiz
├── Qualifying Program
├── Ijazah Program
├── Monthly Hafiz Exams
└── Quran Reports

Faith Meetings
├── All Meetings
├── Create Meeting
├── Upcoming
├── Completed
└── Reports

Profiles
├── Hafiz
├── Ijazah
├── Teachers
└── Custom Fields
```

---

# 20. Definition of Done

## Tasmee'

- [ ] Teacher can select New or Revision.
- [ ] Teacher can record the amount.
- [ ] Teacher can record the Quran portion.
- [ ] Teacher can record the result.
- [ ] Teacher can add notes.
- [ ] Historical Tasmee' records are preserved.

## Quran Completion

- [ ] Quran completion can be recorded.
- [ ] Completion can be confirmed.
- [ ] Confirmation is audited.
- [ ] Student becomes Hafiz automatically.
- [ ] Student enters the configured next program automatically.

## Faith Meetings

- [ ] Meeting can be created.
- [ ] Supervisor/teacher can be selected.
- [ ] Specific students can be selected.
- [ ] Meeting attendance can be recorded.
- [ ] General notes can be added.
- [ ] Student-specific notes can be added.
- [ ] Suggestions can be added.
- [ ] Action items can be assigned.

## Qualifying Program

- [ ] Hafiz automatically enters the program.
- [ ] Weekly evaluations are supported.
- [ ] Student name is shown.
- [ ] Amount is recorded.
- [ ] Recited portion is recorded.
- [ ] Result is recorded.
- [ ] Supervisor/teacher is recorded.
- [ ] Notes are supported.
- [ ] Previous weeks are preserved.
- [ ] Completion moves the student to Ijazah.

## Ijazah Program

- [ ] Student enters after qualifying completion.
- [ ] Monthly evaluations are supported.
- [ ] Student name is shown.
- [ ] Amount is recorded.
- [ ] Recited portion is recorded.
- [ ] Result is recorded.
- [ ] Supervisor/teacher is recorded.
- [ ] Notes are supported.
- [ ] Previous months are preserved.

## Hafiz Profile

- [ ] Full name.
- [ ] Class.
- [ ] Date of birth.
- [ ] Quran completion date.
- [ ] Mujaz.
- [ ] Mujiz.
- [ ] Riwayah.
- [ ] Ijazah Jazariyyah.
- [ ] Scientific certificate.
- [ ] Sharia courses.
- [ ] Training courses.
- [ ] Sharia academic study.
- [ ] Additional custom fields.
- [ ] Custom field types supported.

## Monthly Hafiz Exams

- [ ] Hafiz name.
- [ ] Month.
- [ ] Tested / Not Tested.
- [ ] Grade.
- [ ] Result.
- [ ] Exam supervisor.
- [ ] Failed portions.
- [ ] Required repetitions.
- [ ] Repetition completion tracking.
- [ ] Monthly history preserved.

---

# 21. Final Architecture

The Quran module should model the real journey:

```text
Student
  ↓
Tasmee'
  ├── New
  └── Revision
  ↓
Quran Completed
  ↓
Hafiz
  ├── Qualifying Program
  │      └── Weekly Evaluations
  │
  └── Monthly Hafiz Exams
          ↓
     Qualifying Completed
          ↓
     Ijazah Program
          └── Monthly Evaluations
                  ↓
              Ijazah Completed
                  ↓
            Teacher / Sheikh
```

All transitions must be based on confirmed business events, preserve history, enforce role/scope permissions, and create audit records.
