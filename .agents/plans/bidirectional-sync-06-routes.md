# Task 6 — Register Sync Routes

## Summary

تسجيل جميع الـ routes الجديدة الخاصة بالمزامنة في `routes/api.php`.

---

## Changes to `routes/api.php`

Add the following inside the `Route::middleware(['auth:sanctum', InitializeTenant::class])->group(function () { ... })` block:

```php
// Sync — المزامنة بين الموبايل والسيرفر
Route::post('/sync/bulk-push', [SyncController::class, 'bulkPush']);
Route::get('/sync/metadata', [SyncController::class, 'getMetadata']);
Route::put('/sync/metadata', [SyncController::class, 'updateMetadata']);
Route::delete('/sync/metadata', [SyncController::class, 'resetMetadata']);
```

Add the import at the top of the file:

```php
use App\Http\Controllers\Api\SyncController;
```

---

## Full routes/api.php after changes

```php
<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClinicSettingController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MedicationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PatientAssessmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\SessionTreatmentController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\SyncController;              // NEW
use App\Http\Controllers\Api\TeethController;
use App\Http\Controllers\Api\TreatmentSessionController;
use App\Http\Controllers\Api\TreatmentTypeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkingHourController;
use App\Http\Middleware\InitializeTenant;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Public endpoints (no auth required)
Route::get('/appointments/public', [AppointmentController::class, 'publicIndex']);
Route::get('/appointments/public/available-times', [AppointmentController::class, 'getPublicAvailableTimes']);
Route::get('/cities', function () {
    return response()->json([
        'success' => true,
        'data' => config('cities.cities'),
    ]);
});

// Protected routes
Route::middleware(['auth:sanctum', InitializeTenant::class])->group(
    function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Sync — المزامنة بين الموبايل والسيرفر              // NEW
        Route::post('/sync/bulk-push', [SyncController::class, 'bulkPush']);   // NEW
        Route::get('/sync/metadata', [SyncController::class, 'getMetadata']);  // NEW
        Route::put('/sync/metadata', [SyncController::class, 'updateMetadata']); // NEW
        Route::delete('/sync/metadata', [SyncController::class, 'resetMetadata']); // NEW

        // Patients CRUD
        Route::get('patients', [PatientController::class, 'index']);
        // ... rest unchanged
    }
);
```

---

## API endpoints summary (new)

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| POST | `/api/sync/bulk-push` | sanctum + tenant | Push all local changes to server |
| GET | `/api/sync/metadata` | sanctum + tenant | Get sync metadata (last_pull_at per entity) |
| PUT | `/api/sync/metadata` | sanctum + tenant | Update last_pull_at for entity |
| DELETE | `/api/sync/metadata` | sanctum + tenant | Reset all metadata (logout) |

---

## API endpoints summary (modified)

All existing `GET /api/{entity}` endpoints now support `?is_sync=0`:

| Endpoint | New behavior with `?is_sync=0` |
|----------|-------------------------------|
| GET `/api/patients` | Returns only `is_synced = false` records |
| GET `/api/appointments` | Same |
| GET `/api/treatment-sessions` | Same |
| GET `/api/session-treatments` | Same |
| GET `/api/treatment-types` | Same |
| GET `/api/invoices` | Same |
| GET `/api/payments` | Same |
| GET `/api/prescriptions` | Same |
| GET `/api/expenses` | Same |
| GET `/api/medications` | Same |
| GET `/api/clinic-settings` | Same |
| GET `/api/working-hours` | Same |
| GET `/api/patient-assessments` | Same |
| GET `/api/notifications` | Same |
