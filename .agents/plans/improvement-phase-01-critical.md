# Phase 01 — Critical Fixes (🔴 2-3h)

---

## Task 1.1: Remove MultiTenantTrait from User model

**Files affected:**
- `app/Models/User.php`

**Why:** `User` uses `MultiTenantTrait`, which adds a `where('tenant_id', config('app.current_tenant_id'))` global scope. During Sanctum token authentication, the user lookup happens *before* `InitializeTenant` middleware runs — so `config('app.current_tenant_id')` is `null`, and the query becomes `WHERE tenant_id IS NULL`. This can silently fail auth.

**Fix:**
```php
// Line 15 — remove MultiTenantTrait
use HasFactory, Notifiable, HasApiTokens; // was: ..., MultiTenantTrait

// Remove the import too (line 10)
// use App\Models\Traits\MultiTenantTrait;  <-- delete this
```

User tenant isolation is already implicit: every user belongs to exactly one tenant via `tenant_id`, and `InitializeTenant` gates all authenticated routes. No additional global scope is needed on the User model.

**Verify:**
- `composer test` — all existing tests pass
- POST `/api/login` — works with correct credentials
- POST `/api/login` with wrong password — returns 401, not 500

---

## Task 1.2: Remove duplicate GET / route in web.php

**Files affected:**
- `routes/web.php`

**Why:** Lines 14-15 define two `GET /` routes on the same URI:
```php
Route::get('/', [PublicController::class, 'booking'])->name('public.home')->middleware('throttle:public-api');
Route::get('/', [PublicController::class, 'booking'])->name('public.index')->middleware('throttle:public-api');
```
Only the second registers; the first is shadowed. `route('public.home')` will fail.

**Fix:** Delete line 14. Keep only:
```php
Route::get('/', [PublicController::class, 'booking'])->name('public.index')->middleware('throttle:public-api');
```

**Verify:**
- `php artisan route:list` shows only one `/` route
- Calling `route('public.index')` works

---

## Task 1.3: Remove unreachable public-booking branch in InitializeTenant

**Files affected:**
- `app/Http/Middleware/InitializeTenant.php`

**Why:** Lines 33-38 check for `api/appointments/public/*` inside the middleware, but those routes live outside the `auth:sanctum` group in `api.php` — the middleware never fires for them. Dead code.

**Fix:** Delete lines 33-38:
```php
// Delete this block:
            // Allow public booking endpoints (no auth required)
            if ($request->is('api/appointments/public/*')) {
                config(['app.current_tenant_id' => null]);
                return $next($request);
            }
```

**Verify:**
- `GET /api/appointments/public` still works (routes are public, no middleware applied)
- No behavioral change

---

## Task 1.4: Delete six stale "fix doctors user fk" migrations

**Files affected (DELETE):**
- `database/migrations/2026_02_23_070000_fix_doctors_user_fk.php`
- `database/migrations/2026_02_28_000001_fix_doctors_foreign_key.php`
- `database/migrations/2026_03_03_000001_fix_doctors_user_id_foreign_key.php`
- `database/migrations/2026_03_16_032324_fix_doctors_user_id_fk.php`
- `database/migrations/2026_03_20_000000_fix_doctors_user_fk_constraint.php`
- `database/migrations/2026_11_10_000000_fix_doctors_user_foreign_key.php`

**Why:** `2026_07_06_000002_squash_doctors_user_fk.php` already consolidates all six into one clean migration. The predecessors are redundant and slow down fresh installs (`migrate:fresh` runs all 7 sequentially).

**Files to keep:**
- `database/migrations/2026_02_23_062424_create_doctors_table.php` (the original)
- `database/migrations/2026_07_06_000002_squash_doctors_user_fk.php` (the squash)

**Verify:**
- Delete the six files
- Run `php artisan migrate:fresh` — no errors
- `doctors` table has the correct foreign key on `user_id`

---

## Task 1.5: Normalize non-snake_case columns

**Files affected:**
- New migration: `2026_07_07_000001_rename_legacy_columns.php`
- `app/Models/User.php`
- `app/Models/Patient.php`

**Why:** Three columns use camelCase and stick out:
| Table | Current | Should be |
|-------|---------|------------|
| `patients` | `fullName` | `full_name` |
| `patients` | `nickeName` | `nickname` (also fixes typo) |
| `users` | `nameClinic` | `name_clinic` |

These break Laravel conventions. No auto-accessor generation, confusing in Blade/JS, and the `nickeName` spelling is a typo.

**Fix — migration:**
```php
// database/migrations/2026_07_07_000001_rename_legacy_columns.php
Schema::table('patients', function (Blueprint $table) {
    $table->renameColumn('fullName', 'full_name');
    $table->renameColumn('nickeName', 'nickname');
});
Schema::table('users', function (Blueprint $table) {
    $table->renameColumn('nameClinic', 'name_clinic');
});
```

**Fix — models:**
- `User::$fillable`: `'nameClinic'` → `'name_clinic'`
- `Patient::$fillable`: `'fullName'` → `'full_name'`, `'nickeName'` → `'nickname'`
- `Patient::$casts`: update keys

**Fix — then grep entire codebase for `fullName`, `nickeName`, `nickeName`, `nameClinic` in all `.php`, `.blade.php`, `.js`, `.vue` files and replace references.

**Verify:**
- `php artisan migrate:fresh` — tables have correct column names
- `grep -r "fullName\|nickeName\|nameClinic" app/ resources/` returns zero matches
- `composer test` passes

---

## Task 1.6: Audit unscoped model consistency

**Files affected:**
- `app/Models/Tenant.php`
- `app/Models/Doctor.php`
- `app\Models\Assesment.php` (stub model, check if it exists)

**Why:** `AGENTS.md` lists `Tenant`, `Doctor`, and `Assesment` as unscoped. Verify this is intentional and documented in each model with a comment.

**Fix:** Add to each unscoped model header:
```php
/**
 * NOTE: This model intentionally does not use MultiTenantTrait.
 * Tenant — system-wide (all tenants must be visible).
 * Doctor — lookup table (doctors belong to a tenant via User, not directly).
 */
```

**Verify:** `TenantScopedModelTest.php` and `UnscopedModelTest.php` pass.
