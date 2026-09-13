<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validation rules for the optional Quran intake record captured when a
 * student is added: how much he memorized and the range he reached.
 * Reused by the admin web controller and the V1 API requests.
 */
final class QuranMemorizationRules
{
    public static function rules(Request $request): array
    {
        return [
            'memorized_juz' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'memorized_from_surah_id' => ['nullable', 'required_with:memorized_from_ayah', 'uuid', 'exists:quran_surahs,id'],
            'memorized_from_ayah' => [
                'nullable', 'required_with:memorized_from_surah_id', 'integer', 'min:1',
                Rule::exists('quran_ayahs', 'ayah_number')->where('surah_id', $request->input('memorized_from_surah_id')),
            ],
            'memorized_to_surah_id' => ['nullable', 'required_with:memorized_to_ayah', 'uuid', 'exists:quran_surahs,id'],
            'memorized_to_ayah' => [
                'nullable', 'required_with:memorized_to_surah_id', 'integer', 'min:1',
                Rule::exists('quran_ayahs', 'ayah_number')->where('surah_id', $request->input('memorized_to_surah_id')),
            ],
        ];
    }
}
