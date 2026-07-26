<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class QuranReviewSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'from_ayah' => $this->from_ayah,
            'to_ayah' => $this->to_ayah,
            'total_words' => $this->total_words,
            'correct_words' => $this->correct_words,
            'incorrect_words' => $this->incorrect_words,
            'hesitation_words' => $this->hesitation_words,
            'tajweed_error_words' => $this->tajweed_error_words,
            'added_words' => $this->added_words,
            'forgotten_words' => $this->forgotten_words,
            'mastery_percentage' => $this->mastery_percentage,
            'notes' => $this->notes,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ]),
            'surah' => $this->whenLoaded('surah', fn () => [
                'id' => $this->surah->id,
                'name_arabic' => $this->surah->name_arabic,
            ]),
            'words' => QuranReviewWordResource::collection($this->whenLoaded('words')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
