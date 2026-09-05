<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Models\QuranSurah;
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $surah = QuranSurah::find($this->input('surah_id'));
            $from = (int) $this->input('from_ayah');
            $to = (int) $this->input('to_ayah');

            if (! $surah) {
                return;
            }

            if ($from < 1 || $from > $surah->num_ayahs) {
                $validator->errors()->add('from_ayah', 'رقم الآية خارج حدود السورة');
            }

            if ($to > $surah->num_ayahs || $to < $from) {
                $validator->errors()->add('to_ayah', 'نطاق الآيات يجب أن يكون ضمن حدود السورة وبترتيب صحيح');
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}
