# Project Improvement Plan — Overview (Updated 2026-07-07)

Based on a fresh codebase review. Much has improved since the original 4.5/10 rating. See `PLAN.md` for the legacy tasks that have since been completed.

## Overall Rating: **7.5 / 10**

| Category | Previous | Current | Delta |
|---|---|---|---|
| Architecture & Design | 6/10 | 8/10 | +2 |
| Security & Authorization | 3/10 | 6/10 | +3 |
| Code Quality | 5/10 | 7/10 | +2 |
| Test Coverage | 2/10 | 6/10 | +4 |
| Documentation | 5/10 | 6/10 | +1 |
| Maintainability | 4/10 | 6/10 | +2 |

## Phases

| Phase | Focus | Priority | Est. Effort |
|-------|-------|----------|-------------|
| [Phase 01](improvement-phase-01-critical.md) | Critical fixes — User trait, duplicate route, dead middleware, stale migrations, snake_case columns | 🔴 Critical | 2-3h |
| [Phase 02](improvement-phase-02-refactor.md) | Refactoring — split DashboardController, FormRequests, API Resources | 🟡 High | 8-12h |
| [Phase 03](improvement-phase-03-harden.md) | Harden — CI, Larastan, rate limiting, Policy audit, dead code removal | 🟡 Medium | 4-6h |
| [Phase 04](improvement-phase-04-polish.md) | Polish — .env.example, API versioning, missing model config, misc cleanup | 🟢 Low | 2-3h |

## What got fixed since the last review

- ✅ `Invoice` — now has `MultiTenantTrait`
- ✅ `Payment` — now has `UuidTrait` + `MultiTenantTrait`
- ✅ `SessionTreatment` — now has `MultiTenantTrait`
- ✅ `TreatmentType` — now has `MultiTenantTrait`
- ✅ `WorkingHourRange` — now has `UuidTrait` + `MultiTenantTrait`
- ✅ `PatientAssessment` — now has `UuidTrait` + `MultiTenantTrait`
- ✅ Doctor FK migrations squashed (`2026_07_06_000002_squash_doctors_user_fk.php`)
- ✅ `Payment` now has `invoice_id` migration + relation
- ✅ `Invoice::payments()` fixed — now per-invoice, not per-patient
- ✅ 12 Policies created under `app/Policies/`
- ✅ 6 FormRequests created under `app/Http/Requests/`
- ✅ 2 API Resources created (`AppointmentResource`, `ExpenseResource`)
- ✅ Doctor Blade views restored (`resources/views/doctor/`, `resources/views/public/`)
- ✅ 19 test files across Unit, Feature, Middleware, Services
- ✅ Factories exist for all core models
- ✅ API routes (api.php) clean — no lab/supplier/center references
