<?php

namespace App\Models;

use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningTestResult;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * جزء واحد داخل دفعة برنامج استماع: حالته (مقفل/متاح/تم الاستماع/يحتاج
 * إعادة/ناجح) وسجل الاستماع (متى ومن سجّله وثواني التشغيل).
 */
class QuranListeningProgramItem extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'program_id',
        'batch_id',
        'juz',
        'from_page',
        'to_page',
        'status',
        'listened_at',
        'listened_by',
        'listen_seconds',
        'quran_recitation_session_id',
        'attempts',
        'last_result',
        'passed_at',
        'passed_by',
    ];

    protected function casts(): array
    {
        return [
            'juz' => 'integer',
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

    public function program(): BelongsTo
    {
        return $this->belongsTo(QuranListeningProgram::class, 'program_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(QuranListeningProgramBatch::class, 'batch_id');
    }

    public function listenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'listened_by');
    }

    /** آخر جلسة تسميع غطّت الجزء (دورة التأهيلي/الإجازة). */
    public function quranRecitationSession(): BelongsTo
    {
        return $this->belongsTo(QuranRecitationSession::class, 'quran_recitation_session_id');
    }

    public function passedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passed_by');
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

    /** هل يمكن تسجيل الاستماع الآن؟ */
    public function canBeListened(): bool
    {
        return in_array($this->status, [QuranListeningItemStatus::Available, QuranListeningItemStatus::NeedsRepeat], true);
    }

    public function pagesCount(): int
    {
        return max(0, $this->to_page - $this->from_page + 1);
    }

    /** وصف مختصر: «الجزء 6 (صفحات 102–120)». */
    public function label(): string
    {
        return "الجزء {$this->juz} (صفحات {$this->from_page}–{$this->to_page})";
    }
}
