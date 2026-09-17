<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
            ]),
            'started_at' => $this->started_at?->toDateTimeString(),
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'score' => $this->score === null ? null : (float) $this->score,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'passed' => $this->whenLoaded('exam', fn () => $this->isFinished() ? $this->passed() : null),
            'answers' => ExamAnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
