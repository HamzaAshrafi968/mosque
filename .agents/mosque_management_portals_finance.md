# Mosque Management System — Parent, Student & Sheikh Portals

## 1. Overview

The system should provide separate portals and permissions for:

- Administrators
- Parents / Guardians
- Students
- Sheikhs / Teachers

Each role must only access data within its authorized scope.

Core principle:

```text
Admin
  ↓
Full access according to permissions

Parent
  ↓
Own children only

Student
  ↓
Own profile and academic data only

Sheikh / Teacher
  ↓
Assigned sections → enrolled students only
```

---

# 2. Parent / Guardian Portal

## 2.1 Parent Account

A parent/guardian must have an independent account.

Recommended structure:

```text
parents
- id
- user_id
- name
- phone
- email
- status
- created_at
- updated_at
```

A parent can be connected to one or more students.

```text
parent_students
- id
- parent_id
- student_id
- relationship
- is_primary
- created_at
- updated_at
```

Supported relationships may include:

```text
father
mother
guardian
other
```

The parent must only be able to access students connected through `parent_students`.

---

# 3. Parent Dashboard

The parent dashboard should show all children connected to the account.

Example:

```text
Parent Dashboard

My Children
────────────────────────────────────
Ahmed Ali
Quran — Section A
Attendance: 92%

Mohamed Ali
Arabic — Section B
Attendance: 87%
```

Each child should be clickable.

---

# 4. Parent Child Profile

When a parent clicks a child, they should see the child's complete authorized academic profile.

Navigation:

```text
Child Profile
├── Overview
├── Personal Information
├── Class & Section
├── Attendance
├── Subjects
├── Teachers
├── Exams
├── Grades
├── Homework
├── Announcements
├── Files / Documents
└── Notifications
```

The parent must not be able to modify information unless a specific permission explicitly allows it.

---

# 5. Parent Attendance Page

The parent should be able to view attendance history.

Example:

| Date | Subject / Section | Status | Note |
|---|---|---|---|
| Sep 1 | Quran | Present | |
| Sep 3 | Quran | Present | |
| Sep 5 | Quran | Late | 10 minutes |
| Sep 8 | Quran | Absent | |

Summary:

```text
Attendance
----------------
Present:  18
Absent:    1
Late:      2
Excused:   1

Attendance Rate: 90%
```

Attendance calculations must be based on attendance records, not a manually edited percentage.

---

# 6. Parent Subjects Page

The parent can see the subjects/classes associated with the child.

Example:

```text
Subjects

Quran
Teacher: Sheikh Omar
Section: A

Tajweed
Teacher: Sheikh Ahmed
Section: A

Arabic
Teacher: Sheikh Yusuf
Section: B
```

---

# 7. Parent Teachers Page

The parent can see teachers associated with the child.

Example:

```text
Teachers

Sheikh Omar
Subject: Quran
Section: A

Sheikh Ahmed
Subject: Tajweed
Section: A
```

Only teachers actually connected to the student's current or relevant enrollment should be shown.

---

# 8. Parent Exams & Grades

Parents should be able to view:

- Upcoming exams
- Completed exams
- Exam date
- Subject
- Grade
- Maximum grade
- Percentage
- Result/status
- Teacher comments when permitted

Example:

| Exam | Subject | Grade | Percentage |
|---|---|---:|---:|
| Quran Exam 1 | Quran | 87/100 | 87% |
| Tajweed Exam 1 | Tajweed | 92/100 | 92% |

---

# 9. Parent Homework Page

Parents should be able to monitor homework.

Example:

| Homework | Subject | Due Date | Status |
|---|---|---|---|
| Memorization 12 | Quran | Sep 8 | Completed |
| Worksheet 4 | Arabic | Sep 10 | Pending |

The parent can view the submission/status according to permissions.

---

# 10. Parent Notifications

Parent notifications may include:

- Absence notification
- Late notification
- New homework
- Homework overdue
- New exam
- Exam result published
- New announcement
- Financial notification
- Important school/mosque announcement

---

# 11. Student Portal

Students should have their own account and dashboard.

Recommended structure:

```text
Student Portal
├── Dashboard
├── My Profile
├── My Class
├── My Section
├── Attendance
├── Subjects
├── Teachers
├── Exams
├── Grades
├── Homework
├── Announcements
├── Files
└── Notifications
```

