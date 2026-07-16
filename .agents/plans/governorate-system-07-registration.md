# Task 07 — Registration Flow

## 7.1: Update TenantService

**File:** `app/Services/TenantService.php`

### Update createTenantForUser() method (line 38)

**Current:**
```php
public static function createTenantForUser($user)
{
    $tenant = Tenant::create([
        'name' => $user->nameClinic ?: $user->name . ' Clinic',
        'city' => $user->city,
        'owner_id' => $user->id,
    ]);

    $user->withoutGlobalScope('tenant')->update(['tenant_id' => $tenant->id]);
    return $tenant;
}
```

**Replace with:**
```php
public static function createTenantForUser($user)
{
    $tenant = Tenant::create([
        'name' => $user->nameClinic ?: $user->name . ' Clinic',
        'city' => $user->city,
        'governorate_id' => $user->governorate_id,
        'city_id' => $user->city_id,
        'owner_id' => $user->id,
    ]);

    $user->withoutGlobalScope('tenant')->update(['tenant_id' => $tenant->id]);
    return $tenant;
}
```

---

## 7.2: Update RegisterService

**File:** `app/Services/RegisterService.php`

### Update register() method (line 18)

In the `$userData` array (lines 24-36), add `governorate_id` and `city_id`:

```php
$userData = [
    'id' => Str::uuid(),
    'name' => $request->name,
    'nameClinic' => $request->nameplace ?? $request->nameClinic,
    'email' => $request->email,
    'role' => $request->role,
    'location' => $request->location,
    'isActive' => $request->isActive ?? false,
    'city' => $request->city,
    'governorate_id' => $request->governorate_id,
    'city_id' => $request->city_id,
    'phone' => $request->phone,
    'token_device' => json_encode($request->token_device ?? ''),
    'password' => Hash::make($request->password),
];
```

`$request->city` can now be:
- The city ID (UUID) from the city dropdown
- Or the old free-text city name from `config('cities.cities')`

If we keep backward compat, the register form will send `city_id` (UUID) from the cascading dropdown. The `city` field can remain as the city name for backward compat (we can populate it from the City model or keep it as-is if user still sends it).

**Simpler approach:** The register form sends `governorate_id` and `city_id` (UUIDs). The RegisterService also sets `city` as free-text by looking up the city name:

```php
'city' => \App\Models\City::find($request->city_id)?->name ?? $request->city,
'governorate_id' => $request->governorate_id,
'city_id' => $request->city_id,
```

### Update validation in PublicController::storePatient()

Add `governorate_id` and `city_id` validation rules:

```php
$request->validate([
    'name' => 'required|string|max:255',
    'nameClinic' => 'required|string|max:255',
    'email' => 'required|email|unique:users',
    'role' => 'required|string|in:doctor,clinic',
    'location' => 'required|string|max:255',
    'governorate_id' => 'required|exists:governorates,id',
    'city_id' => 'required|exists:cities,id',
    'phone' => 'required|string|unique:users',
    'password' => 'required|string|min:6|confirmed',
]);
```

---

## 7.3: Update Register Blade View

**File:** `resources/views/public/register.blade.php`

### Replace city dropdown with governorate + city cascading dropdowns

**Current city dropdown (lines 62-71):**
```html
<div class="mb-3">
    <label for="city" class="form-label">المدينة</label>
    <select name="city" id="city" class="form-select" required>
        <option value="">اختر المدينة</option>
        @foreach (config('cities.cities') as $city)
            <option value="{{ $city }}" {{ old('city') == $city ? 'selected' : '' }}>
                {{ $city }}
            </option>
        @endforeach
    </select>
</div>
```

**Replace with:**
```html
<div class="mb-3">
    <label for="governorate_id" class="form-label">المحافظة</label>
    <select name="governorate_id" id="governorate_id" class="form-select" required>
        <option value="">اختر المحافظة</option>
        @foreach (\App\Models\Governorate::all() as $gov)
            <option value="{{ $gov->id }}" {{ old('governorate_id') == $gov->id ? 'selected' : '' }}>
                {{ $gov->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="city_id" class="form-label">المدينة</label>
    <select name="city_id" id="city_id" class="form-select" required>
        <option value="">اختر المدينة</option>
        @if (old('governorate_id'))
            @foreach (\App\Models\City::where('governorate_id', old('governorate_id'))->get() as $city)
                <option value="{{ $city->id }}" {{ old('city_id') == $city->id ? 'selected' : '' }}>
                    {{ $city->name }}
                </option>
            @endforeach
        @endif
    </select>
</div>
```

### Add JavaScript for cascading dropdown

Add at the bottom (before `</body>`):

```html
<script>
document.getElementById('governorate_id').addEventListener('change', function() {
    const governorateId = this.value;
    const citySelect = document.getElementById('city_id');
    citySelect.innerHTML = '<option value="">اختر المدينة</option>';

    if (governorateId) {
        fetch('/api/cities', {
            headers: { 'X-Governorate-Id': governorateId }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                data.data.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.id;
                    option.textContent = city.name;
                    citySelect.appendChild(option);
                });
            }
        })
        .catch(err => console.error('Error loading cities:', err));
    }
});
</script>
```

Note: This uses the public `/api/cities` endpoint which requires `X-Governorate-Id` header. The fetch call sends it explicitly. The register page itself doesn't need a governorate header set globally — the JS sends it per request.

### Remove old config('cities.cities') references

No remaining uses of `config('cities.cities')` should remain in this file.

---

## 7.4: Update AuthController (API register)

**File:** `app/Http/Controllers/Api/AuthController.php`

Find the `register()` method and ensure it passes `governorate_id` and `city_id` from the request to `RegisterService`.

The API register endpoint already validates and passes fields through `RegisterService`. Add `governorate_id` and `city_id` to validation if they exist in the request (API register might have different validation than web register).

---

## Verify

- Web register: select governorate → cities load dynamically → submit → user + tenant created with governorate_id + city_id
- API register: POST with `governorate_id` + `city_id` → user + tenant created correctly
- `php artisan db:seed` — governorates + cities populated; existing tenants backfilled
- Old register flow without governorate_id still works (city_id nullable, backwards compatible)
