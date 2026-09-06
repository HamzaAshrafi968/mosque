<?php

namespace App\Services;

use App\Enums\HafizExamStatus;
use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranCompletionStatus;
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
    public function __construct(private readonly AuditLogger $audit) {}

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

        $alreadyHafiz = HafizProfile::where('student_id', $student->id)->exists()
            || QuranCompletion::where('student_id', $student->id)
                ->where('status', QuranCompletionStatus::Confirmed)
                ->where('id', '!=', $completion->id)
                ->exists();

        if ($alreadyHafiz) {
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

            $this->enroll(ProgramType::Qualifying, $completion->student_id, $startedAt, $actor);

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

        $this->enroll(ProgramType::Ijazah, $enrollment->student_id, Carbon::today()->format('Y-m-d'), $actor);
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
     * Record a monthly exam grade. The status is derived from the configured
     * pass mark: grade >= HAFIZ_EXAM_PASS_MARK → passed, otherwise failed.
     */
    public function gradeMonthlyExam(HafizMonthlyExam $exam, array $data, ?User $actor = null): HafizMonthlyExam
    {
        $before = $exam->getAttributes();

        $exam->update([
            'exam_status' => (float) $data['grade'] >= QuranProgramSettings::HAFIZ_EXAM_PASS_MARK
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

    /** Latest active qualifying/ijazah enrollment of the student. */
    public function activeEnrollment(Student $student, ProgramType $type): ?ProgramEnrollment
    {
        return ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', $type)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->latest()
            ->first();
    }

    private function enroll(ProgramType $type, string $studentId, string $startedAt, ?User $actor): void
    {
        $existing = ProgramEnrollment::query()
            ->where('student_id', $studentId)
            ->where('program_type', $type)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->exists();

        if ($existing) {
            return;
        }

        $enrollment = ProgramEnrollment::create([
            'student_id' => $studentId,
            'program_type' => $type,
            'started_at' => $startedAt,
            'status' => ProgramEnrollmentStatus::Active,
        ]);

        $this->audit->logModel('program.enrollment.created', $enrollment, actor: $actor);
    }

    private function completeEnrollment(ProgramEnrollment $enrollment, ?User $actor): void
    {
        $type = $enrollment->program_type;

        $enrollment->update([
            'status' => ProgramEnrollmentStatus::Completed,
            'completed_at' => Carbon::today(),
            'completed_by' => $actor?->id,
        ]);

        $this->audit->logModel($type === ProgramType::Qualifying
            ? 'qualifying.completed'
            : 'ijazah.completed', $enrollment, actor: $actor);
    }

    private function assertEnrollment(ProgramEnrollment $enrollment, ProgramType $type): void
    {
        if ($enrollment->program_type !== $type || ! $enrollment->isActive()) {
            throw ValidationException::withMessages(['program' => ['بيانات الالتحاق غير صالحة']]);
        }
    }
}