A student must only access their own records unless an explicit permission says otherwise.

---

# 12. Student Dashboard

Example:

```text
Ahmed Ali

Quran — Section A

Attendance
92%

Upcoming Exams
- Quran — Sep 12
- Tajweed — Sep 15

Pending Homework
- Memorization 13

Teachers
- Sheikh Omar
- Sheikh Ahmed
```

---

# 13. Student Profile

The student can see authorized profile information:

```text
My Profile

Name: Ahmed Ali
Date of Birth: ...
Email: ...
Phone: ...
Class: Quran Beginners
Section: A
```

Sensitive or administrative fields should only be displayed when allowed by policy.

---

# 14. Student Attendance

The student should be able to see:

- Daily attendance
- Monthly attendance
- Attendance history
- Present count
- Absent count
- Late count
- Excused count
- Attendance percentage

Example:

```text
September Attendance

Present: 18
Absent: 1
Late: 2
Excused: 1

Attendance: 90%
```

---

# 15. Student Subjects

Example:

```text
My Subjects

Quran
Teacher: Sheikh Omar

Tajweed
Teacher: Sheikh Ahmed

Arabic
Teacher: Sheikh Yusuf
```

---

# 16. Student Teachers

The student can see teachers associated with their current subjects/sections.

Teacher information should be limited to information intended for students.

---

# 17. Student Exams & Grades

Student exam page:

```text
Exams

Quran Exam 1
Date: Sep 12
Status: Upcoming

Tajweed Exam 1
Date: Sep 15
Status: Upcoming

Arabic Exam
Status: Completed
Grade: 89/100
```

---

# 18. Student Homework

The student should be able to:

- View homework
- View instructions
- View due date
- Submit work where supported
- View submission status
- View teacher feedback

Example:

```text
Homework #13

Subject: Quran
Due: Sep 10

Status: Pending

[Submit Homework]
```

---

# 19. Sheikh / Teacher Portal

The Sheikh/Teacher portal should focus on assigned academic responsibilities.

```text
Teacher Portal
├── Dashboard
├── My Sections
├── Students
├── Attendance
├── Homework
├── Exams
├── Grades
├── Announcements
├── Financial Ledger
└── Notifications
```

A teacher must only access sections assigned to them.

---

# 20. Teacher Dashboard

Example:

```text
Sheikh Omar

My Sections
────────────────────
Quran — Section A
Students: 15

Quran — Section B
Students: 18

Today's Tasks
────────────────────
- Attendance
- Homework review
- Exam grading
```

---

# 21. Teacher Section Page

Clicking a section should show:

```text
Quran / Section A

Teacher: Sheikh Omar
Students: 15

Tabs:
├── Students
├── Attendance
├── Homework
├── Exams
├── Grades
└── Announcements
```

---

# 22. Teacher Student List

Example:

| Student | Status | Attendance | Actions |
|---|---|---:|---|
| Ahmed Ali | Active | 92% | View |
| Omar Ahmed | Active | 87% | View |
| Yusuf Ali | Active | 95% | View |

The teacher should not see students from sections that are outside their assigned scope.

---

# 23. Teacher Attendance

The teacher should be able to take attendance for assigned sections.

Workflow:

```text
My Sections
→ Section A
→ Attendance
→ Select Date
→ Create/Open Session
→ Mark Students
→ Save
```

Statuses:

```text
present
absent
late
excused
```

The teacher should only be able to modify attendance when their permissions allow it.

---

# 24. Attendance Data Model

## Attendance Sessions

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

## Attendance Records

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

There must not be duplicate attendance records for the same student and session.

---

# 25. Financial Management for Sheikhs

Sheikhs who handle money need a financial ledger.

The system must record actual financial transactions rather than relying on a manually edited balance.

Examples:

```text
Money received from a student
Money received from a parent
Money given to the mosque
Refund
Adjustment
Transfer to another person
```

---

# 26. Financial Transaction Model

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

Transaction types:

```text
charge
payment
refund
transfer
adjustment
```

Directions:

```text
money_in
money_out
```

The final accounting semantics should be centralized in the finance service.

---

# 27. Sheikh Financial Dashboard

Example:

