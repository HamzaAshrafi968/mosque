<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubmissionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'grade' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'feedback' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,graded'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
