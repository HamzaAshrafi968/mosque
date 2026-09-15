# AGENTS.md

## Project overview
Laravel 12 (PHP 8.2+) multi-mosque (multi-tenant) Islamic school management app: students, teachers, classes/sections, subjects, schedules, attendance, exams/grades, homeworks, lessons, announcements, messages, Quran review + reward points, plus a central "مدير الجوامع" (super admin) area, RBAC (roles/permissions/scopes) and the mosque/school spec in `.agents/mosque_management_implementation.md`. Blade RTL Arabic UI + REST API (Sanctum), Vite 7 + Tailwind CSS 4.

Demo accounts (seeded): super@mosque.test / password, admin@mosque.test / password, teacher@mosque.test / password.

## Key commands

```bash
composer setup          # Full bootstrap: install deps, copy .env, key:generate, migrate, npm install + build
composer dev            # Concurrent dev stack: artisan serve + queue:listen + pail (logs) + vite
composer test           # config:clear + php artisan test (uses SQLite :memory:)
php artisan migrate:fresh --seed   # fresh DB + demo data + full Quran (114 surahs)
npm run build           # Vite production build
npm run dev             # Vite dev server with HMR
```

## Architecture

### Multi-tenancy ("mosques")
- `Tenant` = mosque/جامع. Domain models use `MultiTenantTrait` → global `tenant` scope filtered by `config('app.current_tenant_id')`.
- `InitializeTenant` sets that config from `users.tenant_id`. **`users.tenant_id` is nullable** — only the global super admin has `null`.
- Super admin (role string `super_admin`, role row `roles.code = super_admin`, tenant-less) can "enter" a mosque: `session('super_admin_mosque_id')`; then `EnsureRole`/`InitializeTenant` treat them as that mosque's admin and all admin routes/controllers run scoped to that mosque.
- Bypass tenant scope with `withoutGlobalScope('tenant')` (used by super-admin cross-mosque queries).

