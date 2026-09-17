<?php

namespace App\Models;

use App\Enums\DeliveryMode;
use App\Enums\ExamKind;
use App\Enums\ExamStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use FlushesTenantCache, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'subject_id',
        'classroom_id',
        'section_id',
        'teacher_id',
        'title',
        'kind',
        'exam_date',
        'duration_minutes',
        'mode',
        'status',
        'published_at',
        'attachment_key',
        'attachment_name',
        'total_marks',
        'pass_marks',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'kind' => ExamKind::class,
            'mode' => DeliveryMode::class,
            'status' => ExamStatus::class,
            'published_at' => 'datetime',
            'duration_minutes' => 'integer',
            'total_marks' => 'integer',
            'pass_marks' => 'integer',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('sort_order')->orderBy('created_at');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ExamStatus::Published);
    }

    /**
     * الامتحانات الظاهرة لطالب: منشورة وتخص صفه/شعبته.
     * (الامتحان المشترك بين الجوامع غير مدعوم — العزل بالجامع مفروض مسبقاً.)
     */
    public function scopeVisibleForStudent(Builder $query, Student $student): Builder
    {
        return $query->published()
            ->where('classroom_id', $student->classroom_id)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('section_id')
                ->orWhere('section_id', $student->section_id));
    }

    /** امتحان إلكتروني بأسئلة داخل النظام. */
    public function isElectronic(): bool
    {
        return $this->mode?->isElectronic() ?? false;
    }

    public function hasQuestions(): bool
    {
        return $this->questions()->exists();
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_key !== null;
    }

    /** هل يمكن تعديل الامتحان وأسئلته؟ (لا بعد وجود أي محاولة) */
    public function canEdit(): bool
    {
        return ! $this->attempts()->exists();
    }

    /** مجموع علامات الأسئلة (للتحقق من مطابقة العلامة الكلية). */
    public function questionsTotalMarks(): float
    {
        return round((float) $this->questions()->sum('marks'), 2);
    }

    /** الثواني المتبقية لمحاولة جارية (null إن لم توجد مدة أو لم تبدأ). */
    public function remainingSeconds(ExamAttempt $attempt): ?int
    {
        if ($this->duration_minutes === null || $attempt->started_at === null) {
            return null;
        }

        $deadline = $attempt->started_at->copy()->addMinutes($this->duration_minutes);

        return max(0, now()->diffInSeconds($deadline, false));
    }

    /** النجاح في الامتحان حسب علامة النجاح (إن حُددت). */
    public function passed(?float $score): bool
    {
        if ($this->pass_marks === null || $score === null) {
            return false;
        }

        return $score >= (float) $this->pass_marks;
    }
}
