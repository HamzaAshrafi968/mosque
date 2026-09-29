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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Exam extends Model
{
    use FlushesTenantCache, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'subject_id',
        'study_session_id',
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

    /** الصفوف المستهدفة (عدة صفوف في اختبار واحد) — بدون فلتر الدوام النشط. */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'exam_classroom')
            ->withoutGlobalScope('study_session');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** الدوام المستهدف (فارغ = صفوف محددة أو اختبار قديم). */
    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
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
     * الامتحانات التي تستهدف الطالب:
     * - صفوف محددة (exam_classroom أو classroom_id القديم) مع احترام الشعبة، أو
     * - دوام كامل (بلا صفوف + study_session_id).
     * (الامتحان المشترك بين المساجد غير مدعوم — العزل بالجامع مفروض مسبقاً.)
     */
    public function scopeTargetsStudent(Builder $query, Student $student): Builder
    {
        return $query->where(function (Builder $target) use ($student) {
            $target
                ->where(function (Builder $classrooms) use ($student) {
                    $classrooms
                        ->where(function (Builder $scope) use ($student) {
                            $scope->where('exams.classroom_id', $student->classroom_id)
                                ->orWhereHas('classrooms', fn (Builder $q) => $q->whereKey($student->classroom_id));
                        })
                        ->where(function (Builder $section) use ($student) {
                            $section->whereNull('exams.section_id')
                                ->orWhere('exams.section_id', $student->section_id);
                        });
                })
                ->orWhere(function (Builder $shift) use ($student) {
                    $shift->whereNull('exams.classroom_id')
                        ->whereDoesntHave('classrooms')
                        ->whereNotNull('exams.study_session_id')
                        ->where('exams.study_session_id', $student->study_session_id);
                });
        });
    }

    /**
     * الامتحانات الظاهرة لطالب: منشورة وتستهدفه (صف/شعبة أو دوام).
     */
    public function scopeVisibleForStudent(Builder $query, Student $student): Builder
    {
        return $query->published()->targetsStudent($student);
    }

    /** الصفوف المستهدفة: exam_classroom وإن لم يوجد فالصف القديم classroom_id. */
    public function targetClassroomIds(): Collection
    {
        $ids = $this->classrooms()->pluck('classrooms.id');

        if ($ids->isEmpty() && $this->classroom_id) {
            return collect([$this->classroom_id]);
        }

        return $ids;
    }

    /** اختبار دوام كامل: بلا صفوف محددة ومرتبط بدوام. */
    public function isShiftWide(): bool
    {
        return $this->classroom_id === null
            && $this->study_session_id !== null
            && ! $this->classrooms()->exists();
    }

    /** وصف الفئة المستهدفة للعرض: «الصف الأول، الصف الثاني» أو «دوام الأول». */
    public function targetLabel(): string
    {
        $classrooms = $this->relationLoaded('classrooms')
            ? $this->classrooms
            : $this->classrooms()->orderBy('name')->get(['classrooms.id', 'classrooms.name']);

        if ($classrooms->isNotEmpty()) {
            $names = $classrooms->sortBy('name')->pluck('name')->implode('، ');

            if (! $this->section_id) {
                return $names;
            }

            $sectionName = $this->relationLoaded('section')
                ? $this->section?->name
                : Section::query()->whereKey($this->section_id)->value('name');

            return $sectionName ? $names.' — '.$sectionName : $names;
        }

        if ($this->study_session_id) {
            $sessionName = $this->relationLoaded('studySession')
                ? $this->studySession?->display_name
                : StudySession::query()->whereKey($this->study_session_id)->value('name');

            return $sessionName ? 'دوام '.$sessionName : 'دوام كامل';
        }

        return '—';
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
