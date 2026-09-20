<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Enums\DeliveryMode;
use App\Enums\ExamKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
{
    public function rules(): array
    {
        $tenantId = config('app.current_tenant_id') ?: $this->user()?->tenant_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', Rule::enum(ExamKind::class)],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'study_session_id' => ['nullable', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $tenantId)],
            'classroom_id' => ['nullable', Rule::exists('classrooms', 'id')->where('tenant_id', $tenantId)],
            'classroom_ids' => ['nullable', 'array'],
            'classroom_ids.*' => ['uuid', Rule::exists('classrooms', 'id')->where('tenant_id', $tenantId)],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->where('tenant_id', $tenantId)],
            'exam_date' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'mode' => ['nullable', Rule::enum(DeliveryMode::class)],
            'total_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_marks' => ['nullable', 'integer', 'min:0', 'lte:total_marks'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
