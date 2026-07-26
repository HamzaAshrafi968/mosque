<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'score' => $this->score,
            'status' => $this->status,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
            ]),
            'exam' => $this->whenLoaded('exam', fn () => [
                'id' => $this->exam->id,
                'title' => $this->exam->title,
                'total_marks' => $this->exam->total_marks,
                'exam_date' => $this->exam->exam_date?->toDateString(),
                'subject' => $this->when($this->exam->relationLoaded('subject'), fn () => [
                    'id' => $this->exam->subject->id,
                    'name' => $this->exam->subject->name,
                ]),
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
