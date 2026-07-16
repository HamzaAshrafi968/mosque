# Task 04 — InitializeGovernorate Middleware

## Overview

Creates middleware that reads `X-Governorate-Id` header and sets the governorate context for the request. Pattern follows `InitializeTenant` middleware.

---

## 4.1: Create middleware

**File:** `app/Http/Middleware/InitializeGovernorate.php`

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Governorate;

class InitializeGovernorate
{
    public function handle(Request $request, Closure $next)
    {
        // Exclude login and register endpoints
        if ($request->is('api/register') || $request->is('api/login')) {
            return $next($request);
        }

        // Exclude governorates listing (initial data, no header needed)
        if ($request->is('api/governorates')) {
            return $next($request);
        }

        $governorateId = $request->header('X-Governorate-Id');

        if (!$governorateId) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'data' => [],
                    'message' => 'Governorate header is required. Please provide X-Governorate-Id.',
                ], 400);
            }
        }

        $governorate = Governorate::find($governorateId);

        if (!$governorate) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'data' => [],
                    'message' => 'Invalid governorate. The provided X-Governorate-Id does not exist.',
                ], 400);
            }
        }

        config(['app.current_governorate_id' => $governorateId]);

        return $next($request);
    }
}
```

**Behavior:**
- `POST /api/register`, `POST /api/login` → pass through (like InitializeTenant)
- `GET /api/governorates` → pass through (initial endpoint, no header needed)
- Any API request with missing `X-Governorate-Id` → 400 with error message + empty data
- Any API request with invalid `X-Governorate-Id` → 400 with error message + empty data
- Valid header → sets `config('app.current_governorate_id')` and passes through

---

## 4.2: Register middleware in bootstrap/app.php

**File:** `bootstrap/app.php`

Add alias in `withMiddleware` closure:

```php
$middleware->alias([
    'initialize.tenant' => InitializeTenant::class,
    'initialize.governorate' => InitializeGovernorate::class,  // <-- add this
]);
```

Also add the import at the top:
```php
use App\Http\Middleware\InitializeGovernorate;
```

**File structure at lines 3-6 currently:**
```php
use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\InitializeTenant;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\WebAuthenticate;
```

Add:
```php
use App\Http\Middleware\InitializeGovernorate;
```

---

## Apply middleware in routes

See Task 05 (`governorate-system-05-routes.md`) for route configuration.

---

## Verify

- `GET /api/cities` without header → 400, `"Governorate header is required"`
- `GET /api/cities` with `X-Governorate-Id: invalid-uuid` → 400, `"Invalid governorate"`
- `GET /api/cities` with valid governorate UUID → passes through to controller
- `POST /api/login` → works without header (excluded)
- `POST /api/register` → works without header (excluded)
- `GET /api/governorates` → works without header (excluded)
