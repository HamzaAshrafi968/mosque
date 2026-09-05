# Mosque Management System — Students, Classes, Sections, Attendance & Finance

## 1. Purpose

This document defines the functional and technical requirements for managing:

- Students
- Teachers
- Classes
- Sections
- Section membership/enrollment
- Attendance
- Custom student/teacher fields
- Person-to-person financial transactions
- Financial balances and ledgers

The design should use the database as the source of truth and preserve history for important operations.

---

## 2. Core Data Model

### 2.1 Students

The `students` table should contain core identity/profile information only.

Recommended fields:

- `id`
- `name`
- `date_of_birth`
- `phone`
- `email`
- `address`
- `status`
- `created_at`
- `updated_at`
- `deleted_at` (if the global soft-delete policy applies)

Guardian information should be modeled separately if it becomes complex or if one guardian can be associated with multiple students.

### 2.2 Teachers

The `teachers` table should contain core teacher/profile information.

Recommended fields:

- `id`
- `name`
- `phone`
- `email`
- `address`
- `status`
- `created_at`
- `updated_at`
- `deleted_at`

Employment-specific information should be separated if it grows beyond simple profile data.

---

# 3. Custom Fields

Administrators should be able to add extra fields to student and teacher profiles without modifying the database schema for every new field.

## 3.1 Custom Field Definition

Recommended structure:

```text
custom_fields
- id
- entity_type
- name
- field_key
- field_type
- required
- options
- sort_order
- is_active
- created_at
- updated_at
```

### Supported `entity_type`

```text
student
teacher
```

### Supported `field_type`

```text
text
textarea
number
date
boolean
select
multiselect
```

## 3.2 Custom Field Values

Recommended structure:

```text
custom_field_values
- id
- custom_field_id
- entity_id
- value
- created_at
- updated_at
```

The application must validate that the value matches the custom field type.

Example:

> Admin creates `Father's Occupation` as a text field.

The field then appears automatically on student profiles.

---

# 4. Classes

A class represents the high-level academic/course grouping.

Recommended structure:

```text
classes
- id
- name
- description
- status
- created_at
- updated_at
- deleted_at
```

Examples:

```text
Quran Beginners
Quran Intermediate
Arabic
Tajweed
```

---

# 5. Sections

A section is a subdivision of a class.

Recommended structure:

```text
sections
- id
- class_id
- name
- description
- status
- created_at
- updated_at
- deleted_at
```

Relationship:

```text
classes.id
    ↓
sections.class_id
```

One class can contain multiple sections.

Example:

```text
Quran Beginners
├── Section A
├── Section B
└── Section C
```

---

# 6. Student Enrollment / Section Membership

Do not permanently store `section_id` directly on the student if students can move between sections.

Use a membership/enrollment table.

Recommended structure:

```text
section_students
- id
- section_id
- student_id
- status
- enrolled_at
- left_at
- created_at
- updated_at
```

Possible statuses:

```text
active
inactive
transferred
completed
```

This preserves enrollment history.

Example:

```text
Student Ahmed
    ↓
Section A
    ↓
Transferred
    ↓
Section B
```

The system can therefore determine which section a student belonged to at any historical date.

---

# 7. Teacher / Section Membership

Teachers should also be associated with sections through explicit membership/assignment.

Recommended structure:

```text
section_teachers
- id
- section_id
- teacher_id
- role
- status
- starts_at
- ends_at
- created_at
- updated_at
```

This is important for authorization.

A `classroom_teacher` or `section_teacher` should only receive access based on actual membership/assignment to the relevant classroom/section, not merely because the user has a role string.

---

# 8. Class and Section UI

Recommended navigation:

```text
Classes
│
├── Quran Beginners
│   ├── Section A
│   ├── Section B
│   └── Section C
│
├── Quran Intermediate
│   ├── Section A
│   └── Section B
│
└── Arabic
    └── Section A
```

## 8.1 Class Page

The class page should show:

- Class name
- Description
- Number of sections
- Total active students
- Teachers
- Sections

Actions:

```text
Add Section
Edit Class
Archive Class
```

## 8.2 Section Page

Clicking a section should open a section dashboard containing:

- Section name
- Parent class
- Assigned teachers
- Active student count
- Student list
- Attendance
- Homework
- Exams
- Announcements
- Section settings

---

# 9. Section Student List

Example:

```text
Quran Beginners / Section A

Teacher: Ahmed Ali
Students: 15

------------------------------------------------
Student              Status        Attendance
------------------------------------------------
Mohamed Hassan       Active          92%
Omar Ahmed           Active          87%
Yusuf Ali            Active          95%
...
```

Actions:

```text
Add Student
Remove/Transfer Student
View Student
Take Attendance
```

---

# 10. Attendance Architecture

Attendance should be session-based.

Do not store only a calculated percentage on the student.

The individual attendance records are the source of truth.

