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
 * تقييم شهري ضمن برنامج الإجازة — one historical record per student & month
 * (unique on tenant_id + student_id + month); months are never overwritten.
 */
class IjazahMonthlyEvaluation extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'month',
        'amount',
        'recited_portion',
        'result',
        'evaluated_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
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
