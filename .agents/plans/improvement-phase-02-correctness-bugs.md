# Phase 02 — Correctness Bugs (High)

## Task 2.1: Add UuidTrait to WhatsappMessage and Attachment

**Files affected:**
- `app/Models/WhatsappMessage.php`
- `app/Models/Attachment.php`

**Changes:**
1. Add `use UuidTrait` to both models
2. Add `public $incrementing = false; protected $keyType = 'string';` to both
3. `WhatsappMessage`: fix `WhatsAppService.php:46-51` — it calls `create()` without an `id`; UuidTrait now auto-sets it
4. `Attachment`: no current code creates attachments, but the model is now structurally sound for future use

**Why:** Migrations use `uuid('id')->primary()` but models lacked both the trait and the PK config. Inserts fail with missing PK.

**Verify:**
- `WhatsAppService::sendWhatsAppMessage()` creates a record with a valid UUID (test via `WhatsappMessage::first()->id`)
- `php artisan test --filter=UuidModelTest` — add assertions for `WhatsappMessage` and `Attachment`

---

## Task 2.2: Fix WhatsAppMessage enum violation

**Files affected:**
- `app/Services/WhatsAppService.php` — change line sending `'appointment_reminder'` to a valid value
- `database/migrations/2026_02_17_000016_create_whatsapp_messages_table.php` — alter enum to add `'appointment_reminder'`

**Changes:**
Option A (preferred): Modify migration to add `'appointment_reminder'` to the enum.
Option B: Create a new migration altering the column to include the new value.

**Why:** The enum allows `['invoice','prescription','attachment']` but the service inserts `'appointment_reminder'`, which fails on strict DBMS.

**Verify:**
- Calling the WhatsApp service from appointment reminder flow does not throw a DB error

---

## Task 2.3: Fix Invoice::payments() relation (per-invoice accounting broken)

**Files affected:**
- `app/Models/Invoice.php` — change `payments()` from basing on `patient_id` to proper `invoice_id`
- `app/Models/Payment.php` — add `invoice()` belongsTo
- Create migration: `add_invoice_id_to_payments_table`
- `app/Http/Controllers/Api/InvoiceController.php` — update to pass `invoice_id` on payment creation
- `app/Http/Controllers/Api/PaymentController.php` — update store to accept/set `invoice_id`
- `app/Http/Controllers/DashboardController.php` — update payment creation
- Tests fixing

**Changes:**
1. Create migration: `$table->foreignUuid('invoice_id')->nullable()->constrained()->nullOnDelete();`
2. `Invoice::payments()` → `hasMany(Payment::class)`
3. `Payment::invoice()` → `belongsTo(Invoice::class)`
4. Update `Invoice::getTotalPaidAttribute()` → `$this->payments()->sum('amount')` (now correct per-invoice)

**Why:** Currently `Invoice::payments()` joins on `patient_id` — returns ALL payments for that patient across all invoices. Per-invoice totals (`remaining_balance`) are wrong.

**Verify:**
- Existing `InvoiceBalanceTest.php` — update to use real invoice-payment association
- Create invoice → create payment linked to that invoice → remaining balance is correct
- Create payment for a different invoice of the same patient → first invoice balance unaffected

---

## Task 2.4: Fix invoice-number race condition

**Files affected:**
- `app/Services/BillingService.php` — generate invoice number here, remove from elsewhere
- `app/Http/Controllers/Api/InvoiceController.php` — remove duplicate number generation
- `app/Http/Controllers/DashboardController.php` — remove duplicate number generation

**Changes:**
1. Move invoice-number generation into `BillingService` as a single, locked method
2. Use `DB::transaction` with `sharedLock` or `SELECT ... FOR UPDATE`:
   ```php
   DB::transaction(function () use ($tenantId) {
       $last = Invoice::where('tenant_id', $tenantId)
           ->orderBy('invoice_number', 'desc')
           ->lockForUpdate()
           ->first();
       $next = $last ? intval(substr($last->invoice_number, 4)) + 1 : 1;
       return 'INV-' . str_pad($next, 6, '0', STR_PAD_LEFT);
   });
   ```
3. Remove equivalent logic from `InvoiceController:87-92` and `DashboardController:1652-1657`

**Why:** Three copies of the same logic, all with the same race condition (read without lock, then write). Two concurrent requests in the same tenant get the same invoice number.

**Verify:**
- Concurrent invoice creation in the same tenant produces sequential unique numbers
- `php artisan test --filter=InvoiceBalanceTest` passes

---

## Task 2.5: Fix auth()->id property-access bug

**Files affected:**
- `app/Http/Controllers/DashboardController.php` — line ~403

**Changes:**
- Change `'doctor_id' => auth()->id` to `'doctor_id' => auth()->id()`

