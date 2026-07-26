<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class QuranReviewWordResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'word_position' => $this->word_position,
            'word_text' => $this->word_text,
            'status' => $this->status,
            'error_type' => $this->error_type,
            'notes' => $this->notes,
            'ayah' => $this->whenLoaded('ayah', fn () => [
                'id' => $this->ayah->id,
                'ayah_number' => $this->ayah->ayah_number,
            ]),
            'review_session' => $this->whenLoaded('reviewSession', fn () => [
                'id' => $this->reviewSession->id,
                'date' => $this->reviewSession->date,
                'surah' => $this->when($this->reviewSession->relationLoaded('surah'), fn () => [
                    'id' => $this->reviewSession->surah->id,
                    'name_arabic' => $this->reviewSession->surah->name_arabic,
                ]),
            ]),
        ];
    }
}
