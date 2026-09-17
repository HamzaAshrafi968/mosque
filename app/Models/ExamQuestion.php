<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamQuestion extends Model
{
    use FlushesTenantCache, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'exam_id',
        'type',
        'text',
        'marks',
        'options',
        'correct_answer',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'marks' => 'decimal:2',
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'question_id');
    }

    /** @return array<int, string> */
    public function optionsList(): array
    {
        return array_values(array_filter((array) ($this->options ?? []), fn ($option) => $option !== null && $option !== ''));
    }

    /** الإجابات الصحيحة المتعددة (checkbox) كمصفوفة. */
    public function correctOptions(): array
    {
        if ($this->correct_answer === null || $this->correct_answer === '') {
            return [];
        }

        $decoded = json_decode($this->correct_answer, true);

        if (is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }

        return [(string) $this->correct_answer];
    }
}
