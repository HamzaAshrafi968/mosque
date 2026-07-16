# Phase 03 — Harden (🟡 4-6h)

---

## Task 3.1: Add CI workflow (GitHub Actions)

**Files to create:**
- `.github/workflows/tests.yml`
- `.github/workflows/pint.yml`

**Why:** No CI at all. Every PR should auto-run tests + lint.

**tests.yml:**
```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: [8.2, 8.3, 8.4]
    steps:
      - uses: actions/checkout@v4
      - uses: php-actions/composer@v6
        with:
          php_version: ${{ matrix.php }}
      - run: composer test
```

**pint.yml:**
```yaml
name: Lint
on: [push, pull_request]
jobs:
  pint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer install
      - run: vendor/bin/pint --test
```

**Verify:** Push a commit — both workflows trigger and pass.

---

## Task 3.2: Add Larastan (PHPStan for Laravel)

**Install:**
```bash
composer require --dev larastan/larastan
```

**Files to create:**
- `phpstan.neon`

```neon
parameters:
    level: 5
    paths:
        - app/
        - tests/
    universalObjectCratesClasses:
        - Illuminate\Http\Request
```

**Add composer script:**
```json
"analyse": "php artisan config:clear && phpstan analyse --memory-limit=256M"
```

**Why:** Static analysis catches type mismatches, undefined methods, missing imports — before runtime. Level 5 is a good starting target.

**Verify:**
- `composer analyse` runs with zero errors (or documented baseline)

---

## Task 3.3: Add barryvdh/laravel-ide-helper

**Install:**
```bash
composer require --dev barryvdh/laravel-ide-helper
```

**Generate helpers:**
```bash
php artisan ide-helper:generate    # Facade autocompletion
php artisan ide-helper:models -W   # Model mixin PHPDocs
php artisan ide-helper:meta        # PhpStorm Meta
```

**Files to .gitignore:**
```
_ide_helper.php
_ide_helper_models.php
.phpstorm.meta.php
```

**Why:** IDEs can't infer Eloquent dynamic properties, scopes, relations, or facade methods. This generates PHPDocs so autocomplete works.

**Verify:** `Patient $p->full_name` autocompletes after generation.

---

## Task 3.4: Audit RoleMiddleware for deleted roles

**Files affected:**
- `app/Http/Middleware/RoleMiddleware.php`

**Why:** Lab, Supplier, and Center modules were purged. The `role:lab`, `role:supplier`, `role:center` cases in `RoleMiddleware` are dead code.

**Fix:** Remove cases for `lab`, `supplier`, `center` roles. Keep only `doctor` and `admin`.

**Verify:**
- `php artisan test --filter=RoleMiddlewareTest` passes (if exists)
- `grep -r "lab\|supplier\|center" app/Http/Middleware/RoleMiddleware.php` returns zero hits

---

## Task 3.5: Add rate limiting to auth endpoints

**Files affected:**
- `bootstrap/app.php`

**Why:** `POST /api/login` and `POST /api/register` are already throttled in `routes/api.php:26-27` via `throttle:login`. Verify the rate limiting configuration exists in the app bootstrap.

**Fix:** Ensure `bootstrap/app.php` has:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->throttleUsing('login');
})
```

Or define in `AppServiceProvider`:
```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('login', fn ($request) =>
    Limit::perMinute(5)->by($request->ip())
);

RateLimiter::for('public-api', fn ($request) =>
    Limit::perMinute(30)->by($request->ip())
);
```

**Verify:** 6 rapid login attempts return `429 Too Many Requests` on the 6th.

---

## Task 3.6: Audit existing Policies — ensure all API controllers use authorize()

**Files affected:** 12 policy files under `app/Policies/`, all API controllers.

**Existing policies:** `AppointmentPolicy`, `ExpensePolicy`, `InvoicePolicy`, `NotificationPolicy`, `PatientAssessmentPolicy`, `PatientPolicy`, `PaymentPolicy`, `PrescriptionPolicy`, `TreatmentSessionPolicy`, `TreatmentTypePolicy`, `UserPolicy`, `WorkingHourPolicy`

**Why:** Policies were created but may not be wired into controllers. Without `$this->authorize('view', $model)` calls, the policies have zero effect.

**Fix:** Add `$this->authorizeResource()` in each controller constructor, or explicit `$this->authorize()` calls in each method.

Example:
```php
// In PatienteController::__construct
public function __construct()
{
    $this->authorizeResource(Patient::class, 'patient');
}
```

**Verify:**
- Doctor A tries `PUT /api/patients/{doctorB-patient-id}` → 403
- Admin can access any patient in their tenant

---

## Task 3.7: Fix WhatsAppService hardcoded country code

**Files affected:**
- `app/Services/WhatsAppService.php`
- `.env.example`

**Why:** Country code `+963` (Syria) is hardcoded. Should be `+966` (Saudi Arabia) or configurable.

**Fix:**
```php
// WhatsAppService.php
$phone = config('services.whatsapp.country_code', '+966') . ltrim($phoneNumber, '0');
```

```dotenv
# .env.example
WHATSAPP_COUNTRY_CODE=+966
```

```php
// config/services.php
'whatsapp' => [
    'country_code' => env('WHATSAPP_COUNTRY_CODE', '+966'),
],
```

**Verify:** WhatsApp message phone formatting uses configured code.

---

## Task 3.8: Handle missing mergeSessions route handler

**Files affected:**
- `routes/api.php:69` — `Route::post('/appointments/merge-sessions', [AppointmentController::class, 'mergeSessions']);`
- `app/Http/Controllers/Api/AppointmentController.php`

**Why:** Route references `mergeSessions` method which may not exist → 500 error.

**Fix:** Either:
1. Add `mergeSessions(Request $request)` method to `AppointmentController`, or
2. Remove the route from `api.php`

If implementing, the method should: accept two `appointment_id`s, validate they share tenant/patient/doctor, merge treatment sessions, delete the second appointment.

**Verify:** Calling the endpoint returns a valid response, not 500.

---

## Task 3.9: Delete ExampleTest.php stubs

**Files affected (DELETE):**
- `tests/Feature/ExampleTest.php`
- `tests/Unit/ExampleTest.php`

**Why:** Default Laravel scaffold tests that assert `assertTrue(true)`. Zero value.

**Fix:** Delete both files.

**Verify:** `composer test` still passes.
