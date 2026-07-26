<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'status' => $this->status,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'classroom' => $this->when($this->student->relationLoaded('classroom'), fn () => [
                    'id' => $this->student->classroom->id,
                    'name' => $this->student->classroom->name,
                ]),
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
