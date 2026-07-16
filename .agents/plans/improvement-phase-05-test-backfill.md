# Phase 05 — Test Backfill (Critical)

## Overview

Current coverage: ~8% of controllers, 0% of services tested. This phase targets meaningful coverage on all critical paths. Target: 70%+ controller coverage, 100% service coverage.

---

## Task 5.1: Add HTTP Feature tests for all API controllers

**Files to create:**
- `tests/Feature/Api/AuthControllerTest.php`
- `tests/Feature/Api/PatientControllerTest.php`
- `tests/Feature/Api/AppointmentControllerTest.php`
- `tests/Feature/Api/TreatmentSessionControllerTest.php`
- `tests/Feature/Api/SessionTreatmentControllerTest.php`
- `tests/Feature/Api/TreatmentTypeControllerTest.php`
- `tests/Feature/Api/InvoiceControllerTest.php`
- `tests/Feature/Api/PaymentControllerTest.php`
- `tests/Feature/Api/ExpenseControllerTest.php`
- `tests/Feature/Api/PrescriptionControllerTest.php`
- `tests/Feature/Api/MedicationControllerTest.php`
- `tests/Feature/Api/ClinicSettingControllerTest.php`
- `tests/Feature/Api/WorkingHourControllerTest.php`
- `tests/Feature/Api/StatisticsControllerTest.php`
- `tests/Feature/Api/NotificationControllerTest.php`
- `tests/Feature/Api/UserControllerTest.php`
- `tests/Feature/Api/PatientAssessmentControllerTest.php`
- `tests/Feature/Api/SyncControllerTest.php`
- `tests/Feature/Api/TeethControllerTest.php`

**Pattern for each test file:**

Each test should cover (where applicable):

1. **Unauthenticated** → 401 (for protected endpoints)
2. **Authenticated, list** → returns scoped results
3. **Authenticated, create** → valid input creates record, invalid returns 422
4. **Authenticated, show** → own record returns 200, other tenant's record returns 404
5. **Authenticated, update** → own record updates, other's returns 403/404
6. **Authenticated, destroy** → own record deletes, other's returns 403/404
7. **Authorization** → doctor A cannot access doctor B's records (within same tenant)
8. **Tenant scoping** → user from tenant A does not see tenant B's data

**Setup approach:**
- Use `RefreshDatabase` trait (already in `TestCase`)
- Create two tenants with separate patients/doctors/data
- Authenticate as a user from tenant A
- Assert tenant B's data is not accessible

**Example structure:**
```php
public function test_cross_tenant_show_returns_404(): void
{
    // Create two tenants with a patient each
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'doctor']);
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);

    Sanctum::actingAs($userA);
    $response = $this->getJson("/api/patients/{$patientB->id}");

    $response->assertStatus(404); // NOT 200 (currently codified as 200 in TenantScopedApiTest)
}
```

---

## Task 5.2: Add unit tests for all services

**Files to create/update:**
- `tests/Unit/Services/RegisterServiceTest.php`
- `tests/Unit/Services/BillingServiceTest.php`
- `tests/Unit/Services/TenantServiceTest.php`
- `tests/Unit/Services/WhatsAppServiceTest.php`
- `tests/Unit/Services/PushNotificationServiceTest.php`

**What to test per service:**

| Service | Key tests |
|---------|-----------|
| `RegisterService` | Happy path (creates User + Tenant + Settings + WorkingHours + Seeders); duplicate email/phone; missing fields; tenant_id assignment |
| `BillingService` | Invoice generation from session; idempotency (existing invoice returned); invoice number sequence; items generation; duplicate prevention |
| `TenantService` | `getCurrentTenantId()` from Auth user; from config; from header; abort if missing; `createTenantForUser` |
| `WhatsAppService` | Message creation; WA link generation; country code handling; enum compliance; tenant scoping |
| `PushNotificationService` | FCM token targetting; audience filtering (all/doctors/reception); error handling on failure |

**Pattern:**
- Use `Mockery` or `Http::fake()` for external calls (FCM, WhatsApp)
- For DB-using services, use `RefreshDatabase` trait
- Assert correct DB state after service execution

---

## Task 5.3: Fix TenantScopedApiTest cross-tenant assertion

**Files affected:**
- `tests/Feature/Api/TenantScopedApiTest.php`

**Changes:**
- Line ~89: Change `$response->assertStatus(200)` to `$response->assertStatus(404)` for cross-tenant patient show
- Add similar assertions for other resources (appointments, invoices, etc.)

**Why:** The current test *codifies* the data leak as expected behavior. After Phase 01 fixes, cross-tenant access should return 404 (record not found) or 403 (unauthorized).

---

## Task 5.4: Add Blade/web route tests

**Files to create:**
- `tests/Feature/PublicBookingTest.php`
- `tests/Feature/DoctorDashboardTest.php`
- `tests/Feature/AdminDashboardTest.php`

**What to test:**
| Test file | Key scenarios |
|-----------|---------------|
| `PublicBookingTest` | Home page loads; doctor listing; assessment form submit; booking flow; patient registration; survey submission |
| `DoctorDashboardTest` | Authentication required; patient list; appointment CRUD; session management; settings update; financial reports |
| `AdminDashboardTest` | Authentication required; role:admin gate; user management; global reports; invoice/payment views |

**Challenges:**
- Blade tests require session-based auth (`$this->actingAs($user)` and `$this->get('/dashboard')`)
- CSRF is exempted for public routes, but may need `$this->withoutMiddleware('csrf')` for testing or `$this->call()` with proper CSRF token
- Laravel's `Laravel\BrowserKitTesting` or `InteractsWithViews` can help

---

## Task 5.5: Add middleware unit tests

**Files to create:**
- `tests/Unit/Middleware/InitializeTenantTest.php`
- `tests/Unit/Middleware/RoleMiddlewareTest.php`

**What to test:**

`InitializeTenant`:
- Sets `config('app.current_tenant_id')` from `auth()->user()->tenant_id` for authenticated requests
- Skips initialization for `register` and `login` routes
- Returns 403 for authenticated user without `tenant_id`
- Returns 401 for unauthenticated requests to protected endpoints
- Sets config to null for public appointment endpoints

`RoleMiddleware`:
- Allows `doctor` role through doctor routes
- Allows `admin` role through admin routes
- Rejects `admin` user on doctor routes
- Rejects `doctor` user on admin routes
- Returns proper redirect/abort for unauthenticated users

---

## Task 5.6: Add concurrency test for invoice numbering

**Files to create:**
- `tests/Feature/InvoiceNumberConcurrencyTest.php`

**What to test:**
- Simulate concurrent invoice generation (using `Http::pool()` or `Process::run()` parallelism or just sequential assertions)
- Assert that two invoices created in rapid succession for the same tenant have unique, sequential numbers
- Assert that different tenants have independent sequences

**Why:** The race condition in invoice-number generation (Phase 02, Task 2.4) requires a concurrency test, especially if `lockForUpdate()` is used.

---

## Task 5.7: Set up code coverage reporting

**Files affected:**
- `phpunit.xml` — add coverage configuration

**Changes:**
```xml
<coverage>
    <report>
        <html outputDirectory="coverage"/>
        <text outputFile="coverage.txt"/>
    </report>
</coverage>
```

**Add script to composer.json:**
```json
"test-coverage": "php artisan test --coverage-html=coverage"
```

**Add `coverage/` to `.gitignore`**

**Why:** Without coverage reports, it's hard to track progress on the test backfill effort or identify uncovered code paths.
