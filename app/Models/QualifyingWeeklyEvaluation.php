<?php

namespace App\Models;

use App\Enums\QuranEvaluationResult;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تقييم أسبوعي ضمن البرنامج التأهيلي — every week creates a new historical
 * record; previous weeks are never overwritten.
 */
class QualifyingWeeklyEvaluation extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'week_start',
        'week_end',
        'amount',
        'recited_portion',
        'result',
        'evaluated_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'amount' => 'decimal:2',
            'result' => QuranEvaluationResult::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'evaluated_by');
    }
}
