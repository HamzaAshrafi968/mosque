# Task 5 — Create Bulk Push Endpoint

## Summary

الموبايل يحتاج endpoint واحد لدفع جميع السجلات المعدلة محليًا إلى السيرفر دفعة واحدة، بدلًا من استدعاء `POST /api/patients` ثم `POST /api/appointments` ... إلخ، واحدة تلو الأخرى.

---

## Subtask 5.1 — Add route

In `routes/api.php`, inside the auth group:

```php
Route::post('/sync/bulk-push', [SyncController::class, 'bulkPush']);
```

---

## Subtask 5.2 — Add `bulkPush()` to `SyncController`

Add to `app/Http/Controllers/Api/SyncController.php`:

```php
use App\Models\{
    Appointment, Attachment, ClinicSetting, Expense, Invoice,
    InvoiceItem, Medication, Notification, Patient, PatientAssessment,
    Payment, Prescription, SessionTreatment, TreatmentSession,
    TreatmentType, User, WhatsappMessage, WorkingHour, WorkingHourRange
};
use Illuminate\Support\Facades\Log;

/**
 * POST /api/sync/bulk-push
 * Accepts an array of entities to upsert on the server.
 *
 * Body format:
 * {
 *   "entities": [
 *     {
 *       "entity_type": "patient",
 *       "action": "create",     // or "update", "delete"
 *       "data": { ... }         // full record data
 *     },
 *     ...
 *   ]
 * }
 */
public function bulkPush(Request $request): JsonResponse
{
    $validated = $request->validate([
        'entities' => 'required|array',
        'entities.*.entity_type' => 'required|string|in:patient,appointment,treatment_session,session_treatment,patient_assessment,invoice,invoice_item,payment,prescription,expense,medication,treatment_type,clinic_setting,working_hour,working_hour_range,notification,user,whatsapp_message,attachment',
        'entities.*.action' => 'required|string|in:create,update,delete',
        'entities.*.data' => 'required|array',
    ]);

    $tenantId = TenantService::getCurrentTenantId();
    $results = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];

    // Map entity_type to model class
    $modelMap = [
        'patient' => Patient::class,
        'appointment' => Appointment::class,
        'treatment_session' => TreatmentSession::class,
        'session_treatment' => SessionTreatment::class,
        'patient_assessment' => PatientAssessment::class,
        'invoice' => Invoice::class,
        'invoice_item' => InvoiceItem::class,
        'payment' => Payment::class,
        'prescription' => Prescription::class,
        'expense' => Expense::class,
        'medication' => Medication::class,
        'treatment_type' => TreatmentType::class,
        'clinic_setting' => ClinicSetting::class,
        'working_hour' => WorkingHour::class,
        'working_hour_range' => WorkingHourRange::class,
        'notification' => Notification::class,
        'user' => User::class,
        'whatsapp_message' => WhatsappMessage::class,
        'attachment' => Attachment::class,
    ];

    foreach ($validated['entities'] as $index => $entity) {
        $modelClass = $modelMap[$entity['entity_type']] ?? null;

        if (!$modelClass) {
            $results['errors'][] = "Index {$index}: Unknown entity_type '{$entity['entity_type']}'";
            continue;
        }

        $data = $entity['data'];

        try {
            match ($entity['action']) {
                'create', 'update' => $this->upsertEntity($modelClass, $data, $tenantId, $entity['action'], $results),
                'delete' => $this->deleteEntity($modelClass, $data['id'] ?? null, $results, $index),
            };
        } catch (\Exception $e) {
            Log::error('Sync bulk push error', [
                'entity_type' => $entity['entity_type'],
                'index' => $index,
                'error' => $e->getMessage(),
            ]);
            $results['errors'][] = "Index {$index} ({$entity['entity_type']}): {$e->getMessage()}";
        }
    }

    return response()->json([
        'success' => count($results['errors']) === 0,
        'data' => $results,
    ]);
}

private function upsertEntity(string $modelClass, array $data, string $tenantId, string $action, array &$results): void
{
    // Ensure tenant context for multi-tenant models
    if (!isset($data['tenant_id'])) {
        $data['tenant_id'] = $tenantId;
    }

    // Mark as synced since mobile is pushing this data
    $data['is_synced'] = true;

    if ($action === 'create') {
        if (!isset($data['id'])) {
            $data['id'] = Str::uuid();
        }
        $modelClass::create($data);
        $results['created']++;
    } else {
        // update
        $id = $data['id'] ?? null;
        if (!$id) {
            throw new \Exception('Missing id for update');
        }
        $model = $modelClass::find($id);
        if ($model) {
            $model->update($data);
            $results['updated']++;
        } else {
            // If not found, create it (offline-first: mobile may have created it)
            $modelClass::create($data);
            $results['created']++;
        }
    }
}

private function deleteEntity(string $modelClass, ?string $id, array &$results, int $index): void
{
    if (!$id) {
        throw new \Exception('Missing id for delete');
    }
    $model = $modelClass::find($id);
    if ($model) {
        $model->delete();
        $results['deleted']++;
    }
}
```

---

## Notes

- **`is_synced = true`:** السجلات المدفوعة من الموبايل تُعلَّم مباشرة كـ synced لأن الموبايل يملكها أصلًا.
- **Update → Create fallback:** إذا لم يُوجد السجل على السيرفر (تم حذفه)، يتم إنشاؤه (offline-first safety).
- **Error handling:** كل entity تُعالَج بشكل منفصل — فشل entity لا يوقف معالجة البقية.
- **Tenant isolation:** `tenant_id` يُضاف تلقائيًا إذا لم يُرسَل من الموبايل.
- **No validation per entity:** الـ bulk endpoint لا يُكرر validation rules لكل entity (لتجنب التعقيد). الموبايل مسؤول عن إرسال بيانات صحيحة.