## 10.1 Attendance Sessions

Recommended structure:

```text
attendance_sessions
- id
- section_id
- date
- start_time
- end_time
- status
- created_by
- created_at
- updated_at
```

A session represents one attendance-taking event.

Example:

```text
Section A
2026-09-05
```

## 10.2 Attendance Records

Recommended structure:

```text
attendance_records
- id
- attendance_session_id
- student_id
- status
- note
- created_at
- updated_at
```

Recommended statuses:

```text
present
absent
late
excused
```

The application should enforce one attendance record per student per attendance session.

---

# 11. Attendance Table UI

Example:

| Student | Sep 1 | Sep 3 | Sep 5 | Sep 8 | Attendance |
|---|---|---|---|---|---:|
| Ahmed | Present | Present | Late | Present | 87.5% |
| Omar | Present | Absent | Present | Present | 75% |
| Yusuf | Present | Present | Present | Present | 100% |

The interface should allow teachers/admins to:

- Mark Present
- Mark Absent
- Mark Late
- Mark Excused
- Add a note
- Edit attendance according to permission
- View attendance history

---

# 12. Attendance Calculation

Attendance percentage should be calculated from attendance records.

Example:

```text
attendance_percentage =
    qualifying_present_sessions
    / total_qualifying_sessions
    * 100
```

The exact definition of `qualifying_present_sessions` must be documented.

For example:

```text
Present = counts as attended
Late = counts as attended, optionally with a separate late metric
Absent = does not count
Excused = excluded from denominator
```

This rule must be consistent throughout reports and dashboards.

---

# 13. Financial Management

Financial data should use a ledger/transaction model.

Do not rely on a manually edited `balance` field as the source of truth.

## 13.1 Financial Transactions

Recommended structure:

```text
financial_transactions
- id
- person_id
- amount
- transaction_type
- direction
- related_person_id
- description
- reference
- created_by
- created_at
- updated_at
```

Possible transaction types:

```text
charge
payment
refund
transfer
adjustment
```

Possible directions:

```text
money_in
money_out
```

The exact accounting semantics should be finalized before implementation.

---

# 14. Person Financial Balance

For each person, the UI should show:

```text
Person
-------------------------------
Total charges:       €100.00
Total payments:       €60.00
Total received:       €0.00
Outstanding balance: €40.00
```

A balance should be derived from the transaction ledger.

Example:

```text
balance =
    charges
  - payments
  + adjustments
```

The final formula must depend on the accounting direction selected for the project.

---

# 15. Person-to-Person Money Transfers

If one person gives or takes money from another person, the transaction must preserve both sides of the relationship.

Example:

```text
Ahmed gives Omar €50
```

The system should record:

```text
Ahmed
  → €50 outgoing

Omar
  → €50 incoming
```

Recommended fields:

```text
person_id
related_person_id
amount
transaction_type = transfer
```

This makes it possible to answer:

- How much money did Ahmed give?
- How much did Ahmed receive?
- How much does Omar owe?
- Who gave money to whom?
- When did the transfer happen?
- Who recorded the transaction?

Financial transactions should not be silently deleted. Corrections should normally use reversal/adjustment transactions and an audit trail.

---

# 16. Financial UI

Each person should have a financial tab:

```text
Ahmed Ali

Balance
----------------
Owed:       €40
Received:   €0

Transactions
------------------------------------------------
Date        Type       From/To       Amount
------------------------------------------------
Sep 01      Charge     Organization  €100
Sep 03      Payment    Ahmed         €60
```

For transfers:

```text
Sep 05      Transfer   To Omar       €50
```

---

# 17. Permissions

Access should be controlled by both role and scope.

Examples:

```text
student.view
student.update

teacher.view
teacher.update

class.view
class.create
class.update

section.view
section.create
section.update

section.students.view

attendance.view
attendance.create
attendance.update

finance.view
finance.create
finance.update
```

A teacher assigned to Section A should not automatically gain access to Section B.

The authorization layer should verify actual section membership/assignment.

---

# 18. Audit Logging

The following actions should be auditable:

### Student

- Student created
- Student updated
- Student transferred
- Student removed/archived

### Teacher

- Teacher created
- Teacher updated
- Teacher assigned to section
- Teacher removed from section

### Classes/Sections

- Class created/updated
- Section created/updated
- Student enrolled
- Student transferred

### Attendance

- Attendance session created
- Attendance marked
- Attendance changed
- Attendance corrected

### Finance

- Transaction created
- Transaction adjusted/reversed
- Transfer created

Audit information should include:

```text
actor
action
entity
entity_id
before
after
timestamp
```

---

# 19. Recommended User Flow

## Create a class

```text
Classes
→ Add Class
→ Enter name
→ Save
```

## Add a section

```text
Class
→ Add Section
→ Enter section name
→ Assign teacher
→ Save
```

## Add students

```text
Section
→ Add Student
→ Select existing student
→ Enroll
```

