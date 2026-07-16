# Task 3 — Add `?is_sync=0` Filter + Mark-as-Synced to All Index Endpoints

## Summary

كل API endpoint من نوع `index()` يجب أن:
1. يقبل `?is_sync=0` كـ query parameter
2. عند وجوده، يفلتر السجلات حيث `is_synced = false`
3. بعد جلب النتائج، يحدث `is_synced = true` للسجلات المُرجَعة
4. عند عدم وجوده (الوضع الطبيعي)، يُرجع كل السجلات كما كان سابقًا — لا تغيير

---

## Pattern per controller

أضف الكود التالي في بداية `index()` method بعد `$query = Model::query()`:

```php
// Incremental sync filter — only return unsynced records
if ($request->has('is_sync') && $request->is_sync == '0') {
    $query->where('is_synced', false);
}
```

بعد جلب البيانات مباشرةً وقبل `return response()->json(...)`:

```php
// Mark fetched unsynced records as synced
if ($request->has('is_sync') && $request->is_sync == '0') {
    $ids = $results->pluck('id')->toArray();
    if (!empty($ids)) {
        ModelClass::whereIn('id', $ids)->update(['is_synced' => true]);
    }
}
```

**تنبيه:** المتغير `$results` قد يكون `$patients` أو `$appointments` ... إلخ، حسب كل controller. استخدم اسم المتغير الصحيح.

---

## Controllers to modify (14 files)

| # | Controller | File | Notes |
|---|------------|------|-------|
| 1 | `PatientController` | `app/Http/Controllers/Api/PatientController.php` | index() line ~13, uses `$patients` |
| 2 | `AppointmentController` | `app/Http/Controllers/Api/AppointmentController.php` | Check index() method |
| 3 | `TreatmentSessionController` | `app/Http/Controllers/Api/TreatmentSessionController.php` | Check index() method |
| 4 | `SessionTreatmentController` | `app/Http/Controllers/Api/SessionTreatmentController.php` | Check index() method |
| 5 | `TreatmentTypeController` | `app/Http/Controllers/Api/TreatmentTypeController.php` | Check index() method |
| 6 | `InvoiceController` | `app/Http/Controllers/Api/InvoiceController.php` | Check index() method |
| 7 | `PaymentController` | `app/Http/Controllers/Api/PaymentController.php` | Check index() method |
| 8 | `PrescriptionController` | `app/Http/Controllers/Api/PrescriptionController.php` | Check index() method |
| 9 | `ExpenseController` | `app/Http/Controllers/Api/ExpenseController.php` | Check index() method |
| 10 | `MedicationController` | `app/Http/Controllers/Api/MedicationController.php` | Check index() method |
| 11 | `ClinicSettingController` | `app/Http/Controllers/Api/ClinicSettingController.php` | Check index() method |
| 12 | `WorkingHourController` | `app/Http/Controllers/Api/WorkingHourController.php` | Check index() method |
| 13 | `PatientAssessmentController` | `app/Http/Controllers/Api/PatientAssessmentController.php` | Check index() method |
| 14 | `NotificationController` | `app/Http/Controllers/Api/NotificationController.php` | Check index() method |

---

## Example — PatientController

```php
// Before:
public function index(Request $request)
{
    $query = Patient::query()
        ->withCount('appointments', 'treatmentSessions')
        ->select('patients.*');

    if ($request->filled('doctor_id')) {
        $query->where('doctor_id', $request->doctor_id);
    }

    if ($request->filled('search')) {
        // ...
    }

    $patients = $query->latest()->paginate(15);

    return response()->json([
        'success' => true,
        'data' => $patients,
    ]);
}

// After:
public function index(Request $request)
{
    $query = Patient::query()
        ->withCount('appointments', 'treatmentSessions')
        ->select('patients.*');

    // Incremental sync filter
    if ($request->has('is_sync') && $request->is_sync == '0') {
        $query->where('is_synced', false);
    }

    if ($request->filled('doctor_id')) {
        $query->where('doctor_id', $request->doctor_id);
    }

    if ($request->filled('search')) {
        // ...
    }

    $patients = $query->latest()->paginate(15);

    // Mark fetched records as synced
    if ($request->has('is_sync') && $request->is_sync == '0') {
        $ids = $patients->pluck('id')->toArray();
        if (!empty($ids)) {
            Patient::whereIn('id', $ids)->update(['is_synced' => true]);
        }
    }

    return response()->json([
        'success' => true,
        'data' => $patients,
    ]);
}
```

---

## Edge Cases

| Scenario | Behavior |
|----------|----------|
| `?is_sync=0` + no unsynced records | Returns empty array, no update needed |
| `?is_sync=0` + 100 records | All 100 returned, all marked synced |
| Normal request (no `?is_sync`) | No filter, no mark — existing behavior untouched |
| `?is_sync=1` | NOT handled (only `0` is meaningful) |
| Paginated results | Only the current page records are marked synced — client should re-fetch next page |

---

## Important: Pagination Consideration

When using pagination with `?is_sync=0`, only the returned page's records get marked. The mobile client should:

1. Call `GET /api/patients?is_sync=0&page=1`
2. Process records
3. Call `GET /api/patients?is_sync=0&page=2`
4. ...until empty page

**Alternative (recommended for sync):** When `?is_sync=0` is present, bypass pagination and return all matching records:

```php
if ($request->has('is_sync') && $request->is_sync == '0') {
    $query->where('is_synced', false);
    $results = $query->latest()->get(); // No pagination for sync
} else {
    $results = $query->latest()->paginate(15); // Normal pagination
}
```

This ensures all unsynced records are returned and marked in one shot.
