<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'weekly_lessons' => ['required', 'integer', 'min:1', 'max:50'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
