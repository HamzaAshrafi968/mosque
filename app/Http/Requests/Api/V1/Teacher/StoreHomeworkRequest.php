<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Support\DocumentUpload;
use Illuminate\Foundation\Http\FormRequest;

class StoreHomeworkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'attachment' => ['nullable', ...DocumentUpload::rules(10240)],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
