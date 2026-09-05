<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['in:present,absent,late'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tenantId = $this->user()?->tenant_id;
            $keys = array_keys((array) $this->input('statuses', []));

            if ($tenantId === null || $keys === []) {
                return;
            }

            $valid = Student::query()
                ->active()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $keys)
                ->pluck('id')
                ->all();

            $invalid = array_diff($keys, $valid);

            if ($invalid !== []) {
                $validator->errors()->add('statuses', 'يحتوي الطلب على طلاب غير مسجلين أو محذوفين في هذا الجامع');
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}
