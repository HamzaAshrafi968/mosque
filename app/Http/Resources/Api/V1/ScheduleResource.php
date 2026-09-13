<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'classroom' => $this->whenLoaded('classroom', fn () => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ]),
            'section' => $this->whenLoaded('section', fn () => [
                'id' => $this->section->id,
                'name' => $this->section->name,
            ]),
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
            ] : null),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ]),
            'program' => $this->whenLoaded('program', fn () => $this->program ? [
                'id' => $this->program->id,
                'name' => $this->program->name,
                'color' => $this->program->color,
            ] : null),
            'program_period' => $this->whenLoaded('programPeriod', fn () => $this->programPeriod ? [
                'id' => $this->programPeriod->id,
                'name' => $this->programPeriod->name,
                'starts_at' => $this->programPeriod->starts_at ? substr($this->programPeriod->starts_at, 0, 5) : null,
                'ends_at' => $this->programPeriod->ends_at ? substr($this->programPeriod->ends_at, 0, 5) : null,
            ] : null),
            'study_session' => $this->whenLoaded('studySession', fn () => $this->studySession ? [
                'id' => $this->studySession->id,
                'name' => $this->studySession->name,
            ] : null),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
