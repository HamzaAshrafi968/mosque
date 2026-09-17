<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    use FlushesTenantCache, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'attempt_id',
        'question_id',
        'answer_text',
        'selected_options',
        'is_correct',
        'marks_awarded',
    ];

    protected function casts(): array
    {
        return [
            'selected_options' => 'array',
            'is_correct' => 'boolean',
            'marks_awarded' => 'decimal:2',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'question_id');
    }

    /** @return array<int, string> */
    public function selectedList(): array
    {
        return array_values(array_map('strval', (array) ($this->selected_options ?? [])));
    }
}
