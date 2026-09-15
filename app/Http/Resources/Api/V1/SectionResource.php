<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'classroom_id' => $this->classroom_id,
            'study_session_id' => $this->study_session_id,
            'study_session' => $this->whenLoaded('studySession', fn () => $this->studySession ? [
                'id' => $this->studySession->id,
                'name' => $this->studySession->name,
            ] : null),
        ];
    }
}
