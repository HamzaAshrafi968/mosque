<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamAnswerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->question_id,
            'answer_text' => $this->answer_text,
            'selected_options' => $this->selectedList(),
            'is_correct' => $this->is_correct,
            'marks_awarded' => $this->marks_awarded === null ? null : (float) $this->marks_awarded,
        ];
    }
}
