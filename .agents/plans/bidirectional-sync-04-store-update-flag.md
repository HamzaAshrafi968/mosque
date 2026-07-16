# Task 4 — Auto-set `is_synced = false` on Server-side Creates/Updates

## Summary

عندما يُنشئ أو يُعدَّل سجل من خلال الـ web dashboard أو API العادي (وليس من push الموبايل)، يجب أن يُوضع `is_synced = false` ليتم سحبه لاحقًا من قبل الموبايل.

عندما يدفع الموبايل بيانات (push)، سيرسل `is_synced: true` لأن الموبايل يملك السجل أصلًا — فلا نريد إعادة سحبه.

---

## Strategy: Default to false, let mobile override

في `store()` و `update()` في كل controller، القيمة الافتراضية هي `false`، لكن الموبايل يمكنه إرسال `true` عبر الـ payload:

```php
// In store():
$validated['is_synced'] = $request->input('is_synced', false);

// In update():
$updateData['is_synced'] = $request->input('is_synced', false);
```

هذا يعني:
- Web dashboard / internal API (لا ترسل `is_synced`) → `false` ✅
- Mobile push (ترسل `is_synced: true`) → `true` ✅
- Mobile pull تسحب فقط `is_synced = false` ✅

---

## Controllers to modify

Same 14 controllers from Task 3. For each:

### In `store()` method

```php
// Ensure is_synced is set (default false for server-side creates)
$validated['is_synced'] = $request->input('is_synced', false);
```

Add this **after** `$validated = $request->validate(...)` and **before** `Model::create($validated)`.

### In `update()` method

```php
// Ensure is_synced is set (default false for server-side updates)
$updateData['is_synced'] = $request->input('is_synced', false);
```

Add this **after** extracting update data from request and **before** `$model->update($updateData)`.

---

## Example — PatientController

```php
// store():
public function store(Request $request)
{
    $validated = $request->validate([
        'fullName' => 'nullable|string|max:255',
        // ... other fields
        'is_synced' => 'nullable|boolean',     // ADD to validation
    ]);

    $validated['id'] = $validated['id'] ?? Str::uuid();
    $validated['tenant_id'] = TenantService::getCurrentTenantId();
    $validated['is_synced'] = $request->input('is_synced', false);  // ADD

    $patient = Patient::create($validated);

    return response()->json([
        'success' => true,
        'data' => $patient,
    ], 201);
}

// update():
public function update(Request $request, Patient $patient)
{
    $validated = $request->validate([
        'fullName' => 'nullable|string|max:255',
        // ... other fields
        'is_synced' => 'nullable|boolean',     // ADD to validation
    ]);

    $validated['is_synced'] = $request->input('is_synced', false);  // ADD
    $patient->update($validated);

    return response()->json([
        'success' => true,
        'data' => $patient,
    ]);
}
```

---

## Notes

- **`is_synced` validation:** أضف `'is_synced' => 'nullable|boolean'` إلى كل `$request->validate()` في `store()` و `update()`. هذا يسمح للموبايل بإرسالها ولا يمنع الويب من تجاهلها.
- **`$request->input('is_synced', false)`:** إذا لم تُرسَل القيمة، تُستخدم `false` — وهذا هو السلوك المطلوب للـ web dashboard.
- **لا تنسَ `Str::uuid()` للـ store:** بعض الـ controllers (مثل `PatientController`) تولد `id` يدويًا — تأكد أن `is_synced` يُضاف قبل الـ create.

---

## Alternative: Laravel Model Events (Observer)

بدلًا من تعديل كل controller يدويًا، يمكن استخدام Observer عالمي:

```php
// app/Observers/IsSyncedObserver.php
class IsSyncedObserver
{
    public function creating($model)
    {
        if (!array_key_exists('is_synced', $model->getAttributes())) {
            $model->is_synced = false;
        }
    }

    public function updating($model)
    {
        if (!$model->isDirty('is_synced')) {
            $model->is_synced = false;
        }
    }
}
```

لكن هذا يتطلب تسجيل observer لكل موديل في `AppServiceProvider::boot()`. الخيار الأبسط هو تعديل الـ controllers مباشرة.
