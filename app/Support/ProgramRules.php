<?php

namespace App\Support;

use App\Enums\AttributeOptionSource;
use App\Enums\CustomFieldType;
use App\Enums\ScheduleProgramType;
use App\Models\Program;
use App\Services\ProgramService;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for program create/update (admin Blade + API v1).
 */
final class ProgramRules
{
    /** @return array<string, mixed> */
    public static function rules(?string $tenantId = null, ?Program $program = null): array
    {
        // مدير الجوامع داخل جامع: tenant_id فارغ ويُستمد من سياق الجامع الحالي.
        $tenantId ??= config('app.current_tenant_id');

        $fieldTypes = collect(CustomFieldType::cases())->pluck('value')->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('programs', 'code')->where('tenant_id', $tenantId)->ignore($program),
            ],
            'type' => ['required', Rule::in(collect(ScheduleProgramType::cases())->pluck('value')->all())],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:20', 'regex:/^#?[0-9a-fA-F]{3,8}$/'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'periods' => ['nullable', 'array'],
            'periods.*.id' => ['nullable', 'uuid'],
            'periods.*.name' => ['nullable', 'string', 'max:255'],
            'periods.*.starts_at' => ['nullable', 'date_format:H:i'],
            'periods.*.ends_at' => ['nullable', 'date_format:H:i'],
            'periods.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'periods.*.is_active' => ['nullable', 'boolean'],

            'attributes' => ['nullable', 'array'],
            'attributes.*.id' => ['nullable', 'uuid'],
            'attributes.*.name' => ['nullable', 'string', 'max:255'],
            'attributes.*.field_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
            'attributes.*.field_type' => ['nullable', Rule::in($fieldTypes)],
            'attributes.*.required' => ['nullable', 'boolean'],
            'attributes.*.options' => ['nullable', 'max:2000'],
            'attributes.*.options_source' => ['nullable', Rule::in(AttributeOptionSource::values())],
            'attributes.*.options_config' => ['nullable', 'array'],
            'attributes.*.options_config.student_status' => ['nullable', Rule::in(['all', 'active'])],
            'attributes.*.options_config.gender' => ['nullable', Rule::in(['all', 'male', 'female'])],
            'attributes.*.options_config.age_from' => ['nullable', 'integer', 'min:0', 'max:120'],
            'attributes.*.options_config.age_to' => ['nullable', 'integer', 'min:0', 'max:120'],
            'attributes.*.options_config.juz_from' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'attributes.*.options_config.juz_to' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'attributes.*.options_config.classroom_id' => [
                'nullable', 'uuid',
                Rule::exists('classrooms', 'id')->where('tenant_id', $tenantId),
            ],
            'attributes.*.options_config.label_mode' => ['nullable', Rule::in(ProgramService::STUDENT_LABEL_MODES)],
            'attributes.*.value' => ['nullable'],
            'attributes.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'attributes.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
