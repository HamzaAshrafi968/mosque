<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuranReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'surah_id' => ['required', 'uuid', 'exists:quran_surahs,id'],
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'from_ayah' => ['required', 'integer', 'min:1'],
            'to_ayah' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'word_statuses' => ['required', 'array'],
            'word_statuses.*' => ['required', 'string', 'in:correct,incorrect,hesitation,tajweed_error,added,forgotten,unreviewed'],
            'word_notes' => ['nullable', 'array'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
