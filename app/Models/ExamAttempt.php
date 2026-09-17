<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use FlushesTenantCache, MultiTenantTrait, UuidTrait;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_GRADED = 'graded';

    protected $fillable = [
        'tenant_id',
        'exam_id',
        'student_id',
        'started_at',
        'submitted_at',
        'score',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'attempt_id');
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_GRADED], true);
    }

    /** نجح الطالب في هذه المحاولة؟ */
    public function passed(): bool
    {
        return (bool) $this->exam?->passed($this->score === null ? null : (float) $this->score);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_IN_PROGRESS => 'جارية',
            self::STATUS_SUBMITTED => 'بانتظار التصحيح',
            default => 'مصححة',
        };
    }
}
