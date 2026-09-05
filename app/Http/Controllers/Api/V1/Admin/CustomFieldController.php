<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CustomFieldEntityType;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\CustomField;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomFieldController extends BaseApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** List field definitions for an entity type (values optionally included). */
    public function index(Request $request): JsonResponse
    {
        $entityType = $request->input('entity_type', CustomFieldEntityType::Student->value);

        if (! in_array($entityType, [CustomFieldEntityType::Student->value, CustomFieldEntityType::Teacher->value], true)) {
            return $this->error('entity_type يجب أن يكون student أو teacher', 422);
        }

        $fields = CustomField::query()
            ->where('entity_type', $entityType)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CustomField $field) => [
                'id' => $field->id,
                'name' => $field->name,
                'field_key' => $field->field_key,
                'field_type' => $field->field_type->value,
                'required' => $field->required,
                'options' => $field->options ?? [],
                'sort_order' => $field->sort_order,
                'is_active' => $field->is_active,
            ]);

        return $this->success(['custom_fields' => $fields]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $tenantId = $request->user()->tenant_id;

        $field = CustomField::create([
            'tenant_id' => $tenantId,
            'entity_type' => $data['entity_type'],
            'name' => $data['name'],
            'field_key' => $data['field_key']
                ?: CustomFieldService::uniqueKey($data['entity_type'], $data['name'], $tenantId),
            'field_type' => $data['field_type'],
            'required' => $data['required'] ?? false,
            'options' => $data['options'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        $this->audit->logModel('custom_field.created', $field, actor: $request->user());

        return $this->created(['id' => $field->id, 'field_key' => $field->field_key], 'تم إنشاء الحقل المخصص');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $field = CustomField::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'required' => ['sometimes', 'boolean'],
            'options' => ['sometimes', 'nullable', 'array'],
            'options.*' => ['string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = $field->getAttributes();
        $field->update($data);

        $this->audit->logModel('custom_field.updated', $field, $before, actor: $request->user());

        return $this->success(message: 'تم تحديث الحقل المخصص');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $field = CustomField::findOrFail($id);
        $this->audit->logModel('custom_field.deleted', $field, actor: $request->user());
        $field->delete();

        return $this->success(message: 'تم حذف الحقل المخصص');
    }

    private function validated(Request $request): array
    {
        $entityTypes = collect(CustomFieldEntityType::cases())->pluck('value')->all();
        $fieldTypes = collect(CustomFieldType::cases())->pluck('value')->all();

        $data = $request->validate([
            'entity_type' => ['required', Rule::in($entityTypes)],
            'name' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::in($fieldTypes)],
            'field_key' => [
                'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('custom_fields', 'field_key')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('entity_type', $request->input('entity_type')),
            ],
            'required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($data['field_type'] === CustomFieldType::Select->value || $data['field_type'] === CustomFieldType::Multiselect->value) {
            $data['options'] = array_values(array_unique(array_filter(array_map('trim', $data['options'] ?? []))));
        } else {
            $data['options'] = null;
        }

        return $data;
    }
}
