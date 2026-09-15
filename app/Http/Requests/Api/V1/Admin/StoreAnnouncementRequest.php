<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\AudioUpload;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'required_without:audio'],
            'audience' => ['required', 'in:all,teachers,guardians,classroom'],
            'classroom_id' => ['nullable', 'required_if:audience,classroom', 'exists:classrooms,id'],
            'audio' => AudioUpload::rules(),
            'auto_delete' => ['nullable', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
