<?php

namespace App\Models;

use App\Enums\QuranTasmeeResult;
use App\Enums\QuranTasmeeType;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * التسميع: a historical recitation record assigned by a teacher to a student.
 * Records are never overwritten; corrections create new rows or are audited.
 */
class QuranRecitationSession extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'teacher_id',
        'type',
        'date',
        'amount',
        'recited_portion',
        'result',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuranTasmeeType::class,
            'date' => 'date',
            'amount' => 'decimal:2',
            'result' => QuranTasmeeResult::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
