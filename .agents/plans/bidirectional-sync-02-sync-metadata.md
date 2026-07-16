# Task 2 — Create `sync_metadata` Table, Model, and Controller

## Summary

الموبايل بحاجة إلى معرفة آخر وقت تم فيه سحب كل entity type. هذا يتم عبر جدول `sync_metadata` الذي يسجل `last_pull_at` لكل `(tenant_id, entity_type)`.

---

## Subtask 2.1 — Create migration

**File:** `database/migrations/2026_07_04_000001_create_sync_metadata_table.php` (new)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_metadata', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('entity_type');       // e.g. 'patient', 'appointment', 'invoice'
            $table->timestamp('last_pull_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'entity_type']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_metadata');
    }
};
```

---

## Subtask 2.2 — Create `SyncMetadata` model

**File:** `app/Models/SyncMetadata.php` (new)

```php
<?php

namespace App\Models;

use App\Models\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncMetadata extends Model
{
    use HasFactory, UuidTrait;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'entity_type',
        'last_pull_at',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'last_pull_at' => 'datetime',
    ];
}
```

**Note:** هذا الموديل **لا يستخدم `MultiTenantTrait`** — يتم فلترة البيانات يدويًا بـ `tenant_id` لأن التريت قد يتعارض مع جدول ليس له علاقة مباشرة بـ tenant.

---

## Subtask 2.3 — Create `SyncController`

**File:** `app/Http/Controllers/Api/SyncController.php` (new)

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncMetadata;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    /**
     * GET /api/sync/metadata
     * Returns all sync_metadata for the current tenant.
     */
    public function getMetadata(Request $request): JsonResponse
    {
        $tenantId = TenantService::getCurrentTenantId();

        $metadata = SyncMetadata::where('tenant_id', $tenantId)
            ->when($request->entity_type, fn($q, $type) => $q->where('entity_type', $type))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $metadata,
        ]);
    }

    /**
     * PUT /api/sync/metadata
     * Upsert last_pull_at for an entity_type.
     * Body: { "entity_type": "patient", "last_pull_at": "2026-07-04T12:00:00Z" }
     */
    public function updateMetadata(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|max:50',
            'last_pull_at' => 'required|date',
        ]);

        $tenantId = TenantService::getCurrentTenantId();

        $metadata = SyncMetadata::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'entity_type' => $validated['entity_type'],
            ],
            [
                'id' => Str::uuid(),
                'last_pull_at' => $validated['last_pull_at'],
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $metadata,
        ]);
    }

    /**
     * DELETE /api/sync/metadata
     * Reset all metadata for the current tenant (on logout).
     */
    public function resetMetadata(): JsonResponse
    {
        $tenantId = TenantService::getCurrentTenantId();

        SyncMetadata::where('tenant_id', $tenantId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sync metadata reset successfully.',
        ]);
    }
}
```

---

## Subtask 2.4 — Register routes

Add inside the `auth:sanctum` + `InitializeTenant` middleware group in `routes/api.php`:

```php
// Sync metadata
Route::get('/sync/metadata', [SyncController::class, 'getMetadata']);
Route::put('/sync/metadata', [SyncController::class, 'updateMetadata']);
Route::delete('/sync/metadata', [SyncController::class, 'resetMetadata']);
```

---

## Entity types reference

These are the `entity_type` values the mobile will use:

| entity_type | Table | Scoped? |
|-------------|-------|---------|
| `patient` | patients | Yes |
| `appointment` | appointments | Yes |
| `treatment_session` | treatment_sessions | Yes |
| `session_treatment` | session_treatments | Yes |
| `patient_assessment` | patient_assessments | Yes |
| `invoice` | invoices | Yes |
| `invoice_item` | invoice_items | Yes |
| `payment` | payments | Yes |
| `prescription` | prescriptions | No (scoped via patient) |
| `expense` | expenses | Yes |
| `medication` | medications | Yes |
| `treatment_type` | treatment_types | Yes |
| `clinic_setting` | clinic_settings | Yes |
| `working_hour` | working_hours | Yes |
| `working_hour_range` | working_hour_ranges | Yes |
| `notification` | notifications | Yes |
| `user` | users | Yes |
| `doctor` | doctors | No (unscoped) |
| `whatsapp_message` | whatsapp_messages | Yes |

---

## Notes

- `sync_metadata` لا يحتاج إلى `MultiTenantTrait` لأنه يتم فلترة `tenant_id` يدويًا في الـ controller.
- `last_pull_at` يستخدم لتحديد أول sync (إن كان null → first sync).
- عند الـ logout، `resetMetadata()` يمسح كل البيانات → المرة القادمة تعتبر first sync.
