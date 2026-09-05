<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomFieldEntityType;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $entityType = CustomFieldEntityType::tryFrom($request->input('entity_type', 'student'))
            ?? CustomFieldEntityType::Student;

        $fields = CustomField::query()
            ->where('entity_type', $entityType->value)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.custom-fields.index', [
            'entityType' => $entityType,
            'fields' => $fields,
            'fieldTypes' => CustomFieldType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, create: true);

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

        return redirect()->route('admin.custom-fields.index', ['entity_type' => $data['entity_type']])
            ->with('success', 'تم إنشاء الحقل المخصص');
    }

    public function update(Request $request, CustomField $customField): RedirectResponse
    {
        $data = $this->validated($request, create: false);
        $before = $customField->getAttributes();

        $customField->update([
            'name' => $data['name'],
            'required' => $data['required'] ?? false,
            'options' => $data['options'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->logModel('custom_field.updated', $customField, $before, actor: $request->user());

        return redirect()->route('admin.custom-fields.index', ['entity_type' => $customField->entity_type->value])
            ->with('success', 'تم تحديث الحقل المخصص');
    }

    public function destroy(Request $request, CustomField $customField): RedirectResponse
    {
        $this->audit->logModel('custom_field.deleted', $customField, actor: $request->user());
        $customField->delete();

        return redirect()->route('admin.custom-fields.index', ['entity_type' => $customField->entity_type->value])
            ->with('success', 'تم حذف الحقل المخصص');
    }

    private function validated(Request $request, bool $create): array
    {
        $entityTypes = collect(CustomFieldEntityType::cases())->pluck('value')->all();
        $fieldTypes = collect(CustomFieldType::cases())->pluck('value')->all();

        $rules = [
            'entity_type' => ['required', Rule::in($entityTypes)],
            'name' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::in($fieldTypes)],
            'required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'options' => ['nullable', 'string', 'max:2000'],
        ];

        if ($create) {
            $rules['field_key'] = [
                'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('custom_fields', 'field_key')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('entity_type', $request->input('entity_type')),
            ];
        }

        $data = $request->validate($rules);

        if (isset($data['options']) && $data['options'] !== '') {
            $options = collect(preg_split('/[\r\n,]+/', $data['options']))
                ->map(fn ($o) => trim($o))
                ->filter()
                ->values();

            if ($options->isEmpty()) {
                $options = null;
            }

            $data['options'] = $options?->all();
        } else {
            $data['options'] = null;
        }

        return $data;
    }
}
