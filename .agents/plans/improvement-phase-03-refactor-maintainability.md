# Phase 03 — Refactoring & Maintainability (Medium)

## Task 3.1: Split DashboardController into focused controllers + services

**Files affected:**
- `app/Http/Controllers/DashboardController.php` (1720 lines — delete after extraction)
- `app/Http/Controllers/AdminDashboardController.php` (new, admin-only logic)
- `app/Http/Controllers/DoctorDashboardController.php` (new, doctor-only logic)
- `app/Services/StatsService.php` (new, reporting aggregates)
- `app/Services/AvailabilityService.php` (new, slot generation/conflict detection)
- `app/Services/NotificationService.php` (new, appointment/FCM notification dispatch)
- `routes/web.php` — update route mappings

**Breakdown of DashboardController responsibilities:**
| Current method | New home |
|---|---|
| `index`, `statistics`, `getStats` | `StatsService` (shared), called by `AdminDashboardController::index` + `DoctorDashboardController::home` |
| `users`, `showUser`, `toggleUserStatus`, `updateUser`, `resetUserPassword` | `AdminDashboardController` |
| `invoices`, `showInvoice` | `AdminDashboardController::invoices` + `DoctorDashboardController::invoices` |
| `payments` | Split accordingly |
| `expenses` | Split accordingly |
| `patients`, `showPatient` | Split accordingly |
| `medicalSurvey`, `storeMedicalSurvey` | `AdminDashboardController` |
| `appointments`, `showAppointment` | Split accordingly |
| `treatmentSessions`, `showTreatmentSession` | Split accordingly |
| `doctorHome` → `doctorPatients` → etc. (whole doctor section) | `DoctorDashboardController` |
| `updateWorkingHours`, `updateTreatmentPrices` | Extract to `WorkingHourService` / `TreatmentTypeService` |
| `generateInvoiceFromSession`, `doctorShowInvoiceDetail` | `BillingService` + `DoctorDashboardController::showInvoice` |

**Why:** A single 1720-line controller with ~40 methods mixing admin/doctor concerns, inline queries, and raw DB writes is the biggest maintainability debt in the project.

**Verify:**
- All existing dashboard routes work after the split
- `php artisan route:list` shows correct controller mappings
- No functionality is lost

---

## Task 3.2: Extract Form Request classes for all API controllers

**Files to create:**
- `app/Http/Requests/StorePatientRequest.php`
- `app/Http/Requests/UpdatePatientRequest.php`
- `app/Http/Requests/StoreAppointmentRequest.php`
- `app/Http/Requests/UpdateAppointmentRequest.php`
- `app/Http/Requests/StoreTreatmentSessionRequest.php`
- `app/Http/Requests/StoreInvoiceRequest.php`
- `app/Http/Requests/StorePaymentRequest.php`
- `app/Http/Requests/SyncBulkPushRequest.php` (from Phase 01)
- Plus equivalents for every controller that validates input

**Benefits:**
- Reusable validation rules
- Testable in isolation
- Centralized authorization (`authorize()` method)
- Clean controllers

**Changes per controller:**
- Replace `$request->validate([...])` with type-hinted Form Request in method signature
- Add `authorize()` method to each request checking doctor role + tenant context
- Use `$validated = $request->validated()` instead of `$request->all()` or loose `$request->input()` reads

**Why:** Currently every controller does inline validation. Patterns are duplicated, rules cannot be reused, and the `PatientAssessmentController` even validates then ignores the result. Form Requests fix all of these.

**Verify:**
- All existing validation behavior is preserved (same rules, same error messages)
- `$request->validated()` returns only the expected fields

---

## Task 3.3: Move TeethController data to config

**Files affected:**
- `app/Http/Controllers/Api/TeethController.php`
- `config/teeth.php` (new)

**Changes:**
- Extract the 395-line `getTeethData()` array into `config/teeth.php`
- Controller methods read from `config('teeth.teeth')`, `config('teeth.statuses')`, etc.
- Optionally add a service class for complex lookups (byJaw, byQuadrant)

**Why:** Embedded hardcoded data arrays in controllers are not translatable, not configurable, and bloat the file.

**Verify:**
- All teeth endpoints return the same data as before
- `php artisan test` passes (if any tests exist for these endpoints)

---

## Task 3.4: Deduplicate availability/conflict detection logic

**Files affected:**
- `app/Services/AvailabilityService.php` (create)
- `app/Http/Controllers/Api/AppointmentController.php` — remove duplicated logic
- `app/Http/Controllers/Api/TreatmentSessionController.php` — remove duplicated logic
- `app/Http/Controllers/Api/WorkingHourController.php` — remove `timesOverlap`/`checkConflict`
- `app/Http/Controllers/DashboardController.php` — remove duplicated `getAvailableTimes` logic

