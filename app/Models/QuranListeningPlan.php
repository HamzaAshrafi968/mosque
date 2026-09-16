<?php

namespace App\Models;

use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningPlanStatus;
use App\Traits\MultiTenantTrait;
use App\Traits\StudySessionScopedTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * «خطة الاستماع»: أجزاء مرتبة بنطاق صفحات لكل جزء، تُفتح دفعةً دفعة،
 * ويُختبر كل ما فُتح منها حتى يُفتح ما بعده.
 */
class QuranListeningPlan extends Model
{
    use HasFactory, MultiTenantTrait, StudySessionScopedTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'teacher_id',
        'study_session_id',
        'created_by',
        'khamsa_review_id',
        'title',
        'gate_size',
        'status',
        'notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'gate_size' => 'integer',
            'status' => QuranListeningPlanStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withoutGlobalScope('study_session');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withoutGlobalScope('study_session');
    }

    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function khamsaReview(): BelongsTo
    {
        return $this->belongsTo(QuranKhamsaReview::class, 'khamsa_review_id')->withoutGlobalScope('study_session');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuranListeningPlanItem::class, 'plan_id')->orderBy('position');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(QuranListeningTest::class, 'plan_id')->orderByDesc('tested_at');
    }

    public function isActive(): bool
    {
        return $this->status === QuranListeningPlanStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->status === QuranListeningPlanStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === QuranListeningPlanStatus::Cancelled;
    }

    /** @return array{total: int, passed: int, percentage: int} */
    public function progress(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $total = $items->count();
        $passed = $items
            ->filter(fn (QuranListeningPlanItem $item) => $item->status === QuranListeningItemStatus::Passed)
            ->count();

        return [
            'total' => $total,
            'passed' => $passed,
            'percentage' => $total > 0 ? (int) round(($passed / $total) * 100) : 0,
        ];
    }

    public function pagesCount(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return (int) $items->sum(fn (QuranListeningPlanItem $item) => $item->pagesCount());
    }
}
