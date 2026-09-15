<?php

namespace App\Models;

use App\Enums\QuranKhamsaItemStatus;
use App\Enums\QuranKhamsaReviewStatus;
use App\Traits\MultiTenantTrait;
use App\Traits\StudySessionScopedTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * «مراجعة 5»: رأس مراجعة يضم خمسات متعددة لطالب مع أستاذ ودوام.
 */
class QuranKhamsaReview extends Model
{
    use HasFactory, MultiTenantTrait, StudySessionScopedTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'teacher_id',
        'study_session_id',
        'assigned_by',
        'assigned_at',
        'due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date',
            'due_date' => 'date',
            'status' => QuranKhamsaReviewStatus::class,
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

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuranKhamsaReviewItem::class, 'review_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === QuranKhamsaReviewStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === QuranKhamsaReviewStatus::Cancelled;
    }

    /** @return array{total: int, completed: int, percentage: int} */
    public function progress(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $total = $items->count();
        $completed = $items
            ->filter(fn (QuranKhamsaReviewItem $item) => $item->status === QuranKhamsaItemStatus::Completed)
            ->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }

    public function pagesCount(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return (int) $items->sum(fn (QuranKhamsaReviewItem $item) => $item->pagesCount());
    }
}
