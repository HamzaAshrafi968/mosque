<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class RewardPointResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'points' => $this->points,
            'reason' => $this->reason,
            'type' => $this->type,
            'notes' => $this->notes,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
            ]),
            'awarded_by' => $this->whenLoaded('awardedBy', fn () => [
                'id' => $this->awardedBy->id,
                'name' => $this->awardedBy->name,
            ]),
            'quran_review_session' => $this->whenLoaded('quranReviewSession', fn () => [
                'id' => $this->quranReviewSession->id,
                'surah' => $this->when($this->quranReviewSession->relationLoaded('surah'), fn () => [
                    'id' => $this->quranReviewSession->surah->id,
                    'name_arabic' => $this->quranReviewSession->surah->name_arabic,
                ]),
                'from_ayah' => $this->quranReviewSession->from_ayah,
                'to_ayah' => $this->quranReviewSession->to_ayah,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
