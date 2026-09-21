<?php

namespace App\Services;

use App\Enums\HafizExamStatus;
use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranCompletionStatus;
use App\Enums\QuranReading;
use App\Models\HafizMonthlyExam;
use App\Models\HafizProfile;
use App\Models\ProgramEnrollment;
use App\Models\QuranCompletion;
use App\Models\Student;
use App\Models\User;
use App\Support\QuranProgramSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Automatic workflow of the Quran journey (spec §18):
 *
 *   Confirmed Quran completion
 *       → student becomes Hafiz (profile row)
 *       → automatic Qualifying enrollment
 *       → monthly hafiz exam rows
 *   Qualifying completed (≥ N passing weeks)
 *       → automatic Ijazah enrollment
 *   Ijazah completed (≥ M passing months)
 *       → journey complete
 *
 * Every transition is a confirmed business event, audited, and idempotent.
 */
class QuranProgramService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QuranSettingsService $settings,
        private readonly NotificationService $notifications,
    ) {}

    /** Record a completion request (pending confirmation). */
    public function recordCompletion(Student $student, ?string $completedAt, ?string $notes, ?User $actor = null): QuranCompletion
    {
        $completion = QuranCompletion::create([
            'student_id' => $student->id,
            'status' => QuranCompletionStatus::Pending,
            'completed_at' => $completedAt ?: Carbon::today(),
            'notes' => $notes,
        ]);

        $this->audit->logModel('quran.completion.recorded', $completion, actor: $actor);

        return $completion;
    }

    /**
     * Confirm the completion: promote the student to Hafiz and run the
     * automatic program enrollment rules. Idempotent once confirmed.
     */
    public function confirmCompletion(QuranCompletion $completion, ?User $actor = null): void
    {
        if ($completion->isConfirmed()) {
            return;
        }

        $student = $completion->student;

        // ملف حافظ موجود مسبقاً (بيانات قديمة/إصلاح يدوي) لا يمنع التأكيد —
        // الشرط الفعلي هو ألا يكون هناك إتمام مؤكد آخر لنفس الطالب.
        $alreadyConfirmed = QuranCompletion::where('student_id', $student->id)
            ->where('status', QuranCompletionStatus::Confirmed)
            ->where('id', '!=', $completion->id)
            ->exists();

        if ($alreadyConfirmed) {
            throw ValidationException::withMessages([
                'student_id' => ['تم تأكيد إتمام حفظ القرآن لهذا الطالب مسبقاً'],
            ]);
        }

        $startedAt = $completion->completed_at?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');

        DB::transaction(function () use ($completion, $actor, $startedAt) {
            $completion->update([
                'status' => QuranCompletionStatus::Confirmed,
                'confirmed_by' => $actor?->id,
                'confirmed_at' => now(),
            ]);

            $this->audit->logModel('quran.completion.confirmed', $completion, actor: $actor);

            $profile = HafizProfile::firstOrCreate(
                ['student_id' => $completion->student_id],
                ['riwayah' => 'حفص عن عاصم']
            );

            if ($profile->wasRecentlyCreated) {
                $this->audit->logModel('hafiz.profile.created', $profile, actor: $actor);
            }

            $this->enrollIfAbsent(ProgramType::Qualifying, $completion->student_id, $startedAt, $actor);

            $this->ensureMonthlyExamRows(
                collect([$completion->student_id]),
                QuranProgramSettings::monthOf(Carbon::today()),
                $actor
            );
        });
    }

    /**
     * Complete the qualifying program when the configured requirements are
     * met, then enroll the student automatically in the Ijazah program.
     */
    public function completeQualifying(ProgramEnrollment $enrollment, ?User $actor = null): void
    {
        $this->assertEnrollment($enrollment, ProgramType::Qualifying);

        $passedWeeks = $enrollment->student->qualifyingWeeklyEvaluations()
            ->where('result', 'passed')
            ->count();

        if ($passedWeeks < QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS) {
            throw ValidationException::withMessages([
                'program' => sprintf(
                    'لا يمكن إنهاء البرنامج التأهيلي قبل اجتياز %d تقييمات أسبوعية على الأقل (اجتاز %d حالياً)',
                    QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS,
                    $passedWeeks
                ),
            ]);
        }

        $this->completeEnrollment($enrollment, $actor);

        $this->enrollIfAbsent(ProgramType::Ijazah, $enrollment->student_id, Carbon::today()->format('Y-m-d'), $actor);
    }

    /**
     * Complete the ijazah program when the configured requirements are met.
     */
    public function completeIjazah(ProgramEnrollment $enrollment, ?User $actor = null): void
    {
        $this->assertEnrollment($enrollment, ProgramType::Ijazah);

        $passedMonths = $enrollment->student->ijazahMonthlyEvaluations()
            ->where('result', 'passed')
            ->count();

        if ($passedMonths < QuranProgramSettings::IJAZAH_MIN_PASSED_MONTHS) {
            throw ValidationException::withMessages([
                'program' => sprintf(
                    'لا يمكن إنهاء برنامج الإجازة قبل اجتياز %d تقييم شهري واحد على الأقل',
                    QuranProgramSettings::IJAZAH_MIN_PASSED_MONTHS
                ),
            ]);
        }

        $this->completeEnrollment($enrollment, $actor);

        $this->inviteToReadings($enrollment->student);
    }

    /**
     * إنهاء التحاق دورة استماع (التأهيلي/الإجازة) عند اجتياز اختبار 1–30 —
     * بلا شروط التقييمات الأسبوعية/الشهرية (تلك تبقى للشاشات اليدوية).
     */
    public function completeCycleEnrollment(ProgramEnrollment $enrollment, ?User $actor = null): void
    {
        if (! $enrollment->isActive()) {
            return;
        }

        $this->completeEnrollment($enrollment, $actor);
    }

    /**
     * Ensure a hafiz profile exists for a student (legacy/manual repair).
     */
    public function ensureHafizProfile(Student $student, ?User $actor = null): HafizProfile
    {
        $profile = HafizProfile::firstOrCreate(
            ['student_id' => $student->id],
            ['riwayah' => 'حفص عن عاصم']
        );

        if ($profile->wasRecentlyCreated) {
            $this->audit->logModel('hafiz.profile.created', $profile, actor: $actor);
        }

        return $profile;
    }

    /**
     * Create not_tested monthly exam rows for the given month for every
     * hafiz (confirmed completion) that does not have one yet.
     *
     * @param  Collection<int, string>|array<int, string>  $studentIds
     * @return Collection<int, HafizMonthlyExam> the rows for the month
     */
    public function ensureMonthlyExamRows(Collection|array $studentIds, string $month, ?User $actor = null): Collection
    {
        $studentIds = collect($studentIds)->unique()->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        $studentIds = Student::query()
            ->whereIn('id', $studentIds)
            ->whereHas('quranCompletions', fn ($q) => $q->where('status', QuranCompletionStatus::Confirmed))
            ->pluck('id');

        $existing = HafizMonthlyExam::query()
            ->where('month', $month)
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id');

        $missing = $studentIds->diff($existing);

        foreach ($missing as $studentId) {
            $exam = HafizMonthlyExam::create([
                'student_id' => $studentId,
                'month' => $month,
                'exam_status' => HafizExamStatus::NotTested,
            ]);

            $this->audit->logModel('hafiz_exam.month_opened', $exam, actor: $actor);
        }

        return HafizMonthlyExam::query()
            ->where('month', $month)
            ->whereIn('student_id', $studentIds)
            ->with(['student:id,name', 'student.classroom:id,name', 'revisions'])
            ->orderBy('student_id')
            ->get();
    }

    /**
     * Aggregate the 12 monthly exam summaries of a year from existing rows only
     * (never creates rows). Optionally scoped to a supervisor.
     *
     * @return array<int, array{month: string, label: string, hafiz_count: int, evaluated: int, tested: int, not_tested: int, passed: int, failed: int}>
     */
    public function examYearSummaries(Collection|array $studentIds, int $year, ?string $supervisorId = null): array
    {
        $studentIds = collect($studentIds)->unique()->values();

        $query = HafizMonthlyExam::query()
            ->whereIn('student_id', $studentIds)
            ->where('month', 'like', $year.'-%');

        if ($supervisorId !== null) {
            $query->where(fn ($q) => $q->whereNull('supervisor_id')->orWhere('supervisor_id', $supervisorId));
        }

        $byMonth = $query->get(['month', 'exam_status'])->groupBy('month');
        $hafizCount = $studentIds->count();

        return collect(range(1, 12))->map(function (int $month) use ($year, $byMonth, $hafizCount) {
            $key = sprintf('%04d-%02d', $year, $month);
            $rows = $byMonth->get($key, collect());

            return [
                'month' => $key,
                'label' => QuranProgramSettings::monthLabel($key),
                'hafiz_count' => $hafizCount,
                'evaluated' => $rows->count(),
                'tested' => $rows->filter(fn (HafizMonthlyExam $row) => $row->exam_status->wasTested())->count(),
                'not_tested' => $rows->filter(fn (HafizMonthlyExam $row) => $row->exam_status->value === HafizExamStatus::NotTested->value)->count(),
                'passed' => $rows->filter(fn (HafizMonthlyExam $row) => $row->exam_status->value === HafizExamStatus::Passed->value)->count(),
                'failed' => $rows->filter(fn (HafizMonthlyExam $row) => $row->exam_status->value === HafizExamStatus::Failed->value)->count(),
            ];
        })->all();
    }

    /**
     * Record a monthly exam grade. The status is derived from the mosque's
     * unified pass mark (settings → quran.minimum_passing_percentage):
     * grade >= pass mark → passed, otherwise failed.
     */
    public function gradeMonthlyExam(HafizMonthlyExam $exam, array $data, ?User $actor = null): HafizMonthlyExam
    {
        $before = $exam->getAttributes();

        $exam->update([
            'exam_status' => (float) $data['grade'] >= $this->settings->minimumPassingPercentage()
                ? HafizExamStatus::Passed
                : HafizExamStatus::Failed,
            'grade' => $data['grade'],
            'supervisor_id' => $data['supervisor_id'] ?? $exam->supervisor_id,
            'exam_date' => $data['exam_date'] ?? Carbon::today(),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('hafiz_exam.graded', $exam, $before, actor: $actor);

        return $exam;
    }

    /** Latest active qualifying/ijazah/readings enrollment of the student. */
    public function activeEnrollment(Student $student, ProgramType $type, ?QuranReading $reading = null): ?ProgramEnrollment
    {
        return ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', $type)
            ->when($reading, fn ($query) => $query->where('reading', $reading))
            ->where('status', ProgramEnrollmentStatus::Active)
            ->latest()
            ->first();
    }

    /**
     * هل أتمّ الطالب برنامج الإجازة؟ — شرط دخول برنامج القراءات (المرحلة
     * المتقدمة الاختيارية). يغطي المسارين: الإنهاء اليدوي (completeIjazah)
     * والإنهاء التلقائي عند اجتياز اختبار 1–30
     * (QuranProgramBatchService::completeCycle).
     */
    public function hasCompletedIjazah(Student $student): bool
    {
        return ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Completed)
            ->exists();
    }

    /**
     * الفلترة الجماعية للمؤهلين لبرنامج القراءات (استعلام واحد — بلا N+1).
     *
     * @param  Collection<int, string>|array<int, string>  $studentIds
     * @return Collection<int, string>
     */
    public function completedIjazahStudentIds(Collection|array $studentIds): Collection
    {
        $studentIds = collect($studentIds)->unique()->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        return ProgramEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Completed)
            ->pluck('student_id')
            ->unique()
            ->values();
    }

    /**
     * فرض البوابة التراتبية: لا تسجيل قراءات قبل إتمام الإجازة.
     */
    public function assertReadingsEligible(Student $student, string $field = 'student_id'): void
    {
        if ($this->hasCompletedIjazah($student)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => ['لا يمكن التسجيل في برنامج القراءات قبل إتمام برنامج الإجازة — القراءات مرحلة متقدمة اختيارية تأتي بعد الإجازة'],
        ]);
    }

    /**
     * Ensure an active enrollment exists for the student and return it
     * (existing or newly created) — idempotent. Used by the automatic
     * transitions and by the listening programs (إجازة/تأهيلي/قراءات).
     *
     * The reading scopes the uniqueness for برنامج القراءات so a student can
     * hold several parallel readings; it stays null for qualifying/ijazah.
     */
    public function enrollIfAbsent(ProgramType $type, string $studentId, ?string $startedAt = null, ?User $actor = null, ?QuranReading $reading = null): ProgramEnrollment
    {
        $existing = ProgramEnrollment::query()
            ->where('student_id', $studentId)
            ->where('program_type', $type)
            ->when($reading, fn ($query) => $query->where('reading', $reading))
            ->where('status', ProgramEnrollmentStatus::Active)
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $enrollment = ProgramEnrollment::create([
            'student_id' => $studentId,
            'program_type' => $type,
            'reading' => $reading,
            'started_at' => $startedAt ?? Carbon::today()->format('Y-m-d'),
            'status' => ProgramEnrollmentStatus::Active,
        ]);

        $this->audit->logModel('program.enrollment.created', $enrollment, actor: $actor);

        return $enrollment;
    }

    /**
     * دعوة اختيارية لبرنامج القراءات بعد إتمام الإجازة: الطالب يسجّل أو لا
     * من «برامجي» (لا تسجيل تلقائي — المرحلة اختيارية).
     */
    public function inviteToReadings(Student $student): void
    {
        $this->notifications->notifyStudentCircle(
            $student,
            'أتممت برنامج الإجازة — القراءات مرحلة متقدمة اختيارية',
            'ما شاء الله! أتممت برنامج الإجازة. يمكنك الآن — إن رغبت — التسجيل في برنامج القراءات (القراءات العشر) من «برامجي». التسجيل اختياري بالكامل.',
            route('student.quran-programs.index'),
        );
    }

    private function completeEnrollment(ProgramEnrollment $enrollment, ?User $actor): void
    {
        $type = $enrollment->program_type;

        $enrollment->update([
            'status' => ProgramEnrollmentStatus::Completed,
            'completed_at' => Carbon::today(),
            'completed_by' => $actor?->id,
        ]);

        $code = match ($type) {
            ProgramType::Qualifying => 'qualifying.completed',
            ProgramType::Ijazah => 'ijazah.completed',
            ProgramType::Readings => 'readings.completed',
        };

        $this->audit->logModel($code, $enrollment, actor: $actor);
    }

    private function assertEnrollment(ProgramEnrollment $enrollment, ProgramType $type): void
    {
        if ($enrollment->program_type !== $type || ! $enrollment->isActive()) {
            throw ValidationException::withMessages(['program' => ['بيانات الالتحاق غير صالحة']]);
        }
    }
}
