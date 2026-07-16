# Task 01 — Database Migrations

## 1.1: Create governors table

**File:** `database/migrations/2026_07_07_000001_create_governorates_table.php`

```php
Schema::create('governorates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name')->unique();
    $table->timestamps();
});
```

- Use `uuid` primary key (pattern from all other tables in project)
- `name` is unique (Arabic governorate name)

---

## 1.2: Create cities table

**File:** `database/migrations/2026_07_07_000002_create_cities_table.php`

```php
Schema::create('cities', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->uuid('governorate_id');
    $table->foreign('governorate_id')->references('id')->on('governorates')->onDelete('cascade');
    $table->timestamps();

    $table->unique(['name', 'governorate_id']);
    $table->index('governorate_id');
});
```

- Compound unique on `(name, governorate_id)` — same city name can exist in different governorates (e.g., both Damascus governorate AND Rif Dimashq governorate have areas called "دمشق" related entries)
- FK cascade delete: deleting a governorate deletes its cities

---

## 1.3: Add governorate_id and city_id to tenants and users

**File:** `database/migrations/2026_07_07_000003_add_governorate_city_to_tenants_users.php`

**tenants table:**
```php
Schema::table('tenants', function (Blueprint $table) {
    $table->uuid('governorate_id')->nullable()->after('city');
    $table->uuid('city_id')->nullable()->after('governorate_id');
    $table->foreign('governorate_id')->references('id')->on('governorates')->nullOnDelete();
    $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
    $table->index('governorate_id');
    $table->index('city_id');
});
```

**users table:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->uuid('governorate_id')->nullable()->after('city');
    $table->uuid('city_id')->nullable()->after('governorate_id');
    $table->foreign('governorate_id')->references('id')->on('governorates')->nullOnDelete();
    $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
    $table->index('governorate_id');
    $table->index('city_id');
});
```

- Keep existing `city` column (free-text string) — backward compat
- New columns are nullable to allow gradual migration
- `nullOnDelete()` — if a governorate/city is deleted, the FK becomes null (doesn't delete the tenant/user)

---

## Verify

- Run `php artisan migrate` — no errors
- Check DB: `governorates`, `cities` tables exist; `tenants`, `users` have new columns