```text
Financial Dashboard

Total Received:       €500
Total Paid/Transferred: €350
Current Balance:      €150

Recent Transactions
────────────────────────────────────
Sep 05   Ahmed       Received    €50
Sep 05   Omar        Received    €50
Sep 06   Mosque      Transfer    €150
Sep 06   Yusuf       Received    €100
```

---

# 28. Recording Money Received

When a Sheikh receives money:

```text
Add Transaction

Person: Ahmed Ali
Amount: €50
Type: Payment
Direction: Money In
Description: Monthly fee
Date: Sep 05

[Save]
```

After saving, the transaction becomes part of the permanent financial ledger.

---

# 29. Person-to-Person Transfers

If one person gives money to another, both sides must be represented.

Example:

```text
Ahmed gives Sheikh Omar €50
```

The system records:

```text
Ahmed
  → €50 outgoing

Sheikh Omar
  → €50 incoming
```

The transaction should contain:

```text
person_id
related_person_id
amount
transaction_type = transfer
```

This allows the system to answer:

- Who paid?
- Who received?
- How much?
- When?
- Why?
- Who recorded it?

---

# 30. Sheikh Money Handover

If a Sheikh collects money and later hands it to the mosque:

```text
Sheikh Omar
    ↓
Receives €500
    ↓
Hands €350 to Mosque
    ↓
Remaining €150
```

The system should show:

```text
Total received:       €500
Total handed over:    €350
Remaining:            €150
```

This must be calculated from the ledger.

---

# 31. Financial Corrections

Financial records should not normally be deleted.

If a mistake occurs:

```text
Original transaction
        ↓
Reversal / Adjustment
        ↓
Corrected financial position
```

This preserves financial history and auditability.

---

# 32. Financial Permissions

Suggested permissions:

```text
finance.view
finance.create
finance.update
finance.adjust
finance.transfer
finance.report
```

A Sheikh should only receive the finance permissions explicitly granted to their role.

If a Sheikh can record money but cannot edit old transactions, this should be represented as separate permissions.

---

# 33. Parent Financial Visibility

If the project requires parents to see student-related financial information, expose only the financial records belonging to their authorized children or family account.

Example:

```text
Ahmed Ali

Fees
----------------------------
September Fee     €50
October Fee       €50

Paid:             €50
Outstanding:      €50
```

Parents must never see another family's financial information.

---

# 34. Financial Privacy

Financial data is sensitive.

Rules:

1. Students should not automatically see parent financial records.
2. Parents should only see their own authorized financial information.
3. Teachers should only see financial information required for their financial responsibilities.
4. Admins can access finance according to administrative permissions.
5. Every financial modification should be auditable.

---

# 35. Role and Scope Matrix

| Feature | Admin | Parent | Student | Sheikh/Teacher |
|---|---|---|---|---|
| Own Profile | Full | Own | Own | Own |
| Student Profile | Full | Own Children | Own | Assigned Students |
| Classes | Full | Read Relevant | Read Own | Assigned |
| Sections | Full | Read Child | Read Own | Assigned |
| Attendance | Full | Read Children | Read Own | Assigned Sections |
| Subjects | Full | Read Children | Read Own | Assigned |
| Teachers | Full | Read Relevant | Read Relevant | Relevant |
| Exams | Full | Read Children | Read Own | Assigned |
| Grades | Full | Read Children | Read Own | Assigned |
| Homework | Full | Read Children | Read/Submit | Assigned |
| Announcements | Full | Relevant | Relevant | Assigned |
| Finance | Full | Own/Children if enabled | Usually No | According to Finance Permission |

---

# 36. Authorization Rules

Authorization must be evaluated using both:

```text
Role
+
Resource Scope
```

Examples:

```text
Parent
→ Can access student only if parent_students contains the relationship.

Student
→ Can access student record only when student.id == currentUser.student_id.

Teacher
→ Can access section only when an active section_teachers relationship exists.

Teacher
→ Can access student only when the student is enrolled in an assigned section.

Finance user
→ Can create/edit financial transactions only when the corresponding finance permission exists.
```

Do not rely only on frontend route protection.

All scope checks must also be enforced server-side.

---

# 37. Recommended Navigation

## Parent

```text
Dashboard
My Children
  └── Child Profile
      ├── Attendance
      ├── Subjects
      ├── Teachers
      ├── Exams
      ├── Grades
      ├── Homework
      └── Announcements

Notifications
Profile
```

## Student

