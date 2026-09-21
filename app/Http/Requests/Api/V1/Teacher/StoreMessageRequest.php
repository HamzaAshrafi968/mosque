<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // المستقبل يجب أن يكون من نفس الجامع (exists الخام يتجاوز نطاق العزل).
            'recipient_id' => [
                'required',
                Rule::exists('users', 'id')->where('tenant_id', config('app.current_tenant_id')),
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
