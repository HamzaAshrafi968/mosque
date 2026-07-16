# Fix Web Booking — Patient name/phone not sent to doctor

## Summary

Web booking flow (`POST /booking` → `PublicController::storeAppointment`) creates a `Patient` with explicit `tenant_id` but it's silently dropped because `tenant_id` is not in `Patient::$fillable`. The web route has no `initialize.tenant` middleware so `config('app.current_tenant_id')` is null and `MultiTenantTrait`'s `creating` listener can't fill it. Result: patient saved with `tenant_id = null` — invisible to the doctor's tenant-scoped queries. The doctor never sees the patient's name/phone on appointments or notifications.

Additionally, the current lookup-by-name-then-phone + update-else-create logic is overly complex and drops `fullName`/`phone` in the update branch. Simplify per user spec: lookup by name only; if found → just add appointment; if not found → create patient (name+phone) + appointment (pending).

---

## Root cause (confirmed via live DB inspection)

1. `App\Models\Patient::$fillable` (line 19) — `tenant_id` missing → `Patient::create(['tenant_id' => $doctorTenantId])` silently drops it.
2. Web `POST /booking` has no `initialize.tenant` middleware → `config('app.current_tenant_id')` null → `MultiTenantTrait::creating` can't fill tenant_id from config.
3. Patient saved with `tenant_id = null`. Doctor's `$appointment->patient` / `$notification->patient` relationship loads through tenant scope → `WHERE tenant_id = <doctor's tenant>` → null-tenant patient excluded → doctor sees no name/phone.

**DB proof:** patient `له له` (`b0fdbe9b…`, created 2026-07-06 15:48 via web booking) has `tenant_id = null` while its doctor `hamza` belongs to tenant `5d5f27ec…`.

---

## Tasks

### Task 1 — Add `tenant_id` to `Patient::$fillable`

**File:** `app/Models/Patient.php`

**Changes:**
- Add `'tenant_id'` to the `$fillable` array (~line 19).

**Why:** `storeAppointment`, `storeAssessment`, and `storeSurvey` all pass `'tenant_id' => ...` to `Patient::create()`. Without the column in `$fillable`, the value is silently dropped by Eloquent's mass-assignment protection.

**Verify:**
- `php artisan tinker --execute="Patient::create(['fullName'=>'test','phone'=>'999','tenant_id'=>'5d5f27ec-671a-4a04-9444-0dbe8f4f1166'])--fresh()"` → check DB: `tenant_id` must be `5d5f27ec` (not null).
- Then delete the test patient.

---

### Task 2 — Set tenant context + simplify patient lookup in `storeAppointment`

**File:** `app/Http/Controllers/PublicController.php` — method `storeAppointment` (~lines 148–322)

**Changes:**

**2a.** After resolving `$doctorTenantId` (line ~184), add:
```php
if ($doctorTenantId) {
    config(['app.current_tenant_id' => $doctorTenantId]);
}
```
This scopes all subsequent Eloquent queries/creates to the doctor's tenant via `MultiTenantTrait`.

**2b.** Replace the patient lookup (~lines 189–227) with simpler logic:

```php
// Lookup by fullName only (tenant-scoped now that config is set)
$patient = Patient::where('fullName', $validated['fullName'])->first();

if (! $patient) {
    // Name doesn't exist — create patient with name + phone
    $patient = Patient::create([
        'fullName'     => $validated['fullName'],
        'nickeName'    => $validated['nickeName'] ?? '',
        'phone'        => $validated['phone'],
        'date_of_birth' => $validated['date_of_birth'] ?? null,
        'gender'       => $validated['gender'] ?? null,
        'job'          => $validated['job'] ?? '',
        'state'        => $validated['state'] ?? '',
        'is_required'  => $validated['is_required'] ?? 0,
        'address'      => $validated['address'] ?? '',
        'notes'        => $validated['notes'] ?? '',
        'doctor_id'    => $validated['doctor_id'],
        'tenant_id'    => $doctorTenantId,
    ]);
}
// If patient exists (by name) → reuse, no update
```

Removes:
- The phone-based second lookup (`Patient::where('phone', ...)`)
- The `else` branch that updated the existing patient (including the bug where `fullName`/`phone` were omitted from the update array)
- The `withoutGlobalScope('tenant')` calls (no longer needed; scope is correct with config set)

**2c.** Appointment creation (~line 242) stays the same but the `created` listener now auto-fills `tenant_id` from config (belt-and-suspenders with the manual `$appointment->tenant_id = ...; save()`). The `Notification::create` also benefits from the config.

**Why:**
- User's spec: "if name existe just add appointment; if no add patient name and phone and add appointment state pending"
- Tenant context set early ensures all multi-tenant models (Patient, Appointment, Notification) get the correct `tenant_id` automatically, matching how `InitializeTenant` middleware works for authenticated routes
- Lookup is now tenant-scoped (only matches patients in the doctor's clinic — no cross-tenant data leak)
- Simplified logic: no dual-lookup, no update-else branch

**Verify:**
1. Visit `http://127.0.0.1:8000/booking`, fill form with new patient name "Test Name" + phone "123456", select doctor, date, time → submit.
2. Check DB: new `patients` row has `tenant_id = <doctor's tenant>` (not null), `fullName = 'Test Name'`, `phone = '123456'`.
3. Check DB: new `appointments` row exists with `status = 'pending'`, `tenant_id = <doctor's tenant>`.
4. Log in as the same doctor → `/doctor/appointments` → appointment visible with patient name displayed.
5. Submit again with same "Test Name" → no new patient row, new appointment row added (patient reused).

---

### Task 3 — (included in Task 2, listed separately for clarity)

**Context:** The deleted `else` branch was silently dropping `fullName`/`phone` on existing-patient updates. With the simplified logic, this bug is eliminated by removing the update branch entirely.

---

## Out of scope (noted, not fixed)

- `device_token` duplicate guard uses `'completed'` status — should be `'finished'` (typo). `completed` never matches any appointment so the guard is effectively dead code.
- `NewAppointmentBooked` broadcast event pushes to `jobs` table but the queue worker (`queue:listen`) may not be running — the broadcast job is stuck. This doesn't affect the name/phone issue.
- The appointment at `f48b239f…` was created via the booking flow (log confirmed at 15:48:24) but later deleted (notification's `appointment_id` nullified by `ON DELETE SET NULL` cascade). Likely a manual user action during testing, not a code bug.