### RBAC
- Tables: `roles` (tenant_id nullable = global), `role_user`, `permissions` (seeded catalog), `permission_role` (with `scope` = global/mosque/class/section/own).
- Catalog = `app/Support/PermissionCatalog.php` (codes `resource.action` + Arabic labels + default grant maps for `mosque_manager`/`teacher`). Add new permissions there.
- `app/Services/RoleService.php` — seeds catalog, global super-admin role, per-mosque default roles, role assignment; a User `created` hook auto-attaches default role from the legacy `users.role` string.
- `app/Services/AuthorizationService.php` — `can($user, 'resource.action', $subject?, $owns?)` implements permission → scope → mosque isolation chain. Middleware alias `permission:`.
- `EnsurePermission` now guards every admin/teacher web route and every `/api/v1/admin|teacher/*` endpoint (self-service dashboard/profile/logout/notifications stay open). It resolves the route-bound model and enforces `own` scope via owner columns (`user_id`, `awarded_by`, `created_by`, `recorded_by`, `teacher_id`, `supervisor_id`); subjects without owner columns fall back to tenant isolation.
- Legacy route gating still uses the `users.role` string via `EnsureRole` (`role:admin` / `role:teacher` / `role:super_admin`).
- Permission changes (super-admin role matrix → `RoleService::syncRolePermissions`) take effect immediately: no permission cache, `scopesFor()` queries `permission_role` live. `tests/Feature/PermissionEnforcementTest.php` covers grant/revoke on web + API + matrix + sidebar.
- Per-user permission overrides (`permission_user`, migration `2026_09_13_000009`): مدير الجوامع edits one user's matrix at `/super-admin/mosques/{mosque}/users/{user}/edit` (the full user form: data + role + specialty + دوامات + matrix in one save; the standalone `.../permissions` page stays for direct links). Any mosque user: teacher/student/manager/guardian; super admins are excluded. A direct row wins over role grants — `effect=deny` revokes, an allow row replaces the role scopes; absent rows fall back to roles (`AuthorizationService::scopesFor()`). `global` is rejected for mosque-bound users. `RoleService::syncUserPermissions()` + `tests/Feature/UserPermissionOverrideTest.php`.
- Per-shift program access (`program_study_session`, migration `2026_09_13_000011`): each study session lists the programs available in it (no links = all programs). Saved from `/admin/sessions` via `POST admin.sessions.programs` (`permission:sessions.update`); schedule forms/API filter programs by the active/selected shift (`ProgramService::sessionProgramMap/availablePrograms`) and the server rejects a program not enabled for the shift (`ResolveScheduleProgramAction`). `tests/Feature/SessionProgramAccessTest.php`.
- Teacher multi-shift (`study_session_teacher`, migration `2026_09_15_000001`): a teacher can belong to several دوامات (e.g. الأول والثالث). `teachers.study_session_id` stays the primary/first choice; `Teacher::studySessions()` is the pivot source of truth and the `study_session` global scope matches primary OR pivot (`Teacher::applyStudySessionScope`). Teacher/user forms submit `study_session_ids[]` (empty = كل الدوامات), and `StudySession::teachers()` is a BelongsToMany. `tests/Feature/StudySessionsTest.php`.
- Per-shift classrooms/sections (`classrooms.study_session_id`, migration `2026_09_15_000002`): each دوام has its own صفوف وشعب. `Classroom` uses the `study_session` global scope but an unbound classroom (صف مشترك) stays visible in every shift; sections of a bound classroom always inherit its shift (`Section` saving hook), and binding/moving a classroom cascades its sections + students + schedules (`App\Actions\Admin\Classroom\SyncClassroomShiftAction`; bulk assign type `classrooms`). Schedule forms/API inherit the classroom/section shift and reject a mismatched `study_session_id` (`ResolveScheduleProgramAction::assertClassroomSession`). `tests/Feature/ClassroomSessionScopeTest.php`.
- `MosqueUserController` maps mosque roles to legacy `users.role` (manager→admin, guardian→guardian, student→student, teacher/custom→teacher) and provisions the matching profile (Teacher/Guardian/Student) so portals work right after creation. Teacher defaults now include `finance.view/create/adjust/transfer` (own), backfilled to existing teacher roles by `2026_09_13_000010`. `MosqueUserController::store` detaches the legacy auto-attached role so a user keeps only the requested role.
- `Admin\UserController` keeps `role_user` pivots in sync on role change (and guards the last mosque manager); `MosqueUserController` already did. `TeacherPermissionChange`-style role edits elsewhere must do the same.
- New catalog codes `parents.*`, `quran_review.*`, `reward_points.*` are backfilled to existing system roles by migration `2026_09_13_000001_backfill_new_permission_grants.php`; `programs.*` by `2026_09_13_000006_backfill_program_permissions_and_defaults.php` (which also provisions the five default schedule programs per mosque).

### Teacher payroll / رواتب المعلمين
- Managers set `teachers.monthly_salary` (nullable decimal, migration `2026_09_15_000002`) either in the teacher create/edit form or inline at `/admin/payroll` (`Admin\PayrollController`, `permission:finance.view` to view, `finance.create` to save salary/pay).
- Work hours stay a recurring weekly schedule (`teacher_work_hours`); `TeacherWorkHour::monthlyHours()` / `hoursBetween()` expand it to monthly/period totals by counting each weekday's occurrences. `hoursBetweenFromPeriods`/`monthlyHoursFromPeriods` are the batch variants. Admin + teacher work-hours pages, teacher dashboard and profile show the monthly total with a month picker (Arabic labels via `QuranProgramSettings::monthLabel`).
- `App\Services\PayrollService` records a salary payment as a normal `payment`/`money_in` ledger row, then notifies the teacher's user via `NotificationService` ("تم إيداع دفعة لك", url `teacher.finance.index`). The accumulated-hours counter is derived, never stored: it counts from the day after the last non-reversed payment (or `hired_at`), so recording a payment resets it to zero. Reversing a payment restores it.
- The teacher finance page is deposit-only: salary / monthly hours / hours-since-last-payment / last payment cards plus a "الدفعات الواردة إليّ" list (money_in rows, reversed ones flagged). The old receive/transfer/adjust routes stay for API/back-compat but are no longer linked in the teacher UI.
- Tests: `tests/Feature/TeacherPayrollTest.php`.