## View students

```text
Classes
→ Class
→ Section
→ Students
```

## Take attendance

```text
Classes
→ Class
→ Section
→ Attendance
→ Select date/session
→ Mark students
→ Save
```

## View attendance

```text
Section
→ Attendance
→ Select date range
→ View attendance table
```

---

# 20. Example End-to-End Structure

```text
Mosque
│
├── Users
│
├── Students
│   ├── Profiles
│   ├── Custom Fields
│   ├── Enrollments
│   └── Financial Ledger
│
├── Teachers
│   ├── Profiles
│   ├── Custom Fields
│   └── Section Assignments
│
├── Classes
│   ├── Sections
│   │   ├── Teachers
│   │   ├── Students
│   │   ├── Attendance
│   │   ├── Homework
│   │   ├── Exams
│   │   └── Announcements
│
├── Finance
│   ├── Charges
│   ├── Payments
│   ├── Transfers
│   └── Adjustments
│
└── Audit
    └── Activity History
```

---

# 21. Implementation Rules

1. Database records are the source of truth.
2. Do not store derived attendance percentages as authoritative data.
3. Do not manually edit financial balances as the authoritative value.
4. Preserve enrollment history.
5. Preserve financial history.
6. Use explicit teacher/section membership for scoped authorization.
7. Use custom fields for configurable student/teacher information.
8. Use soft delete according to the project's global soft-delete policy.
9. Record important changes in the audit log.
10. Keep status values centralized and consistent.
11. Validate all financial amounts and transaction directions.
12. Prevent duplicate attendance records for the same student/session.
13. Prevent unauthorized teachers from accessing sections to which they are not assigned.

---

# 22. Definition of Done

The feature is complete when:

- [ ] Admin can create and edit students.
- [ ] Admin can create and edit teachers.
- [ ] Admin can define custom student fields.
- [ ] Admin can define custom teacher fields.
- [ ] Admin can create classes.
- [ ] Admin can create sections inside classes.
- [ ] Teachers can be assigned to sections.
- [ ] Students can be enrolled in sections.
- [ ] Student transfers preserve historical membership.
- [ ] Clicking a section displays its students.
- [ ] Attendance sessions can be created.
- [ ] Students can be marked present/absent/late/excused.
- [ ] Attendance history is displayed in a table.
- [ ] Attendance percentages are calculated correctly.
- [ ] Financial charges/payments can be recorded.
- [ ] Person-to-person transfers can be recorded.
- [ ] Financial history is visible per person.
- [ ] Financial changes are auditable.
- [ ] Authorization respects section membership.
- [ ] Tests cover the critical workflows.

---

# 23. Testing Requirements

At minimum, test:

### Classes

- Create class
- Edit class
- Create section
- Section belongs to correct class

### Enrollment

- Enroll student
- Prevent invalid duplicate active enrollment
- Transfer student between sections
- Preserve previous enrollment history

### Teacher Assignment

- Assign teacher to section
- Remove teacher
- Verify teacher can only access assigned sections

### Attendance

- Create attendance session
- Mark all supported statuses
- Prevent duplicate student/session records
- Edit attendance with proper permission
- Calculate percentages correctly
- Exclude/handle excused records according to the documented rule

### Finance

- Create charge
- Create payment
- Create transfer
- Verify both sides of a person-to-person transfer
- Calculate balance
- Reverse/correct a transaction
- Verify audit trail

### Custom Fields

- Create field
- Update field
- Validate field type
- Store student value
- Store teacher value
- Respect field permissions

---

# 24. Suggested API Surface

Example endpoints:

```text
GET    /classes
POST   /classes
GET    /classes/:id
PATCH  /classes/:id

POST   /classes/:id/sections
GET    /sections/:id
PATCH  /sections/:id

GET    /sections/:id/students
POST   /sections/:id/students
POST   /sections/:id/teachers

GET    /sections/:id/attendance
POST   /sections/:id/attendance/sessions
POST   /attendance/sessions/:id/records
PATCH  /attendance/records/:id

GET    /students/:id
PATCH  /students/:id

GET    /teachers/:id
PATCH  /teachers/:id

GET    /custom-fields
POST   /custom-fields
PATCH  /custom-fields/:id

GET    /people/:id/transactions
POST   /people/:id/transactions
POST   /financial-transfers
```

Exact endpoint names should follow the existing project's API conventions.

---

# 25. Final Architecture Principle

The system should model the real-world relationships:

```text
Class
  ↓
Section
  ↓
Enrollment
  ↓
Student
  ↓
Attendance

Teacher
  ↓
Section Assignment
  ↓
Section

Person
  ↓
Financial Ledger
  ↓
Charges / Payments / Transfers / Adjustments
```

This structure provides a clean foundation for adding future features such as homework, exams, announcements, reports, notifications, and dashboards without redesigning the core student/class/section architecture.
