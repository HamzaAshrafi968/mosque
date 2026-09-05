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
- Legacy route gating still uses the `users.role` string via `EnsureRole` (`role:admin` / `role:teacher` / `role:super_admin`).
- Note: legacy Admin/Teacher user creation only updates `users.role`, not pivots, on edit; keep the string in sync with attached roles.

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
`User` (tenant_id nullable), `Tenant` (unscoped), `Role`, `Permission` (unscoped), `Student`, `Teacher`, `Classroom`, `Section`, `Subject`, `Schedule`, `Attendance`, `Exam`, `Grade`, `Homework`, `HomeworkSubmission`, `Lesson`, `Announcement`, `Message`, `QuranSurah`/`QuranAyah` (unscoped), `QuranReviewSession`, `QuranReviewWord`, `RewardPoint`, `TeacherRating`, `TeacherCertificate`.

### Grade/schedule workflow
- Grades: draft → submitted → approved (teacher `grades.submit`, admin approve). Grades cannot be edited once approved (enforced in `SaveGradesAction`).
- Schedules: no approval workflow yet (spec §14 pending).

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
- `.agents/mosque_management_implementation.md` — the Arabic implementation spec; Definition of Done in §47. Current status vs spec: foundation + single-mosque academic core + RBAC/super-admin (central dashboard, mosques/users/roles + permission matrix) done. **Not yet built**: schedule approval workflow, grade reject, student transfer, soft deletes, custom fields + field-level permissions, audit logs, notifications, reports export, schedule/grade full 5-state engine, per-spec test matrix for those features.
- Removed legacy: dental-clinic modules (Lab/Supplier/Center), pre-V1 API controllers, public registration.
