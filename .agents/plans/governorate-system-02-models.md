# Task 02 — Models

## 2.1: Create Governorate model

**File:** `app/Models/Governorate.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\UuidTrait;

class Governorate extends Model
{
    use HasFactory, UuidTrait;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['name'];

    protected $casts = ['id' => 'string'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
```

- Uses `UuidTrait` (UUID primary key, same pattern as all project models)
- Does NOT use `MultiTenantTrait` — governorates are global (unscoped), like `Tenant` model
- Relations: `cities()`, `tenants()`

---

## 2.2: Create City model

**File:** `app/Models/City.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\UuidTrait;

class City extends Model
{
    use HasFactory, UuidTrait;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['name', 'governorate_id'];

    protected $casts = ['id' => 'string'];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
```

- Does NOT use `MultiTenantTrait` — cities are global (unscoped)
- Relations: `governorate()`, `tenants()`

---

## 2.3: Update Tenant model

**File:** `app/Models/Tenant.php`

**Changes:**

Add to `$fillable`:
```php
'governorate_id',
'city_id',
```

Add to `$casts`:
```php
'governorate_id' => 'string',
'city_id' => 'string',
```

Add relations:
```php
public function governorate(): BelongsTo
{
    return $this->belongsTo(Governorate::class);
}

public function city(): BelongsTo
{
    return $this->belongsTo(City::class);
}
```

Add import at top:
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
```

---

## 2.4: Update User model

**File:** `app/Models/User.php`

Read current `$fillable` (lines 20-33) to determine exact position.

**Changes:**

Add to `$fillable`:
```php
'governorate_id',
'city_id',
```

Add to `$casts`:
```php
'governorate_id' => 'string',
'city_id' => 'string',
```

Add relations:
```php
public function governorate(): BelongsTo
{
    return $this->belongsTo(Governorate::class);
}

public function city(): BelongsTo
{
    return $this->belongsTo(City::class);
}
```

Add import at top:
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
```

---

## Verify

- `php artisan tinker` → `Governorate::class`, `City::class` resolve
- `Tenant::first()->governorate` and `User::first()->governorate` don't throw errors when relations are empty (nullable FK)
