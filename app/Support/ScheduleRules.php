<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Shared validation rules for schedule create (admin Blade + API v1) and the
 * weekly shift generator (one period / عدة أيام). All referenced records are
 * scoped to the acting mosque.
 */
final class ScheduleRules
{
    /** @return array<string, mixed> */
    public static function rules(?string $tenantId = null, bool $weekly = false): array
    {
        // مدير الجوامع داخل جامع: tenant_id فارغ ويُستمد من سياق الجامع الحالي.
        $tenantId ??= config('app.current_tenant_id');

        $rules = [
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->where('tenant_id', $tenantId)],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => [
                'nullable',
                Rule::exists('subjects', 'id')->where('tenant_id', $tenantId),
                'required_without:program_id',
            ],
            'teacher_id' => ['required', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'program_id' => ['nullable', 'uuid', Rule::exists('programs', 'id')->where('tenant_id', $tenantId)],
            'program_period_id' => ['nullable', 'uuid', Rule::exists('program_periods', 'id')->where('tenant_id', $tenantId)],
            'study_session_id' => ['nullable', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $tenantId)],
            'starts_at' => ['nullable', 'date_format:H:i', 'required_without:program_period_id'],
            'ends_at' => ['nullable', 'date_format:H:i', 'required_without:program_period_id'],
        ];

        if ($weekly) {
            $rules['days'] = ['required', 'array', 'min:1'];
            $rules['days.*'] = ['integer', 'min:0', 'max:6', 'distinct'];
        } else {
            $rules['day_of_week'] = ['required', 'integer', 'min:0', 'max:6'];
        }

        return $rules;
    }
}
