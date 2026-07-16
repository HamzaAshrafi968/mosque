# Phase 04 — Polish (🟢 2-3h)

---

## Task 4.1: Update .env.example

**Files affected:**
- `.env.example`

**Add missing entries:**
```dotenv
FCM_SERVER_KEY=
WHATSAPP_COUNTRY_CODE=+966
APP_TIMEZONE=Asia/Riyadh
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

**Why:** New developers won't know these config values exist or what defaults to use.

**Verify:** Fresh `composer setup` copies a `.env` with all required keys.

---

## Task 4.2: Fix missing $keyType / $incrementing on models

**Files affected:**
- `app/Models/WorkingHour.php`
- `app/Models/WorkingHourRange.php` (verify it has them)
- `app/Models/PatientAssessment.php` (verify it has them)

**Why:** AGENTS.md mandates all UUID models must have:
```php
public $incrementing = false;
protected $keyType = 'string';
```
Missing these on some models — Eloquent defaults to auto-increment integer PK, which breaks UUID inserts.

**Fix:** Add the two properties to any UUID model missing them.

**Verify:**
- Create a `WorkingHour` record → receives a UUID, not an auto-increment integer
- `grep -r "UuidTrait" app/Models/ | while read model; do grep -l '$incrementing' $model; done` — every model with UuidTrait also has $incrementing

---

## Task 4.3: Add API versioning prefix

**Files affected:**
- `routes/api.php`

**Why:** No version prefix — breaking changes affect all mobile clients simultaneously.

**Fix:** Wrap all protected routes in `Route::prefix('v1')->group(...)`. Keep public auth routes (`login`, `register`) unversioned or also under `/v1`.

```php
Route::prefix('v1')->group(function () {
    // public
    Route::post('/login', ...);
    Route::post('/register', ...);

    // protected
    Route::middleware(['auth:sanctum', InitializeTenant::class])->group(function () {
        // ... all current routes
    });
});
```

**Verify:** All mobile API calls work (update base URL in mobile app). Old URLs return 404 gracefully.

---

## Task 4.4: Pin Pint and create pint.json

**Files to create:**
- `pint.json`

```json
{
    "preset": "laravel",
    "rules": {
        "braces_position": {
            "anonymous_classes_opening_brace": "same_line",
            "anonymous_functions_opening_brace": "same_line",
            "classes_opening_brace": "next_line",
            "control_structures_opening_brace": "same_line",
            "functions_opening_brace": "next_line"
        }
    }
}
```

**Why:** Existing code uses a mix of brace styles. A `pint.json` enforces consistent formatting. The rules above match PSR-12 with `next_line` for functions/classes (common in this codebase).

**Verify:**
- `vendor/bin/pint` runs without errors
- `vendor/bin/pint --test` passes (no unformatted files)

---

## Task 4.5: Remove unused NewAppointmentBooked event

**Files affected:**
- `app/Events/NewAppointmentBooked.php` (check if exists)
- Or `app/Events/` directory

**Why:** Event may have no listener registered — dead code.

**Fix:** If the event exists and has zero listeners, delete the file. If it's wired to a notification listener, keep it.

**Verify:**
- `grep -r "NewAppointmentBooked" app/ vendor/` — only the event class itself shows up
- If no listener references — safe to delete

---

## Task 4.6: Audit Assesment stub model

**Files affected:**
- `app/Models/Assesment.php` (check if exists)

**Why:** AGENTS.md lists it as an "unscoped stub" (only `extends Model`). If it's truly unused, delete it. If it's needed for future use, add proper config.

**Fix:**
- If unused: DELETE
- If needed: rename to `Assessment` (fix typo), add `UuidTrait`, doc comment

**Verify:** `grep -r "Assesment" app/ routes/` — any reference hits resolved or removed.

---

## Task 4.7: Review routes/channels.php

**Files affected:**
- `routes/channels.php`

**Why:** Broadcasting channels file — if the app doesn't use WebSockets, it's dead code.

**Fix:** If empty or unused, keep but add a `// Unused — app does not use broadcasting` comment.

---

## Task 4.8: Add missing factories

**Check existing:** Most models already have factories. Audit:

```bash
ls database/factories/
```

Expected: `AppointmentFactory`, `DoctorFactory`, `InvoiceFactory`, `PatientAssessmentFactory`, `PatientFactory`, `PaymentFactory`, `SessionTreatmentFactory`, `TenantFactory`, `TreatmentSessionFactory`, `TreatmentTypeFactory`, `UserFactory`, `WorkingHourFactory`, `WorkingHourRangeFactory`

**Missing:**
- `ExpenseFactory`
- `PrescriptionFactory`
- `MedicationFactory`
- `ClinicSettingFactory`
- `NotificationFactory`
- `InvoiceItemFactory`

**Fix:** Create factories for any missing model using `php artisan make:factory`. At minimum, each factory should produce a valid record with all required fields.

**Verify:**
- `Patient::factory()->create()` works
- `App\Zombie::factory()->create()` works for all models
