# Phase 02 — Refactoring (🟡 8-12h)

---

## Task 2.1: Split DashboardController (1430 lines)

**Files affected:**
- `app/Http/Controllers/DashboardController.php` — DELETE
- NEW: `app/Http/Controllers/Doctor/PatientController.php`
- NEW: `app/Http/Controllers/Doctor/AppointmentController.php`
- NEW: `app/Http/Controllers/Doctor/SessionController.php`
- NEW: `app/Http/Controllers/Doctor/InvoiceController.php`
- NEW: `app/Http/Controllers/Doctor/PaymentController.php`
- NEW: `app/Http/Controllers/Doctor/SettingsController.php`
- NEW: `app/Http/Controllers/Doctor/FinancialController.php`
- NEW: `app/Http/Controllers/Doctor/TreatmentFlowController.php`
- `routes/web.php` — update route targets

**Why:** 1430-line god controller with 24+ methods. Hard to navigate, test, and maintain.

**Plan:**
1. Move each logical section to its own controller in `app/Http/Controllers/Doctor/`
2. Each new controller gets only the methods it needs
3. Update `routes/web.php` doctor group to point to new controllers
4. Keep `DoctorDashboardController.php` only for `home()` + dashboard-level methods, or fold it into `Doctor\DashboardController`

**Patch `routes/web.php` doctor group:**
```php
use App\Http\Controllers\Doctor\{
    PatientController as DoctorPatientController,
    AppointmentController as DoctorAppointmentController,
    SessionController as DoctorSessionController,
    InvoiceController as DoctorInvoiceController,
    PaymentController as DoctorPaymentController,
    SettingsController as DoctorSettingsController,
    FinancialController as DoctorFinancialController,
    TreatmentFlowController as DoctorTreatmentFlowController,
};
```

**Verify:** Every doctor route renders without error (manual click-through).

---

## Task 2.2: Replace inline $request->validate() with Form Requests

**Files affected:** All controllers under `app/Http/Controllers/` (60+ inline validate calls).

**Existing FormRequests (6):**
- `StoreAppointmentRequest`
- `StoreInvoiceRequest`
- `StorePatientRequest`
- `StorePaymentRequest`
- `StoreTreatmentSessionRequest`
- `SyncBulkPushRequest`

**New FormRequests to create:**
- `UpdateAppointmentRequest`
- `UpdatePatientRequest`
- `UpdateTreatmentSessionRequest`
- `StoreSessionTreatmentRequest`
- `UpdateSessionTreatmentRequest`
- `StoreExpenseRequest`
- `UpdateExpenseRequest`
- `StorePrescriptionRequest`
- `UpdatePrescriptionRequest`
- `StoreTreatmentTypeRequest`
- `UpdateTreatmentTypeRequest`
- `LoginRequest`
- `RegisterRequest`
- `StoreWorkingHourRangeRequest`
- `UpdateWorkingHourRequest`
- `StoreClinicSettingRequest`
- `UpdateClinicSettingRequest`
- `StoreMedicationRequest`
- `UpdateMedicationRequest`
- `StorePatientAssessmentRequest`

**Fix pattern:** In each controller, replace:
```php
$validated = $request->validate([...]);
```
with:
```php
// Inject StoreFooRequest $request in method signature
// Then use $request->validated()
```

All create/update operations should use their respective FormRequest. Reuse existing ones where they fit.

**Verify:**
- `composer test` — all tests pass
- `php artisan test --filter=AppointmentControllerTest` etc.

---

## Task 2.3: Add API Resources for all models

**Files to create under `app/Http/Resources/`:**
- `PatientResource.php`
- `InvoiceResource.php`
- `InvoiceItemResource.php`
- `PaymentResource.php`
- `TreatmentSessionResource.php`
- `SessionTreatmentResource.php`
- `TreatmentTypeResource.php`
- `PrescriptionResource.php`
- `UserResource.php`
- `DoctorResource.php`
- `ClinicSettingResource.php`
- `MedicationResource.php`
- `NotificationResource.php`
- `WorkingHourResource.php`
- `WorkingHourRangeResource.php`
- `PatientAssessmentResource.php`
- `ExpenseResource.php` (exists, audit it)

**Existing: `AppointmentResource`, `ExpenseResource`**

**Why:** Controllers hand-roll `['success' => true, 'data' => $model->toArray()]` which is inconsistent and leaks internal fields. API Resources centralize transformation logic, hide sensitive fields, and allow conditional attribute inclusion.

**Fix pattern:**
```php
// Before:
return response()->json(['success' => true, 'data' => $patient], 200);

// After:
return new PatientResource($patient);
// Or for collections:
return PatientResource::collection($patients);
```

**Verify:**
- All API endpoints return consistent JSON shape
- No field name changes (backward compatible)
- Hidden fields (password, remember_token) still absent

---

## Task 2.4: Replace magic status strings with model constants

**Files affected:** All models with status enums, all controllers referencing them, all Blade views.

**Why:** Status strings like `'pending'`, `'paid'`, `'confirmed'` are scattered across 10+ files. A typo creates a silent bug.

**Fix:**
```php
// Invoice.php
const STATUS_DRAFT = 'draft';
const STATUS_SENT = 'sent';
const STATUS_PAID = 'paid';
const STATUS_PARTIAL = 'partial';
const STATUS_CANCELLED = 'cancelled';

// Appointment.php
const STATUS_PENDING = 'pending';
const STATUS_CONFIRMED = 'confirmed';
const STATUS_CANCELLED = 'cancelled';
const STATUS_COMPLETED = 'completed';
```

Then replace all occurrences: `'pending'` → `Appointment::STATUS_PENDING`, etc.

**Verify:**
- `grep -r "'pending'" app/` → zero false hits
- `composer test` passes

---

## Task 2.5: Audit and deduplicate remaining controller bloat

| Controller | Lines | Action |
|---|---|---|
| `AppointmentController` (Api) | 825 | Split `publicIndex`, `mergeSessions`, `upcomingPending` into `PublicAppointmentController` or service |
| `TreatmentSessionController` (Api) | 509 | Extract `finishSession` to `SessionService` |
| `DoctorDashboardController` | 739 | Keep after phase 2.1 split — or fold the remainder into `Doctor\DashboardController` |
| `AdminDashboardController` | 341 | Acceptable size, but prefix admin views under `resources/views/admin/` not `resources/views/dashboard/` |
| `PublicController` | 400 | Fine — split into `PublicBookingController` + `PublicAssessmentController` if it grows |
| `WorkingHourController` (Api) | 356 | Move range manipulation logic to `WorkingHourService` |
| `SessionTreatmentController` (Api) | 313 | Acceptable |

**Verify:** Each controller stays under 300 lines after split.
