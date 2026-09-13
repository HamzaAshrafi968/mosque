<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\QuranMemorizationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
        ], QuranMemorizationRules::rules($this));
    }

    public function authorize(): bool
    {
        return true;
    }
}
