# Governorate-Based Clinic Filtering System

## Summary

تنظيم العيادات حسب المحافظات. يتم إرسال `X-Governorate-Id` في الهيدر لتصفية العيادات والمدن. بدون الهيدر لا تُرجع النتائج أي بيانات.

**Current state:**
- `config/cities.php` has a flat list of 14 Syrian city names (no governorate hierarchy)
- `tenants`, `users`, `doctors` have free-text `city` column
- No governorate concept exists
- Public endpoints return all doctors/clinics without location filtering

**Goal:**
- Governorates and cities as proper database tables with UUID primary keys
- `X-Governorate-Id` header gates all public API endpoints
- No header → empty response with error message
- Clinics are filtered by governorate when browsing
- Dashboard can filter doctors by city

---

## Core Concepts

### Governorate → City → Clinic hierarchy

```
Governorate (دمشق) ─┬─ City (دمشق)
                     └─ City (...)

Governorate (حلب)  ─┬─ City (حلب)
                     ├─ City (منبج)
                     ├─ City (عفرين)
                     └─ City (...)
```

### Header flow

```
Client opens app → GET /api/governorates (no header needed, initial data)
Client selects governorate → X-Governorate-Id stored in session/state
Client calls GET /api/cities with X-Governorate-Id → cities for that governorate
Client calls any public endpoint with X-Governorate-Id → filtered results
```

### `X-Governorate-Id` header

- Sent on every public API request after initial governorate selection
- Value: UUID string of the governorate
- Missing/invalid → `{ success: false, data: [], message: "Governorate header is required" }`
- Middleware: `InitializeGovernorate` sets `config('app.current_governorate_id')`

---

All tasks: See details in individual files below.

### Files

| # | File | Description |
|---|------|-------------|
| — | `governorate-system.md` | This overview (you are here) |
| 1 | `governorate-system-01-database.md` | Migrations: governorates, cities, FK columns on tenants/users |
| 2 | `governorate-system-02-models.md` | Models: Governorate, City, update Tenant/User with relations |
| 3 | `governorate-system-03-seeders.md` | Seeders: GovernorateCitySeeder, update TenantSeeder backfill |
| 4 | `governorate-system-04-middleware.md` | Middleware: InitializeGovernorate |
| 5 | `governorate-system-05-routes.md` | Routes: api.php + bootstrap/app.php |
| 6 | `governorate-system-06-controllers-public.md` | Controllers: PublicController + UserController + AppointmentController |
| 7 | `governorate-system-07-registration.md` | Registration: RegisterService, TenantService, register view |

---

## Files Checklist

- [ ] `database/migrations/*_create_governorates_table.php` — new
- [ ] `database/migrations/*_create_cities_table.php` — new
- [ ] `database/migrations/*_add_governorate_city_to_tenants_users.php` — new
- [ ] `app/Models/Governorate.php` — new
- [ ] `app/Models/City.php` — new
- [ ] `app/Models/Tenant.php` — add governorate_id, city_id fillable + relations
- [ ] `app/Models/User.php` — add governorate_id, city_id fillable + relations
- [ ] `database/seeders/GovernorateCitySeeder.php` — new
- [ ] `database/seeders/TenantSeeder.php` — update backfill
- [ ] `app/Http/Middleware/InitializeGovernorate.php` — new
- [ ] `bootstrap/app.php` — register middleware alias
- [ ] `routes/api.php` — add governorates/cities endpoints, apply middleware
- [ ] `app/Http/Controllers/Api/UserController.php` — filter by governorate
- [ ] `app/Http/Controllers/PublicController.php` — filter by governorate
- [ ] `app/Http/Controllers/Api/AppointmentController.php` — filter public endpoints
- [ ] `app/Services/RegisterService.php` — store governorate_id + city_id
- [ ] `app/Services/TenantService.php` — accept governorate_id + city_id
- [ ] `resources/views/public/register.blade.php` — governorate + city cascading dropdowns
- [ ] `config/cities.php` — delete (replaced by DB tables)
