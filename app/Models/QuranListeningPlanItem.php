<?php

namespace App\Models;

use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningItemType;
use App\Enums\QuranListeningTestResult;
use App\Support\QuranJuzMap;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * جزء واحد داخل «خطة الاستماع»: نطاق صفحات داخل حدود الجزء + حالته في الدفعة.
 * نوعه إما «جديد» (نطاق صفحات يحدده الأستاذ) أو «مراجعة 5» (خمسة كاملة
 * مربوطة بمراجعة 5 ومنجَزة معها).
 */
class QuranListeningPlanItem extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'position',
        'juz',
        'type',
        'khamsa',
        'from_page',
        'to_page',
        'status',
        'listened_at',
        'listened_by',
        'listen_seconds',
        'attempts',
        'last_result',
        'passed_at',
        'passed_by',
        'khamsa_review_item_id',
        'quran_review_session_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'juz' => 'integer',
            'type' => QuranListeningItemType::class,
            'khamsa' => 'integer',
            'from_page' => 'integer',
            'to_page' => 'integer',
            'status' => QuranListeningItemStatus::class,
            'listened_at' => 'datetime',
            'listen_seconds' => 'integer',
            'attempts' => 'integer',
            'last_result' => QuranListeningTestResult::class,
            'passed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(QuranListeningPlan::class, 'plan_id')->withoutGlobalScope('study_session');
    }

    public function listenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'listened_by');
    }

    public function passedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passed_by');
    }

    public function khamsaReviewItem(): BelongsTo
    {
        return $this->belongsTo(QuranKhamsaReviewItem::class, 'khamsa_review_item_id');
    }

    public function listeningSession(): BelongsTo
    {
        return $this->belongsTo(QuranReviewSession::class, 'quran_review_session_id');
    }

    public function isReview(): bool
    {
        return $this->type === QuranListeningItemType::Review;
    }

    public function isLocked(): bool
    {
        return $this->status === QuranListeningItemStatus::Locked;
    }

    public function isAvailable(): bool
    {
        return $this->status === QuranListeningItemStatus::Available;
    }

    public function isListened(): bool
    {
        return $this->status === QuranListeningItemStatus::Listened;
    }

    public function isNeedsRepeat(): bool
    {
        return $this->status === QuranListeningItemStatus::NeedsRepeat;
    }

    public function isPassed(): bool
    {
        return $this->status === QuranListeningItemStatus::Passed;
    }

    /** هل يمكن للطالب تسجيل الاستماع الآن؟ */
    public function canBeListened(): bool
    {
        return in_array($this->status, [QuranListeningItemStatus::Available, QuranListeningItemStatus::NeedsRepeat], true);
    }

    public function pagesCount(): int
    {
        return max(0, $this->to_page - $this->from_page + 1);
    }

    /** وصف مختصر: «الجزء ١ (صفحات ١٥–٢١)» أو «الجزء ١ — الخمسة ٢ (صفحات ٦–١٠)». */
    public function label(): string
    {
        if ($this->isReview() && $this->khamsa >= 1) {
            return QuranJuzMap::khamsaLabel($this->juz, $this->khamsa);
        }

        return "الجزء {$this->juz} (صفحات {$this->from_page}–{$this->to_page})";
    }
}
