<?php

namespace App\Models;

use App\Enums\HafizExamStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * الاختبار الشهري للحافظ — one historical row per hafiz & month.
 * exam_status: not_tested | tested | passed | failed (not_tested ≠ failed).
 */
class HafizMonthlyExam extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'month',
        'exam_status',
        'grade',
        'supervisor_id',
        'exam_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'exam_status' => HafizExamStatus::class,
            'grade' => 'decimal:2',
            'exam_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'supervisor_id');
    }

    /** Portions to repeat when the hafiz failed. */
    public function revisions(): HasMany
    {
        return $this->hasMany(HafizExamRevision::class, 'exam_id');
    }

    public function hasPendingRevisions(): bool
    {
        return $this->revisions()
            ->whereIn('status', ['pending', 'completed'])
            ->exists();
    }
}
