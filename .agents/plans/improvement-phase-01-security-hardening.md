# Phase 01 — Security Hardening (Critical)

## Task 1.1: Add MultiTenantTrait to unscoped tenant-aware models

**Files affected:**
- `app/Models/InvoiceItem.php` — add `MultiTenantTrait`, add `tenant_id` to `$fillable` and casts
- `app/Models/Prescription.php` — add `MultiTenantTrait`, add `tenant_id` to `$fillable` and casts
- `app/Models/WhatsappMessage.php` — add `MultiTenantTrait` + `UuidTrait`, add `tenant_id` to `$fillable`, set `$incrementing = false`, `$keyType = 'string'`
- `app/Models/Attachment.php` — add `MultiTenantTrait` + `UuidTrait`, add `tenant_id` to `$fillable`, set `$incrementing = false`, `$keyType = 'string'`
- `app/Models/Notification.php` — add `MultiTenantTrait`
- `app/Models/SyncMetadata.php` — add `MultiTenantTrait`

**Why:** The `2026_03_19_115142_add_tenant_id_to_all_tables` migration added `tenant_id` columns to these tables, but their Eloquent models lack the global scope. Reads leak across tenants; writes never auto-populate `tenant_id`.

**Verify:**
- Run `php artisan test --filter=TenantScopedModelTest` — existing tests should pass
- Write a test asserting cross-tenant queries return empty for each repaired model

---

## Task 1.2: Implement authorization layer (Policies/Gates)

**Files to create:**
- `app/Policies/PatientPolicy.php`
- `app/Policies/AppointmentPolicy.php`
- `app/Policies/TreatmentSessionPolicy.php`
- `app/Policies/InvoicePolicy.php`
- `app/Policies/PaymentPolicy.php`
- `app/Policies/ExpensePolicy.php`
- `app/Policies/WorkingHourPolicy.php`
- `app/Policies/TreatmentTypePolicy.php`
- `app/Policies/PrescriptionPolicy.php`
- `app/Policies/PatientAssessmentPolicy.php`
- `app/Policies/NotificationPolicy.php`
- `app/Policies/UserPolicy.php`
- And potentially `app/Providers/AuthServiceProvider.php` to register them (Laravel 11+ auto-discovers from `app/Policies/` if naming conventions match, but explicit registration may be needed)

**Key rules per policy:**
- **Ownership**: users can only access resources where `doctor_id === auth()->id()` or `patient_id` belongs to their patient
- **Admin override**: users with `role === 'admin'` can access all within their tenant
- **Tenant boundary**: all scoped queries already enforce tenant via global scope; policies enforce *within* tenant (doctor ownership)

**Affected controllers — add `$this->authorize(...)` calls:**
- `PatientController` — `show`, `update`, `destroy`, `balance`, `summary`
- `AppointmentController` — `show`, `update`, `destroy`, `finish`
- `TreatmentSessionController` — `show`, `update`, `destroy`, `finishSession`
- `SessionTreatmentController` — `show`, `update`, `destroy`, `deleteMultiple`
- `InvoiceController` — `show`, `update`, `destroy`
- `PaymentController` — `show`, `update`, `destroy`, `patientPayments`
- `ExpenseController` — `show`, `update`, `destroy`, `byDoctor`
- `WorkingHourController` — `show`, `addRange`, `replaceRanges`, `updateRange`, `deleteRange`
- `TreatmentTypeController` — `show`, `update`, `destroy`
- `PrescriptionController` — `show`, `update`, `destroy`
- `PatientAssessmentController` — `show`, `update`, `destroy`
- `NotificationController` — `send`, `markAsRead`, `markAllAsRead`
- `UserController` — `destroy`, `updateToken`
- `StatisticsController` — `index` (restrict to `role:admin || role:doctor`)

**Verify:**
- Every protected endpoint has at least one positive (own resource) and one negative (another's resource) test
- `php artisan make:policy` is not available in Laravel 12 without `--model` flag — create manually

---

## Task 1.3: Harden SyncController bulkPush

**Files affected:**
- `app/Http/Controllers/Api/SyncController.php`
- `app/Http/Requests/SyncBulkPushRequest.php` (new — create `app/Http/Requests/` directory)

**Changes:**
1. Create `SyncBulkPushRequest` with per-entity-type validation rules. At minimum:
   - Validate that `id` (if provided) belongs to the requesting user's tenant and doctor
   - Validate required fields per entity type (e.g., `patient_id` must exist in same tenant)
   - Strip `tenant_id` from `data` — always use the authenticated user's tenant
   - Limit batch size (e.g., `max:100` entities)
2. Remove `doctor_id` / `tenant_id` override from input — always use `auth()->user()->tenant_id` and `auth()->id()`
3. Validate that `action=delete` only targets records owned by the current user/doctor

**Why:** Currently any authenticated user can upsert/delete any record across any tenant by including `tenant_id` in the payload.

**Verify:**
- Test that `bulkPush` with another tenant's `tenant_id` writes to the correct (own) tenant
- Test that deleting another doctor's record returns 403
- Test that invalid fields per entity type get rejected

---

## Task 1.4: Fix publicIndex tenant data leak

**Files affected:**
- `app/Http/Controllers/Api/AppointmentController.php` — `publicIndex()` method

**Changes:**
- Remove `Appointment::withoutTenantScope()` or scope it strictly to the requested `tenant_id` (if passed as query param) or return empty
- Alternatively, accept an explicit `tenant_id` query parameter and validate it against a non-sensitive lookup (e.g., `Tenant::where('id', $request->tenant_id)->exists()`)

**Why:** Currently `publicIndex` bypasses tenant scoping entirely, exposing all tenants' appointments with optional filters.

**Verify:**
- Call `GET /api/appointments/public` without a tenant filter → returns empty or minimal data
- Cross-tenant appointment data is not visible

---

## Task 1.5: Add rate limiting to auth and public endpoints

**Files affected:**
- `bootstrap/app.php` — add `RateLimiter` configuration
- `routes/api.php` — apply throttle middleware to `login`, `register`
- `routes/web.php` — apply throttle to public booking/survey routes

**Changes:**
```php
// bootstrap/app.php
RateLimiter::for('login', fn ($job) => Limit::perMinute(5)->by($job->ip()));
RateLimiter::for('public-api', fn ($job) => Limit::perMinute(30)->by($job->ip()));
```

**Why:** No rate limiting on `login`, `register`, or public booking endpoints makes them abuse/spam vectors, especially since CSRF is exempted for booking/survey routes.

**Verify:**
- Exceeding the limit returns `429 Too Many Requests`
- Normal usage works within limits

---

## Task 1.6: Stop leaking error messages in API responses

**Files affected (up to 12 controllers):**
- `AuthController`, `TreatmentSessionController`, `InvoiceController`, `SyncController`, `ClinicSettingController`, `PatientAssessmentController`, `ExpenseController`, `SessionTreatmentController`, `UserController`, `WorkingHourController`, `NotificationController`, `PublicController`

**Changes:**
- Replace `return response()->json(['error' => $e->getMessage()], 500)` with a generic `'Server error'` message
- Log the real exception with `Log::error()`
- In production, the generic message is safe; in debug mode, Laravel's exception handler already shows details

**Why:** Leaking `$e->getMessage()` exposes internal state (SQL errors, validation internals, file paths) to API clients.

**Verify:**
- Trigger a server error → response contains `'Server error'` not the real exception text
- Storage logs contain the real exception
