<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['in:present,absent,late'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
