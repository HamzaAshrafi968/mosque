# Phase 04 — Documentation & Cleanup (Low)

## Task 4.1: Fix AGENTS.md inaccuracies

**Files affected:**
- `AGENTS.md`

**Changes:**
1. Migration count: "52 files" → "62 files"
2. Models without `MultiTenantTrait`:
   - Remove `User` from unscoped list (it **uses** MTT)
   - Remove `Doctor` from unscoped list (it **uses** MTT)
   - Keep `Tenant` (correctly unscoped)
   - Keep `Assesment` (stub, no traits)
   - Add: `InvoiceItem`, `Prescription`, `WhatsappMessage`, `Attachment`, `Notification`, `SyncMetadata` (unscoped, known bugs — Phase 01 fixes these)
3. TestCase description: "bare extension" → "uses RefreshDatabase + resets tenant config in tearDown"
4. Models list: add `Notification`, `SyncMetadata`, `Assesment`

**Why:** AGENTS.md is the primary reference for agent tooling. Inaccuracies lead to wrong assumptions during development.

**Verify:**
- Review file after changes for internal consistency

---

## Task 4.2: Replace default Laravel README with project documentation

**Files affected:**
- `README.md`

**Changes:**
Write a proper project README covering:
- Project name and purpose (multi-tenant dental clinic management)
- Tech stack (Laravel 12, PHP 8.2+, Sanctum, Tailwind 4, Vite 7)
- Quickstart (`composer setup`)
- Dev workflow (`composer dev`)
- Testing (`composer test`)
- Architecture summary (multi-tenant, UUIDs, route structure)
- Key commands reference
- Link to AGENTS.md for full architecture docs

**Why:** The current README is the default Laravel skeleton — zero project-specific information.

---

## Task 4.3: Remove dead code and stubs

**Files affected:**
- `app/Http/Controllers/AssesmentController.php` — delete (empty stub)
- `app/Http/Controllers/DoctorController.php` — delete (empty stub)
- `app/Models/Assesment.php` — delete (empty stub, unused model)
- `app/Http/Middleware/InitializeTenant.php` — remove dead commented code block (lines 13-53)
- `app/Http/Middleware/RoleMiddleware.php` — remove `lab`/`supplier`/`center` redirect paths
- `routes/api.php` — remove sample credentials comment block (lines 157-172)
- `app/Http/Controllers/Api/ClinicSettingController.php` — remove dead `workingHours()` method (line 90) if it has no route

**Changes:**
- Delete stub files entirely
- Remove commented-out code blocks
- Remove purged-module references from RoleMiddleware
- Remove credential comments from api.php
- Remove dead controller methods

**Why:** Dead code causes confusion, wastes cognitive load during reading, and may be flagged by static analysis.

---

## Task 4.4: Remove duplicate tenant() relationships from models

**Files affected:**
- `app/Models/User.php` — remove `tenant()` (already in MTT)
- `app/Models/Doctor.php` — remove `tenant()` (already in MTT)
- `app/Models/Patient.php` — remove `tenant()` (already in MTT)
- `app/Models/Expense.php` — remove `tenant()` (already in MTT)

**Changes:**
Delete the `tenant()` belongsTo method from each model. The `MultiTenantTrait` already defines it.

**Why:** Redundant definitions risk diverging from the trait's version. The trait version is the single source of truth.

**Verify:**
- `php artisan test --filter=TenantScopedModelTest` passes (tenant scoping still works)
- Models that redefined `tenant()` can still access `$model->tenant`

---

## Task 4.5: Fix broken Doctor::assesment() relationship

**Files affected:**
- `app/Models/Doctor.php` — remove `assesment()` relationship (references non-existent `assesment_id` column)
- Or implement the relationship properly if `Assesment` model is needed

**Changes:**
- Remove the `assesment()` method from `Doctor.php`
- If assessment tracking per doctor is needed, implement via a proper `doctor_assessments` pivot or use `PatientAssessment.doctor_id`

**Why:** `Doctor::assesment()` references `App\Models\Assesment` with a default FK `assesment_id` that no migration creates. Calling it throws a SQL error.

**Verify:**
- `$doctor->assesment` anywhere in the codebase would previously error; after removal it's a dead call that should also be removed if found (grep for `->assesment` in `app/`)
