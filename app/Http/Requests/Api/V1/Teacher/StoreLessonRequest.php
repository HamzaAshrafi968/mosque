<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'type' => ['required', 'in:file,video,link,presentation'],
            'file' => ['nullable', 'file', 'max:20480', 'required_if:type,file,presentation'],
            'url' => ['nullable', 'url', 'required_if:type,video,link'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