**Why:** `auth()->id` returns the AuthManager instance, not the authenticated user's ID. The `__toString` on AuthManager may return empty string. Medical surveys get wrong `doctor_id`.

**Verify:**
- Creating a medical survey via the dashboard correctly stores the logged-in user's ID

---

## Task 2.6: Fix missing mergeSessions route handler

**Files affected:**
- `app/Http/Controllers/Api/AppointmentController.php`

**Changes:**
- Add `mergeSessions()` method or remove the route in `routes/api.php:69`
- If implementing: accept two `appointment_id`s, validate they belong to same patient/doctor/tenant, combine sessions, delete the second

**Why:** Route `api.php:69` maps to a method that doesn't exist → 500 error on access.

**Verify:**
- Calling the route returns either a functional response or 404 (if route is removed)

---

## Task 2.7: Remove dead Lab references in DashboardController

**Files affected:**
- `app/Http/Controllers/DashboardController.php` — `showTreatmentSession()` loads `labOrders()` / `lab`

**Changes:**
- Remove `labOrders()`/`lab` relationship loads (Lab module was purged per `CHANGES.md`)
- Remove any Lab-related Blade template references if present

**Why:** Calling a non-existent relationship throws `BadMethodCallException`. This will crash the treatment session detail page.

**Verify:**
- Loading a treatment session detail via the dashboard does not error

---

## Task 2.8: Fix MySQL-only raw SQL in ProcessOverdueSessions

**Files affected:**
- `app/Console/Commands/ProcessOverdueSessions.php`

**Changes:**
- Replace `whereRaw('STR_TO_DATE(CONCAT(session_date, " ", TIME(session_time)), "%Y-%m-%d %H:%i:%s") < DATE_SUB(NOW(), INTERVAL 15 MINUTE)')`
- With Carbon-based comparison:
  ```php
  use Carbon\Carbon;
  $threshold = Carbon::now()->subMinutes(15);
  TreatmentSession::where('status', TreatmentSession::STATUS_SCHEDULED)
      ->where(function ($q) use ($threshold) {
          $q->where('session_date', '<', $threshold->toDateString())
            ->orWhere(function ($q) use ($threshold) {
                $q->where('session_date', '=', $threshold->toDateString())
                  ->where('session_time', '<=', $threshold->toTimeString());
            });
      });
  ```

**Why:** `STR_TO_DATE`/`TIME`/`DATE_SUB` are MySQL-specific. Local dev uses SQLite, tests use SQLite `:memory:`. The raw SQL breaks on the documented default DB.

**Verify:**
- `php artisan test` passes with SQLite (currently may be skipping this command)
- Overdue session detection works correctly at midnight boundaries

---

## Task 2.9: Remove id and tenant_id from $fillable on all models

**Files affected (all models where applicable):**
- `app/Models/Tenant.php`, `User.php`, `Doctor.php`, `Patient.php`, `Appointment.php`, `TreatmentSession.php`, `SessionTreatment.php`, `Invoice.php`, `Payment.php`, `Expense.php`, `PatientAssessment.php`, `WorkingHour.php`, `WorkingHourRange.php`, `Prescription.php`, `Notification.php`, `SyncMetadata.php`

**Changes:**
- Remove `'id'` from `$fillable` on every model that uses `UuidTrait` (trait auto-sets it)
- Remove `'tenant_id'` from `$fillable` on every model that uses `MultiTenantTrait` (trait auto-sets it on `creating`)
- For models where `id` is manually set in code (e.g., `RegisterService.php:25`, `BillingService.php:66`), set it directly: `$model->id = Str::uuid()` instead of mass-assigning

**Why:** Mass-assignable primary keys let clients inject arbitrary UUIDs. Mass-assignable `tenant_id` lets clients move records between tenants. Both are auto-managed by traits.

**Verify:**
- All existing `create()` calls still work (change any that relied on mass-assigning `id` or `tenant_id` to explicit assignment)
- `php artisan test` passes

---

## Task 2.10: Fix web route tenant isolation

**Files affected:**
- `bootstrap/app.php` — add `InitializeTenant` to web route middleware group
- Or `routes/web.php` — apply `initialize.tenant` middleware to authenticated web route groups

**Changes:**
Add `App\Http\Middleware\InitializeTenant::class` to the `web` middleware group or to each authenticated route group in `routes/web.php`:
```php
Route::middleware(['auth', 'initialize.tenant', 'role:doctor'])->prefix('/doctor')->group(function () { ... });
```

**Why:** `InitializeTenant` is only applied to API routes. Blade sessions never set `config('app.current_tenant_id')`, so `MultiTenantTrait` queries on admin/doctor dashboards may be unscoped (returning all tenants' data when config is null).

**Verify:**
- Doctor dashboard queries are scoped to the logged-in user's tenant
- Admin dashboard lists only the admin's tenant data (not all tenants)