### Audio announcements (auto-delete after a week)
- Managers attach one audio file to an announcement (`announcements.audio_path/audio_original_name/expires_at`, migration `2026_09_15_000003`; `body` is optional when audio is present). Validation/storage lives in `app/Support/AudioUpload.php` (`mimetypes:audio/*`, ≤20 MB, UUID filename with MIME-derived extension).
- Audio announcements get `expires_at = now()->addWeek()` by default (`auto_delete` checkbox in the admin form, `auto_delete` boolean in `POST /api/v1/admin/announcements` to opt out). `announcements:purge-expired` (scheduled hourly in `routes/console.php`, cross-tenant via `withoutGlobalScope('tenant')`) hard-deletes expired rows; the model `deleting` hook removes the audio file. Portals + dashboards hide expired rows immediately via `Announcement::notExpired()`. `tests/Feature/AudioAnnouncementTest.php`.

### مراجعة 5 — الخمسات (khamsa reviews)
- كل جزء = ٤ خمسات × ٥ صفحات؛ `App\Support\QuranJuzMap` يحمل خريطة صفحات الأجزاء الثلاثين والخمسة الأخيرة تأخذ الباقي (جزء ١: 16–21، جزء ٦: 117–120، جزء ٣٠: 597–604). الميزة لا تعتمد على `quran_ayahs.juz` (لكنه صُحّح أيضاً عبر migration `2026_09_15_000007` + `database/data/quran_pages.json` + `fetch-quran-pages.php`).
- لا تُفتح خمسات جزء إلا بوجود سجل في `student_juz_memorizations`: يدوي من شبكة «الأجزاء المحفوظة» في نموذج الطالب (`memorized_juz_numbers[]` + `memorized_juz_numbers_present`)، أو تعبئة من `memorized_juz` (intake)، أو تلقائياً عند تغطية تسميع «جديد» كل صفحات الجزء (hook `saved` في `QuranRecitationSession`).
- `quran_khamsa_reviews` (رأس: طالب/أستاذ/دوام/تاريخ/حالة pending|completed|cancelled، `StudySessionScopedTrait` للفلترة بالدوام النشط) + `quran_khamsa_review_items` (juz/khamsa/from_page/to_page/status/result/completed_by/quran_review_session_id). `App\Services\QuranKhamsaService` يفرض القفل وتطابق الدوام ومنع تكرار الخمسة قيد المراجعة، والرأس يُغلق تلقائياً عند اكتمال عناصره.
- الشاشات: `/admin/quran/khamsa` و`/teacher/quran/khamsa` (تخصيص/عرض/إنهاء/إلغاء + إدارة الأجزاء المحفوظة) و`/student/quran-khamsa` (عرض فقط). الصلاحيات `quran_khamsa.view/create/update/complete` + `quran.memorization.manage` (mosque للمدير، own للأستاذ؛ backfill `2026_09_15_000006`). `tests/Feature/KhamsaReviewTest.php` + `tests/Feature/QuranJuzMapTest.php`.

### Route structure
| File / prefix | Auth | Purpose |
|------|------|---------|
| `routes/web.php` → `/admin/*` | `role:admin` | Mosque manager Blade UI (students/teachers/classes/schedules/attendance/exams/grades/reports/announcements/quran/reward-points/users) |
| `routes/web.php` → `/teacher/*` | `role:teacher` | Teacher Blade UI |
| `routes/web.php` → `/super-admin/*` | `role:super_admin` | Central management: mosques CRUD, per-mosque users + roles + permission matrix, enter-mosque context |
| `routes/api.php` → `/api/v1/*` | `auth:sanctum` + `tenant` | Mobile API (login/logout/me, admin & teacher areas) |

No public register (removed). Login only. Unauthenticated endpoints: `POST /api/v1/login`.

