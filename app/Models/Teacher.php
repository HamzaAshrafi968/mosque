<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\HasAvatar;
use App\Traits\MultiTenantTrait;
use App\Traits\StudySessionScopedTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Teacher extends Model
{
    use FlushesTenantCache, HasAvatar, HasFactory, MultiTenantTrait, StudySessionScopedTrait, UuidTrait;

    public const CUSTOM_FIELD_ENTITY = 'teacher';

    protected $fillable = [
        'tenant_id',
        'study_session_id',
        'user_id',
        'name',
        'gender',
        'phone',
        'email',
        'specialty',
        'hired_at',
        'monthly_salary',
        'is_active',
        'photo',
    ];

    protected function casts(): array
    {
        return [
            'hired_at' => 'date',
            'monthly_salary' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * الدوام الأساسي (أول اختيار) يبقى مربوطاً تلقائياً في الجدول الوسيط حتى
     * تظل البيانات القديمة (البذور/الاختبارات) متوافقة مع الدوامات المتعددة.
     */
    protected static function booted(): void
    {
        static::saved(function (Teacher $teacher) {
            if ($teacher->study_session_id !== null) {
                $teacher->studySessions()->syncWithoutDetaching([$teacher->study_session_id]);
            }
        });
    }

    /** كل دوامات الأستاذ (يمكن أن يكون في أكثر من دوام). */
    public function studySessions(): BelongsToMany
    {
        return $this->belongsToMany(StudySession::class, 'study_session_teacher')->withTimestamps();
    }

    /**
     * الأستاذ يظهر في الدوام إذا كان دوامه الأساسي أو ضمن دواماته المحددة.
     */
    public function applyStudySessionScope(Builder $builder, string $sessionId): void
    {
        $builder->where(function (Builder $query) use ($sessionId) {
            $query->where($this->getTable().'.study_session_id', $sessionId)
                ->orWhereHas('studySessions', fn (Builder $relation) => $relation->whereKey($sessionId));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(TeacherRating::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(TeacherCertificate::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function homeworks(): HasMany
    {
        return $this->hasMany(Homework::class);
    }

    /** Quran program records created/supervised by this teacher. */
    public function quranRecitationSessions(): HasMany
    {
        return $this->hasMany(QuranRecitationSession::class);
    }

    /** مراجعات الخمسات المكلَّف بها الأستاذ. */
    public function quranKhamsaReviews(): HasMany
    {
        return $this->hasMany(QuranKhamsaReview::class);
    }

    public function quranKhamsaReviewItems(): HasMany
    {
        return $this->hasMany(QuranKhamsaReviewItem::class);
    }

    public function qualifyingEvaluations(): HasMany
    {
        return $this->hasMany(QualifyingWeeklyEvaluation::class, 'evaluated_by');
    }

    public function ijazahEvaluations(): HasMany
    {
        return $this->hasMany(IjazahMonthlyEvaluation::class, 'evaluated_by');
    }

    /** Hafiz monthly exams supervised by this teacher. */
    public function supervisedExams(): HasMany
    {
        return $this->hasMany(HafizMonthlyExam::class, 'supervisor_id');
    }

    /** Faith meetings organized (supervised / co-run) by this teacher. */
    public function supervisedMeetings(): HasMany
    {
        return $this->hasMany(FaithMeeting::class, 'supervisor_id');
    }

    public function coRunMeetings(): HasMany
    {
        return $this->hasMany(FaithMeeting::class, 'teacher_id');
    }

    /** Recurring weekly work periods. */
    public function workHours(): HasMany
    {
        return $this->hasMany(TeacherWorkHour::class);
    }

    /** Explicit assignments to sections (source of truth for section scope). */
    public function sectionAssignments(): HasMany
    {
        return $this->hasMany(SectionTeacher::class);
    }

    /** Sections the teacher is actively assigned to. */
    public function assignedSections(): HasManyThrough
    {
        return $this->hasManyThrough(
            Section::class,
            SectionTeacher::class,
            'teacher_id',
            'id',
            'id',
            'section_id'
        )->where('section_teachers.status', 'active');
    }

    /** Sections reachable through the timetable (legacy/fallback scope). */
    public function scheduledSectionIds(): array
    {
        return $this->schedules()
            ->whereNotNull('section_id')
            ->pluck('section_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Section ids this teacher is allowed to manage: explicit assignments
     * take precedence, falling back to timetable rows for legacy data.
     */
    public function manageableSectionIds(): array
    {
        return $this->assignedSections()
            ->pluck('sections.id')
            ->merge($this->scheduledSectionIds())
            ->unique()
            ->values()
            ->all();
    }

    /** Custom field values (entity_id is a UUID, collision-free across tables). */
    public function customValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id');
    }

    /** Financial ledger rows for this teacher. */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'person_id')->where('person_type', 'teacher');
    }
}
