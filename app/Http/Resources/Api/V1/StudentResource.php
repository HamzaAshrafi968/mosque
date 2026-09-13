<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->toDateString(),
            'guardian_name' => $this->guardian_name,
            'guardian_phone' => $this->guardian_phone,
            'status' => $this->status,
            'notes' => $this->notes,
            'memorized_juz' => $this->memorized_juz !== null ? (float) $this->memorized_juz : null,
            'memorized_from_surah_id' => $this->memorized_from_surah_id,
            'memorized_from_ayah' => $this->memorized_from_ayah,
            'memorized_to_surah_id' => $this->memorized_to_surah_id,
            'memorized_to_ayah' => $this->memorized_to_ayah,
            'memorized_range' => $this->memorizedRangeLabel(),
            'classroom' => $this->whenLoaded('classroom', fn () => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ]),
            'section' => $this->whenLoaded('section', fn () => [
                'id' => $this->section->id,
                'name' => $this->section->name,
            ]),
            'grades' => GradeResource::collection($this->whenLoaded('grades')),
            'attendance_stats' => $this->when(
                isset($this->resource->attendance_stats),
                $this->resource->attendance_stats
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
