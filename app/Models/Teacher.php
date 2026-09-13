<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\HasAvatar;
use App\Traits\MultiTenantTrait;
use App\Traits\StudySessionScopedTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'is_active',
        'photo',
    ];

    protected function casts(): array
    {
        return [
            'hired_at' => 'date',
            'is_active' => 'boolean',
        ];
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
