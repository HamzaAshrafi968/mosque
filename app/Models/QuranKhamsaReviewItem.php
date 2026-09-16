<?php

namespace App\Models;

use App\Enums\QuranKhamsaItemStatus;
use App\Enums\QuranTasmeeResult;
use App\Support\QuranJuzMap;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * خمسة واحدة داخل «مراجعة 5»: جزء + رقم الخمسة + نطاق الصفحات + الحالة.
 */
class QuranKhamsaReviewItem extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'review_id',
        'teacher_id',
        'juz',
        'khamsa',
        'from_page',
        'to_page',
        'status',
        'result',
        'completed_at',
        'completed_by',
        'quran_review_session_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'juz' => 'integer',
            'khamsa' => 'integer',
            'from_page' => 'integer',
            'to_page' => 'integer',
            'status' => QuranKhamsaItemStatus::class,
            'result' => QuranTasmeeResult::class,
            'completed_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(QuranKhamsaReview::class, 'review_id')->withoutGlobalScope('study_session');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withoutGlobalScope('study_session');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function quranReviewSession(): BelongsTo
    {
        return $this->belongsTo(QuranReviewSession::class, 'quran_review_session_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === QuranKhamsaItemStatus::Completed;
    }

    public function pagesCount(): int
    {
        return max(0, $this->to_page - $this->from_page + 1);
    }

    public function label(): string
    {
        return QuranJuzMap::khamsaLabel($this->juz, $this->khamsa);
    }
}
