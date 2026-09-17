<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'kind' => $this->kind?->value,
            'kind_label' => $this->kind?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'mode' => $this->mode?->value,
            'mode_label' => $this->mode?->label(),
            'exam_date' => $this->exam_date?->toDateString(),
            'duration_minutes' => $this->duration_minutes,
            'total_marks' => $this->total_marks,
            'pass_marks' => $this->pass_marks,
            'published_at' => $this->published_at?->toDateTimeString(),
            'attachment_name' => $this->attachment_name,
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
            ]),
            'classroom' => $this->whenLoaded('classroom', fn () => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ]),
            'section' => $this->whenLoaded('section', fn () => [
                'id' => $this->section->id,
                'name' => $this->section->name,
            ]),
            'grades_count' => $this->whenCounted('grades'),
            'questions_count' => $this->whenCounted('questions'),
            'attempts_count' => $this->whenCounted('attempts'),
            'submitted_grades_count' => $this->whenCounted('grades', 'submitted_grades_count'),
            'approved_grades_count' => $this->whenCounted('grades', 'approved_grades_count'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
