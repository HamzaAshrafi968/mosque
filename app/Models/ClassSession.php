<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\StudySessionScopedTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * استثناء حصة في تاريخ محدد: إلغاء أو تأجيل. لا يوجد صف لكل أسبوع —
 * الحصة الأسبوعية تبقى في `schedules` وهذا الجدول يحفظ التغييرات فقط.
 */
class ClassSession extends Model
{
    use FlushesTenantCache, MultiTenantTrait, StudySessionScopedTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'section_id',
        'schedule_id',
        'study_session_id',
        'teacher_id',
        'date',
        'status',
        'reason',
        'postponed_date',
        'postponed_starts_at',
        'postponed_ends_at',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => SessionStatus::class,
            'postponed_date' => 'date',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** هل غُيّرت هذه الحصة (ملغاة أو مؤجلة)؟ */
    public function isChanged(): bool
    {
        return $this->status?->isActiveException() ?? false;
    }

    /** بداية الحصة الفعلية (الموعد الجديد عند التأجيل). */
    public function effectiveStartsAt(): ?string
    {
        return $this->status === SessionStatus::Postponed
            ? $this->postponed_starts_at
            : $this->schedule?->starts_at;
    }

    /** نهاية الحصة الفعلية (الموعد الجديد عند التأجيل). */
    public function effectiveEndsAt(): ?string
    {
        return $this->status === SessionStatus::Postponed
            ? $this->postponed_ends_at
            : $this->schedule?->ends_at;
    }

    /** التاريخ الفعلي (المؤجل أو الأصلي). */
    public function effectiveDate(): ?Carbon
    {
        return $this->status === SessionStatus::Postponed
            ? $this->postponed_date
            : $this->date;
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', today());
    }

    public function scopeActiveExceptions($query)
    {
        return $query->whereIn('status', [
            SessionStatus::Postponed->value,
            SessionStatus::Cancelled->value,
        ]);
    }
}
