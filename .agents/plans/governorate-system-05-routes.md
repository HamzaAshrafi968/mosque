# Task 05 — Routes

## 5.1: Update routes/api.php

### Add governorates endpoint (public, no auth)

Add after line 24 (before public routes section) or in the public routes section:

```php
// Governorates and Cities
Route::get('/governorates', function () {
    $governorates = \App\Models\Governorate::with('cities')->get();

    return response()->json([
        'success' => true,
        'data' => $governorates->map(fn($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'cities' => $g->cities->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]),
        ]),
    ]);
});
```

This endpoint:
- Requires NO auth (public)
- Requires NO `X-Governorate-Id` header (excluded in middleware)
- Returns all governorates with their nested cities
- Used by mobile app for initial governorate selection + city selection

### Update cities endpoint

Modify `GET /api/cities` (currently at line 32):

```php
Route::get('/cities', function (\Illuminate\Http\Request $request) {
    $governorateId = $request->header('X-Governorate-Id')
        ?? config('app.current_governorate_id');

    if (!$governorateId) {
        return response()->json([
            'success' => false,
            'data' => [],
            'message' => 'Governorate header is required',
        ], 400);
    }

    $governorate = \App\Models\Governorate::with('cities')->find($governorateId);

    if (!$governorate) {
        return response()->json([
            'success' => false,
            'data' => [],
            'message' => 'Invalid governorate',
        ], 400);
    }

    return response()->json([
        'success' => true,
        'data' => $governorate->cities->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
        ]),
    ]);
});
```

**Change summary:** Replace the old `config('cities.cities')` flat array with governorate-filtered cities from DB. Requires `X-Governorate-Id` header.

### Apply InitializeGovernorate middleware to public endpoints

The middleware should be applied to the specific public routes that need governorate filtering. Since `InitializeGovernorate` sets a config value, it can be applied as route-level middleware:

```php
// Wrap public endpoints that need governorate filtering
Route::middleware([InitializeGovernorate::class])->group(function () {
    Route::get('/cities', ...);
    Route::get('/appointments/public', [AppointmentController::class, 'publicIndex'])
        ->middleware('throttle:public-api');
    Route::get('/appointments/public/available-times', [AppointmentController::class, 'getPublicAvailableTimes'])
        ->middleware('throttle:public-api');
});
```

Add import at top (if not already):
```php
use App\Http\Middleware\InitializeGovernorate;
```

---

## 5.2: Update bootstrap/app.php

Already done in Task 04 (register middleware alias). No additional changes needed.

---

## 5.3: Delete config/cities.php

**File:** `config/cities.php`

Delete this file entirely. All city/governorate data now comes from the database.

Check for any remaining references to `config('cities.cities')` across the codebase. If found (e.g., in views, controllers), replace with DB queries or Governorate/City model calls.

**Likely affected files:**
- `resources/views/public/register.blade.php` — currently uses `config('cities.cities')` for city dropdown → will be replaced in Task 07
- Any other Blade view using `config('cities.cities')`

---

## Verify

- `GET /api/governorates` — returns 14 governorates with nested cities (no header needed)
- `GET /api/cities` with valid `X-Governorate-Id` — returns cities for that governorate only
- `GET /api/cities` without header — 400 error
- `GET /api/appointments/public` without header — 400 error
- `POST /api/login` — still works (excluded in middleware)
