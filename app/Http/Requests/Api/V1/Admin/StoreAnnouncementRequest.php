<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience' => ['required', 'in:all,teachers,guardians,classroom'],
            'classroom_id' => ['nullable', 'required_if:audience,classroom', 'exists:classrooms,id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
