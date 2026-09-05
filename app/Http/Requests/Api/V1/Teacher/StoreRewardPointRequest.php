<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRewardPointRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'uuid',
                Rule::exists('students', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'points' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:earned,deducted'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
