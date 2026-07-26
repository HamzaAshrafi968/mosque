<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class GetAyahsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'surah_id' => ['required', 'uuid', 'exists:quran_surahs,id'],
            'from_ayah' => ['required', 'integer', 'min:1'],
            'to_ayah' => ['required', 'integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