### Controllers
- `app/Http/Controllers/Admin/`, `Teacher/`, `SuperAdmin/` — Blade controllers (sub-`Actions/` for shared ops).
- `app/Http/Controllers/Api/V1/{Admin,Teacher}` — REST (extends `Api/BaseApiController`, teachers extend `Api/V1/Teacher/BaseTeacherController`).
- `app/Http/Controllers/Api/Admin|Teacher` (no V1) were removed — do not reintroduce.

### Key models (UUID PK + multi-tenant unless noted)
`User` (tenant_id nullable), `Tenant` (unscoped), `Role`, `Permission` (unscoped), `Student`, `Teacher`, `Classroom`, `Section`, `Subject`, `Schedule`, `Attendance`, `Exam`, `Grade`, `Homework`, `HomeworkSubmission`, `Lesson`, `Announcement`, `Message`, `QuranSurah`/`QuranAyah` (unscoped), `QuranReviewSession`, `QuranReviewWord`, `RewardPoint`, `TeacherRating`, `TeacherCertificate`, `StudentJuzMemorization`, `QuranKhamsaReview`, `QuranKhamsaReviewItem`.

### Grade/schedule workflow
- Grades: draft → submitted → approved (teacher `grades.submit`, admin approve). Grades cannot be edited once approved (enforced in `SaveGradesAction`).
- Schedules: no approval workflow yet (spec §14 pending).

### Attendance
- Session-based source of truth (`attendance_sessions` + `attendance_records`, statuses present/late/absent/excused). The sidebar "الحضور والغياب والتأخير" opens `admin.attendance.summary`: a per-student table (حاضر/غائب/متأخر/معذور + percentage via `AttendanceMetricService::studentStats`) filterable by date range and section. Daily sessions stay at `admin.attendance.index` and the per-session grid at `admin.attendance.history`.
- مدير الجوامع can jump straight in: `POST super-admin.mosques.enter` accepts `to=attendance` (button on the central dashboard + mosques list) and lands on the summary inside the mosque context. `tests/Feature/AttendanceSessionsTest.php` covers the page for both the manager and the super admin.

## Database
- SQLite default (`database/database.sqlite`); MySQL supported. SESSION/QUEUE = database; dev runs `queue:listen`.
- ~26 migration files, incl. RBAC (`2026_09_05_00000*`) and tenant management fields (`code/email/status/logo`).

## Testing
- PHPUnit 11.5, SQLite `:memory:`; `composer test` or `php artisan test --filter=TestName`.
- `tests/Feature/`: `AuthTest`, `StudentApiTest`, `DashboardApiTest`, `Phase0RegressionTest` (bug-fix regressions), `AuthorizationTest` (RBAC + isolation + super-admin enter/exit). New spec features should add tests here.
- TestCase resets tenant config in `tearDown`.

## Code style
- Laravel Pint (`vendor/bin/pint --dirty`), 4-space indent, LF, UTF-8. No CI workflows.

## Project docs
- `.agents/mosque_management_implementation.md` — the Arabic implementation spec; Definition of Done in §47. Current status vs spec: foundation + single-mosque academic core + RBAC/super-admin (central dashboard, mosques/users/roles + permission matrix, per-user overrides inside the user form) + schedule specializations/programs (تخصصات الجداول: `programs`/`program_periods`/`program_attributes`, schedule program+period+shift linkage, per-shift program access, dynamic attribute options from students with filters via `options_source`/`options_config`, spec `.agents/mosque_management_schedule_programs.md`) + teacher payroll/رواتب المعلمين (monthly salary, monthly work-hour totals, payment resets the hours counter, teacher deposit-only page + notification) + per-shift classrooms/sections (each دوام has its own صفوف وشعب, cascade on rebinding, schedule shift consistency) + «مراجعة 5» (خمسات: كل جزء ٤ خمسات × ٥ صفحات، قفل الأجزاء غير المحفوظة، تخصيص بأستاذ ودوام، بوابة الطالب) done. **Not yet built**: schedule approval workflow, grade reject, student transfer, soft deletes, custom fields + field-level permissions, audit logs, notifications, reports export, schedule/grade full 5-state engine, per-spec test matrix for those features.
- Removed legacy: dental-clinic modules (Lab/Supplier/Center), pre-V1 API controllers, public registration.