**Changes:**
- Move `timesOverlap`, `checkConflict`, slot generation, `isOpenAt` logic into `AvailabilityService`
- Provide methods: `getAvailableSlots($doctorId, $date)`, `isTimeSlotAvailable($doctorId, $date, $time, $excludeSessionId = null)`, `findConflicts($doctorId, $date, $startTime, $endTime)`
- Call from all controllers

**Why:** The same slot-checking/conflict-detection logic is copy-pasted across 4 controllers with slight variations — a maintenance nightmare and bug source.

**Verify:**
- Available times, conflict detection, and slot generation produce identical results

---

## Task 3.5: Fix N+1 query patterns via accessor aggregates

**Files affected:**
- `app/Models/Invoice.php` — replace `getTotalPaidAttribute()` with a query scope
- `app/Models/Patient.php` — replace `getTotalBalance()` / `getFinancialSummary()` with scopes or service
- `app/Services/FinancialSummaryService.php` (new) — batch aggregates

**Changes:**
1. For `Invoice`:
   - Change `getTotalPaidAttribute()` from running a fresh `sum()` query to reading from an eager-loadable relationship or a cached attribute
   - Add scope `withPaymentsSummary`: `Invoice::select()->withSum('payments', 'amount')`
2. For `Patient`:
   - Create `FinancialSummaryService::forPatient(Patient $patient)` that batches all sums in one query
   - Or use `withCount` / `withSum` in controller queries instead of relying on model accessors
3. Update affected controllers to use best practice: load all needed aggregates in one query rather than per-access

**Why:** `Invoice` and `Patient` accessor methods fire a fresh aggregate query every time they're accessed. In a list of 50 invoices/patients, that's 100+ extra queries.

**Verify:**
- Patient list endpoint executs only ~5 queries total (down from 5 + 4N)
- Invoice list has same improvement

---

## Task 3.6: Squash duplicate "fix doctors user FK" migrations

**Files affected:**
- 6 migration files (2026_02_23_070000, 2026_02_28_000001, 2026_03_03_000001, 2026_03_16_032324, 2026_03_20_000000, 2026_11_10_000000)
- A new squash migration in `database/migrations/`

**Changes:**
- Create a single new migration that defines the correct `doctors.user_id` foreign key (with proper `onDelete` behavior)
- Add a note in the old files that they are superseded (or delete them in a cleanup PR)
- Update `DatabaseSeeder` / fresh-install migration tests to work without the duplicates

**Why:** 6 migrations attempting to fix the same FK on `doctors` table is the poster child of technical debt. Any fresh `php artisan migrate:fresh` runs all 6 unnecessarily.

**Verify:**
- Fresh migration runs without errors
- `php artisan migrate:fresh` + `php artisan db:seed` works
- The FK constraint on `doctors.user_id` is correct

---

## Task 3.7: Add ClinicSetting cache invalidation on set

**Files affected:**
- `app/Models/ClinicSetting.php` — `set()` method at line 35-41

**Changes:**
- After `updateOrCreate`, add `Cache::forget($cacheKey)` where `$cacheKey` matches the key used in `get()`:
  ```php
  public static function set($key, $value)
  {
      $setting = static::updateOrCreate(
          ['tenant_id' => config('app.current_tenant_id'), 'key' => $key],
          ['value' => $value]
      );
      Cache::forget('clinic_setting_' . config('app.current_tenant_id') . '_' . $key);
      return $setting;
  }
  ```

**Why:** `get()` caches for 3600 seconds; `set()` never clears the cache, so reads return stale values for up to an hour after writes.

**Verify:**
- Set a setting → immediately get it → returns the new value (not cached old value)

---

## Task 3.8: Standardize date/time casts across models

**Files affected:**
- `app/Models/Appointment.php` — `appointment_date` → `'date'`, `appointment_time` → `'datetime:H:i'` (or keep as string but use Carbon casts)
- `app/Models/Invoice.php` — `invoice_date` → `'date'`
- `app/Models/Payment.php` — `payment_date` → `'date'`
- `app/Models/Expense.php` — `expense_date` → `'date'`
- `app/Models/User.php` — `isActive` → `'boolean'`
- `app/Models/Appointment.php` — `reminded_doctor_10min_at` → `'datetime'`

**Changes:**
- Update casts in each model
- Remove redundant manual `Carbon::parse()` calls from controllers and services where the cast now handles conversion
- Verify that `Patient::getDateOfBirthAttribute` can be simplified (or removed) since `datetime:Y-m-d` cast already does the job

**Why:** Inconsistent casting leads to manual Carbon-parsing in multiple controllers, code duplication, and potential format mismatches.

**Verify:**
- Date values are returned in expected formats from API endpoints
- No regression in any Blade view that displays dates