```text
Dashboard
My Profile
My Class
My Section
Attendance
Subjects
Teachers
Exams
Grades
Homework
Announcements
Notifications
```

## Sheikh / Teacher

```text
Dashboard
My Sections
  └── Section
      ├── Students
      ├── Attendance
      ├── Homework
      ├── Exams
      ├── Grades
      └── Announcements

Finance
Notifications
Profile
```

---

# 38. Audit Requirements

Audit logs should cover:

### Parent

- Parent created
- Parent/student relationship created
- Parent/student relationship removed

### Student

- Student profile changed
- Enrollment changed
- Transfer between sections

### Teacher

- Teacher assigned to section
- Teacher removed from section
- Student-related actions

### Attendance

- Session created
- Attendance recorded
- Attendance modified
- Attendance corrected

### Finance

- Transaction created
- Transaction updated
- Transaction reversed
- Transfer created
- Financial handover recorded

Audit records should include:

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

# 39. Notifications

Important events should be able to trigger notifications.

Examples:

```text
Student absent
        ↓
Parent notification

New exam
        ↓
Student + Parent notification

Exam result published
        ↓
Student + Parent notification

New homework
        ↓
Student notification

Financial payment recorded
        ↓
Relevant authorized notification
```

Notification visibility must respect the same authorization scope as the underlying resource.

---

# 40. Database Relationship Summary

```text
users
 │
 ├── parents
 │      │
 │      └── parent_students ─── students
 │                                │
 │                                ├── section_students ─── sections
 │                                │                         │
 │                                │                         └── classes
 │                                │
 │                                ├── attendance_records
 │                                ├── exams / grades
 │                                └── homework / submissions
 │
 └── teachers
        │
        └── section_teachers ─── sections
```

Financial system:

```text
people / users
      │
      └── financial_transactions
              ├── payments
              ├── charges
              ├── transfers
              ├── refunds
              └── adjustments
```

---

# 41. Implementation Checklist

## Parent

- [ ] Parent account
- [ ] Parent-to-student relationship
- [ ] Multiple children supported
- [ ] Parent dashboard
- [ ] Child profile
- [ ] Child attendance
- [ ] Child subjects
- [ ] Child teachers
- [ ] Child exams
- [ ] Child grades
- [ ] Child homework
- [ ] Notifications
- [ ] Financial visibility if enabled

## Student

- [ ] Student login
- [ ] Student dashboard
- [ ] Profile
- [ ] Class/section
- [ ] Attendance
- [ ] Subjects
- [ ] Teachers
- [ ] Exams
- [ ] Grades
- [ ] Homework
- [ ] Announcements
- [ ] Notifications

## Sheikh / Teacher

- [ ] Teacher login
- [ ] Teacher dashboard
- [ ] Assigned sections
- [ ] Assigned students
- [ ] Attendance
- [ ] Homework
- [ ] Exams
- [ ] Grades
- [ ] Announcements
- [ ] Financial ledger
- [ ] Money received
- [ ] Transfers/handover
- [ ] Financial audit

## Security

- [ ] Server-side authorization
- [ ] Parent scope guard
- [ ] Student self-scope guard
- [ ] Teacher section-membership guard
- [ ] Finance permission guard
- [ ] Audit logging
- [ ] Financial transaction immutability/reversal policy
- [ ] No cross-family data access
- [ ] No cross-section teacher access

---

# 42. Definition of Done

The portal system is complete when:

1. A parent can log in and see all authorized children.
2. A parent can open each child and view the child's authorized academic information.
3. A student can log in and see their own academic dashboard.
4. A student can see attendance, subjects, teachers, exams, grades, homework, and announcements.
5. A Sheikh/Teacher can log in and see only assigned sections.
6. A Sheikh/Teacher can view students belonging to assigned sections.
7. A Sheikh/Teacher can take attendance for assigned sections.
8. Attendance history and calculations are correct.
9. A Sheikh with finance permissions can record money received.
10. The system can calculate money received, transferred, and remaining.
11. Person-to-person transfers preserve both sides of the transaction.
12. Financial corrections preserve history through reversals/adjustments.
13. Parents cannot access other families.
14. Students cannot access other students.
15. Teachers cannot access unassigned sections.
16. Financial access is controlled by explicit permissions.
17. Important changes are recorded in the audit log.
18. Automated tests cover the critical authorization, attendance, and financial workflows.
