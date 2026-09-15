<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'study_session_id' => $this->study_session_id,
            'study_session' => $this->whenLoaded('studySession', fn () => $this->studySession ? [
                'id' => $this->studySession->id,
                'name' => $this->studySession->name,
            ] : null),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'students_count' => $this->whenCounted('students'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
