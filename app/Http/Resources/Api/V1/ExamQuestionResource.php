<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamQuestionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'text' => $this->text,
            'marks' => (float) $this->marks,
            'options' => $this->optionsList(),
            'correct_answer' => $this->type?->value === 'checkbox'
                ? $this->correctOptions()
                : $this->correct_answer,
            'sort_order' => $this->sort_order,
        ];
    }
}
