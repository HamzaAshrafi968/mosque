# AGENTS.md

## Project overview
Laravel 12 (PHP 8.2+) multi-tenant dental clinic management app. API + Blade frontend, Sanctum auth, Vite 7 + Tailwind CSS 4.

## Key commands

```bash
composer setup          # Full bootstrap: install deps, copy .env, key:generate, migrate, npm install + build
composer dev            # Concurrent dev stack: artisan serve + queue:listen + pail (logs) + vite
composer test           # config:clear + php artisan test (uses SQLite :memory:)
npm run build           # Vite production build
npm run dev             # Vite dev server with HMR
```

## Architecture

### Multi-tenant
- Every authenticated user belongs to a `Tenant` (created on register via `RegisterService`).
- `InitializeTenant` middleware sets `config('app.current_tenant_id')` from the user's `tenant_id`.
- Models using `MultiTenantTrait` automatically scope all queries by `tenant_id` via a global scope.
- Models **without** `MultiTenantTrait`: `Tenant`. Queries on these are unscoped.
- To bypass tenant scope: `$model->withoutGlobalScope('tenant')`.

### UUIDs
- All models use UUID strings as primary keys, not auto-incrementing integers. Use `UuidTrait` on all new models.
- Foreign key columns referencing UUIDs must be `string` type, not `unsignedBigInteger`.

### Route structure
| File | Auth | Purpose |
|------|------|---------|
| `routes/web.php` | web session / `role:doctor` / `role:admin` | Blade views: public booking, doctor dashboard, admin dashboard |
| `routes/api.php` | `auth:sanctum` + `InitializeTenant` | REST API consumed by mobile apps |

Public API endpoints (no auth): `POST /api/login`, `POST /api/register`, `GET /api/appointments/public/*`.

### Controllers
- `app/Http/Controllers/Api/` — REST API controllers
- `app/Http/Controllers/` root — Blade-based controllers (`AdminDashboardController`, `DoctorDashboardController`, `PublicController`)
- `app/Http/Middleware/InitializeTenant.php` — sets tenant context per request

### Key models (all UUID + multi-tenant scoped unless noted)
`User`, `Patient`, `Appointment`, `TreatmentSession`, `SessionTreatment`, `TreatmentType`, `Invoice`, `InvoiceItem`, `Payment`, `Prescription`, `Expense`, `Medication`, `ClinicSetting`, `PatientAssessment`, `WorkingHour`, `WorkingHourRange`, `WhatsappMessage`, `Attachment`, `Notification`, `SyncMetadata`, `Doctor` (scoped via MultiTenantTrait). `Tenant` (unscoped).

## Database
- Local dev default: SQLite (`database/database.sqlite`), transaction_mode `DEFERRED`.
- MySQL support exists; concurrency-safe config for production.
- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database` — runs `queue:listen` (not `queue:work`) in dev via `composer dev`.
- Migrations: 62 files, includes multi-tenant column additions (`add_tenant_id_to_all_tables`).

## Testing
- PHPUnit 11.5, SQLite in-memory (`:memory:`) for tests.
- `php artisan test` or `composer test`.
- Run a single test: `php artisan test --filter=TestName`.
- `tests/TestCase.php` uses `RefreshDatabase` + resets tenant config in `tearDown`.

## Code style
- Laravel Pint (`vendor/bin/pint`) is available for formatting, no `pint.json` config.
- 4-space indentation (`.editorconfig`), LF line endings, UTF-8.
- No CI workflows present.

## Removed modules
Lab, Supplier, and Center modules were purged (see `CHANGES.md`). Do not add them back.
