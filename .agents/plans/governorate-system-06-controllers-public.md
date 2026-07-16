# Task 06 — Controllers (Public + API)

## 6.1: Update PublicController

**File:** `app/Http/Controllers/PublicController.php`

All doctor queries must filter by governorate (from `config('app.current_governorate_id')`).

### 6.1.1: Add helper method

Add a private helper to get the governorate-filtered doctor query:

```php
private function doctorsByGovernorate()
{
    $governorateId = config('app.current_governorate_id');

    $query = User::where('role', 'doctor')->where('isActive', 1);

    if ($governorateId) {
        $query->where('governorate_id', $governorateId);
    }

    return $query;
}
```

### 6.1.2: Update index() method

**Current (line 23-25):**
```php
$doctors = User::where('role', 'doctor')
    ->where('isActive', 1)
    ->get();
```

**Replace with:**
```php
$doctors = $this->doctorsByGovernorate()->get();
```

### 6.1.3: Update assessment() method

**Current (lines 37-39):**
```php
$doctors = User::where('role', 'doctor')
    ->where('isActive', 1)
    ->get();
```

**Replace with:**
```php
$doctors = $this->doctorsByGovernorate()->get();
```

### 6.1.4: Update booking() method

**Current (lines 135-137):**
```php
$doctors = User::where('role', 'doctor')
    ->where('isActive', 1)
    ->get();
```

**Replace with:**
```php
$doctors = $this->doctorsByGovernorate()->get();
```

Also update the `where('role', 'doctor')` query on line 125 (single doctor lookup) — no change needed since it queries by ID.

### 6.1.5: Working hours query

Working hours are fetched in `index()` at line 28:
```php
$workingHours = WorkingHour::where('is_active', 1)->get()->groupBy('day_of_week');
```

This should also be scoped to governorate's doctors. Update to:
```php
$doctorIds = $this->doctorsByGovernorate()->pluck('id');
$workingHours = WorkingHour::where('is_active', 1)
    ->whereIn('doctor_id', $doctorIds)
    ->get()
    ->groupBy('day_of_week');
```

And in `booking()` at line 140:
```php
$workingHours = WorkingHour::where('is_active', 1)
    ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
    ->get();
```

Keep this one as-is (it's filtered by specific doctor, already scoped). But ensure the doctor belongs to the current governorate context (already handled by `doctorsByGovernorate()`).

---

## 6.2: Update UserController (API)

**File:** `app/Http/Controllers/Api/UserController.php`

### 6.2.1: Update index() method

**Current (lines 35-38):**
```php
$doctors = User::where('role', 'doctor')
    ->orWhereHas('doctor')
    ->with('doctor')
    ->get()
    ->map(...);
```

**Replace with:**
```php
$query = User::where('role', 'doctor')->orWhereHas('doctor');

$governorateId = config('app.current_governorate_id');
if ($governorateId) {
    $query->where('governorate_id', $governorateId);
}

$doctors = $query->with('doctor')->get()->map(...);
```

### 6.2.2: Update doctors() method

**Current (lines 66-68):**
```php
$doctors = User::where('role', 'doctor')
    ->orWhereHas('doctor')
    ->select('id', 'name', 'email', 'phone', 'nameClinic', 'city', 'isActive')
    ->get();
```

**Replace with:**
```php
$query = User::where('role', 'doctor')->orWhereHas('doctor');
$governorateId = config('app.current_governorate_id');
if ($governorateId) {
    $query->where('governorate_id', $governorateId);
}
$doctors = $query->select('id', 'name', 'email', 'phone', 'nameClinic', 'city', 'isActive')->get();
```

### 6.2.3: Update doctorsFull() method

**Current (lines 82-83):**
```php
$query = User::where('role', 'doctor')
    ->orWhereHas('doctor');
```

**Add after that line:**
```php
$governorateId = config('app.current_governorate_id');
if ($governorateId) {
    $query->where('governorate_id', $governorateId);
}
```

Add `governorate_id` to the response data if desired.

---

## 6.3: Update AppointmentController public endpoints

**File:** `app/Http/Controllers/Api/AppointmentController.php`

### 6.3.1: Update publicIndex() method (line 98)

This endpoint currently requires `tenant_id` as query param. After governorate filtering, it should:
1. Validate the tenant belongs to the current governorate
2. Or accept `tenant_id` and validate it's within the governorate's tenants

**Updated logic:**
```php
public function publicIndex(Request $request)
{
    $governorateId = config('app.current_governorate_id');

    $tenantQuery = Tenant::query();
    if ($governorateId) {
        $tenantQuery->where('governorate_id', $governorateId);
    }

    if ($request->filled('tenant_id')) {
        $tenant = $tenantQuery->where('id', $request->tenant_id)->first();
        if (!$tenant) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'يرجى تقديم معرف العيادة الصحيح',
            ]);
        }
    } else {
        return response()->json([
            'success' => true,
            'data' => [],
            'message' => 'يرجى تقديم معرف العيادة الصحيح',
        ]);
    }

    // ... rest of the query unchanged (uses withoutTenantScope + where tenant_id) ...
}
```

### 6.3.2: Update getPublicAvailableTimes() method (line 616)

This endpoint uses `doctor_id` directly. No governorate filtering needed here if the caller passes a doctor_id — the doctor is validated via `exists:users,id`. However, to prevent querying doctors from other governorates, add governorate validation:

```php
$request->validate([
    'date'      => 'required|date|after_or_equal:today',
    'doctor_id' => 'required|exists:users,id',
]);

// Validate doctor belongs to current governorate
$governorateId = config('app.current_governorate_id');
if ($governorateId) {
    $doctor = User::where('id', $request->doctor_id)
        ->where('governorate_id', $governorateId)
        ->first();
    if (!$doctor) {
        return response()->json([
            'success' => false,
            'message' => 'Doctor not available in this governorate',
        ], 400);
    }
}
```

---

## Verify

For each modified method:
- With valid `X-Governorate-Id` → returns only doctors/tenants in that governorate
- Without header (if middleware allows) → returns empty or all (depending on context)
- Cross-governorate data is NOT visible (e.g., Damascus governorate users don't see Aleppo doctors)
