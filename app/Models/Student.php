<?php

namespace App\Models;

use App\Enums\SectionStudentStatus;
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
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use FlushesTenantCache, HasAvatar, HasFactory, MultiTenantTrait, StudySessionScopedTrait, UuidTrait;

    public const CUSTOM_FIELD_ENTITY = 'student';

    protected $fillable = [
        'tenant_id',
        'study_session_id',
        'classroom_id',
        'section_id',
        'user_id',
        'name',
        'gender',
        'birth_date',
        'guardian_name',
        'guardian_phone',
        'status',
        'notes',
        'photo',
        'memorized_juz',
        'memorized_from_surah_id',
        'memorized_from_ayah',
        'memorized_to_surah_id',
        'memorized_to_ayah',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'memorized_juz' => 'decimal:1',
            'memorized_from_ayah' => 'integer',
            'memorized_to_ayah' => 'integer',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** Portal login account (nullable — not every student has one). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Guardian links (parent_students). */
    public function guardianLinks(): HasMany
    {
        return $this->hasMany(ParentStudent::class);
    }

    /** Guardians connected to this student through parent_students. */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'parent_students', 'student_id', 'parent_id')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Attendance records captured through session-based attendance. */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** History-preserving section memberships. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(SectionStudent::class);
    }

    /** The currently active section membership, if any. */
    public function activeEnrollment()
    {
        return $this->hasOne(SectionStudent::class)
            ->where('status', SectionStudentStatus::Active)
            ->orderByDesc('created_at');
    }

    /** Custom field values (entity_id is a UUID, collision-free across tables). */
    public function customValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id');
    }

    /** Financial ledger rows for this student. */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'person_id')->where('person_type', 'student');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function rewardPoints(): HasMany
    {
        return $this->hasMany(RewardPoint::class);
    }

    public function quranReviewSessions(): HasMany
    {
        return $this->hasMany(QuranReviewSession::class);
    }

    /** سورة بداية ما حفظه الطالب قبل الالتحاق. */
    public function memorizedFromSurah(): BelongsTo
    {
        return $this->belongsTo(QuranSurah::class, 'memorized_from_surah_id');
    }

    /** السورة التي وصل إليها حفظ الطالب. */
    public function memorizedToSurah(): BelongsTo
    {
        return $this->belongsTo(QuranSurah::class, 'memorized_to_surah_id');
    }

    /** التسميع — historical recitation records (new & revision). */
    public function quranRecitationSessions(): HasMany
    {
        return $this->hasMany(QuranRecitationSession::class);
    }

    /** إتمام حفظ القرآن records (recorded + confirmed). */
    public function quranCompletions(): HasMany
    {
        return $this->hasMany(QuranCompletion::class);
    }

    /** Hafiz extension profile (exists once the completion is confirmed). */
    public function hafizProfile(): HasOne
    {
        return $this->hasOne(HafizProfile::class);
    }

    /** Qualifying / Ijazah program enrollments (history preserved). */
    public function programEnrollments(): HasMany
    {
        return $this->hasMany(ProgramEnrollment::class);
    }

    public function qualifyingWeeklyEvaluations(): HasMany
    {
        return $this->hasMany(QualifyingWeeklyEvaluation::class);
    }

    public function ijazahMonthlyEvaluations(): HasMany
    {
        return $this->hasMany(IjazahMonthlyEvaluation::class);
    }

    /** Weekly evaluations inside each ijazah month (4 per month). */
    public function ijazahWeeklyEvaluations(): HasMany
    {
        return $this->hasMany(IjazahWeeklyEvaluation::class);
    }

    /** Monthly hafiz exams (one historical row per month). */
    public function hafizMonthlyExams(): HasMany
    {
        return $this->hasMany(HafizMonthlyExam::class);
    }

    /** Faith-meeting attendance rows. */
    public function faithMeetingAttendances(): HasMany
    {
        return $this->hasMany(FaithMeetingStudent::class);
    }

    public function faithMeetings(): BelongsToMany
    {
        return $this->belongsToMany(FaithMeeting::class, 'faith_meeting_students', 'student_id', 'meeting_id')
            ->withPivot(['attendance_status', 'note'])
            ->withTimestamps();
    }

    /** Arabic label for the memorized range, e.g. "من سورة البقرة (آية 1) إلى سورة الكهف (آية 20)". */
    public function memorizedRangeLabel(): ?string
    {
        $from = $this->memorizedFromSurah;
        $to = $this->memorizedToSurah;

        if (! $from && ! $to) {
            return null;
        }

        $parts = [];

        if ($from) {
            $parts[] = 'من سورة '.$from->name_arabic.($this->memorized_from_ayah ? ' (آية '.$this->memorized_from_ayah.')' : '');
        }

        if ($to) {
            $parts[] = 'إلى سورة '.$to->name_arabic.($this->memorized_to_ayah ? ' (آية '.$this->memorized_to_ayah.')' : '');
        }

        return implode(' ', $parts);
    }

    public function totalPoints(): int
    {
        $earned = (clone $this->rewardPoints())
            ->where('type', 'earned')
            ->sum('points');

        $deducted = (clone $this->rewardPoints())
            ->where('type', 'deducted')
            ->sum('points');

        return (int) $earned - (int) $deducted;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('guardian_name', 'like', "%{$term}%")
                ->orWhere('guardian_phone', 'like', "%{$term}%");
        }));
    }
}
