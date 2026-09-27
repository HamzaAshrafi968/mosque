<?php

namespace App\Services;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranListeningProgramStatus;
use App\Models\ProgramEnrollment;
use App\Models\QuranCompletion;
use App\Models\QuranListeningProgram;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Collection;

/**
 * Computes the "Quran Journey" of a student (spec §11/§12):
 *
 *   Memorization → Quran Completed → Hafiz → Qualifying (weekly)
 *   → Qualifying completed → Ijazah (monthly) → Ijazah completed
 *   → Readings (optional advanced stage) → Teacher/Sheikh
 *
 * Stages are derived from confirmed rows only; nothing is guessed.
 */
class QuranJourneyService
{
    public const STAGE_NEW = 'new';

    public const STAGE_MEMORIZING = 'memorizing';

    public const STAGE_COMPLETION_PENDING = 'completion_pending';

    public const STAGE_HAFIZ = 'hafiz';

    public const STAGE_QUALIFYING = 'qualifying';

    public const STAGE_IJAZAH = 'ijazah';

    /** المرحلة المتقدمة الاختيارية: القراءات العشر بعد إتمام الإجازة. */
    public const STAGE_READINGS = 'readings';

    public const STAGE_COMPLETED = 'completed';

    public const STAGE_TEACHER = 'teacher';

    public function __construct(
        private readonly QuranProgramBatchService $batches,
    ) {}

    /** @return array<string, string> stage => Arabic label */
    public function stageLabels(): array
    {
        return [
            self::STAGE_NEW => 'طالب جديد',
            self::STAGE_MEMORIZING => 'مرحلة الحفظ',
            self::STAGE_COMPLETION_PENDING => 'بانتظار تأكيد الإتمام',
            self::STAGE_HAFIZ => 'حافظ',
            self::STAGE_QUALIFYING => 'البرنامج التأهيلي',
            self::STAGE_IJAZAH => 'برنامج الإجازة',
            self::STAGE_READINGS => 'القراءات العشر (اختياري)',
            self::STAGE_COMPLETED => 'مكتمل',
            self::STAGE_TEACHER => 'معلم / شيخ',
        ];
    }

    /**
     * The full journey payload for one student.
     *
     * @return array{stage: string, stage_label: string, is_hafiz: bool, completion: ?array, qualifying: ?array, ijazah: ?array, readings: Collection<int, array<string, mixed>>, readings_eligible: bool, has_active_readings: bool, exams: array<string, mixed>, timeline: Collection<int, array{date: string, label: string, done: bool, optional?: bool}>}
     */
    public function journey(Student $student): array
    {
        $labels = $this->stageLabels();

        $completion = QuranCompletion::query()
            ->where('student_id', $student->id)
            ->with('confirmedBy:id,name')
            ->orderByDesc('created_at')
            ->first();

        $qualifying = $this->latestEnrollment($student, ProgramType::Qualifying);
        $ijazah = $this->latestEnrollment($student, ProgramType::Ijazah);

        $readings = $this->readingsFor($student);

        $timeline = collect();

        $firstTasmee = $student->quranRecitationSessions()->orderBy('date')->first();

        if ($firstTasmee) {
            $timeline->push([
                'date' => $firstTasmee->date->format('Y-m-d'),
                'label' => 'بداية التسميع ('.($firstTasmee->type?->label() ?? $firstTasmee->type).')',
                'done' => true,
            ]);
        }

        if ($completion) {
            $timeline->push([
                'date' => $completion->completed_at?->format('Y-m-d') ?? $completion->created_at->format('Y-m-d'),
                'label' => $completion->isConfirmed() ? 'إتمام حفظ القرآن (مؤكد)' : 'تسجيل إتمام الحفظ (بانتظار التأكيد)',
                'done' => $completion->isConfirmed(),
            ]);
        }

        if ($qualifying) {
            $timeline->push([
                'date' => $qualifying->started_at->format('Y-m-d'),
                'label' => 'الالتحاق بالبرنامج التأهيلي',
                'done' => true,
            ]);
            $timeline->push([
                'date' => $qualifying->completed_at?->format('Y-m-d') ?? '—',
                'label' => 'إكمال البرنامج التأهيلي',
                'done' => $qualifying->status === ProgramEnrollmentStatus::Completed,
            ]);
        }

        if ($ijazah) {
            $timeline->push([
                'date' => $ijazah->started_at->format('Y-m-d'),
                'label' => 'الالتحاق ببرنامج الإجازة',
                'done' => true,
            ]);
            $timeline->push([
                'date' => $ijazah->completed_at?->format('Y-m-d') ?? '—',
                'label' => 'إكمال برنامج الإجازة',
                'done' => $ijazah->status === ProgramEnrollmentStatus::Completed,
            ]);
        }

        // المرحلة المتقدمة الاختيارية: القراءات العشر — تُعرض داخل الرحلة
        // بوسم «اختياري» ولا تمنع إتمام الرحلة.
        foreach ($readings as $row) {
            $timeline->push([
                'date' => $row['started_at'] ?? '—',
                'label' => 'بدء '.$row['reading_label'].' (اختياري)',
                'done' => true,
                'optional' => true,
            ]);

            if ($row['status'] === ProgramEnrollmentStatus::Completed) {
                $timeline->push([
                    'date' => $row['completed_at'] ?? '—',
                    'label' => 'إتمام '.$row['reading_label'].' (اختياري)',
                    'done' => true,
                    'optional' => true,
                ]);
            }
        }

        $isTeacher = Teacher::where('user_id', $student->user_id)->exists();

        if ($isTeacher) {
            $timeline->push([
                'date' => '—',
                'label' => 'أصبح معلماً / شيخاً',
                'done' => true,
            ]);
        }

        $hasActiveReadings = $readings->contains(
            fn (array $row) => $row['status'] === ProgramEnrollmentStatus::Active
                && ($row['program_status'] === null || $row['program_status'] === QuranListeningProgramStatus::Active)
        );

        $stage = $this->stageFor($completion, $qualifying, $ijazah, $isTeacher, $student, $hasActiveReadings);

        return [
            'stage' => $stage,
            'stage_label' => $labels[$stage],
            'is_hafiz' => $completion?->isConfirmed() ?? false,
            'completion' => $completion ? [
                'status' => $completion->status,
                'completed_at' => $completion->completed_at?->format('Y-m-d'),
                'confirmed_at' => $completion->confirmed_at?->format('Y-m-d'),
                'confirmed_by' => $completion->confirmedBy?->name,
                'notes' => $completion->notes,
            ] : null,
            'qualifying' => $this->enrollmentSummary($student, ProgramType::Qualifying, $qualifying),
            'ijazah' => $this->enrollmentSummary($student, ProgramType::Ijazah, $ijazah),
            'readings' => $readings,
            'readings_eligible' => $this->hasCompletedIjazah($student),
            'has_active_readings' => $hasActiveReadings,
            'exams' => [
                'latest' => $student->hafizMonthlyExams()
                    ->with('supervisor:id,name')
                    ->orderByDesc('month')
                    ->first(),
                'pending_revisions' => $student->hafizMonthlyExams()
                    ->whereHas('revisions', fn ($q) => $q->whereIn('status', ['pending', 'completed']))
                    ->with(['revisions'])
                    ->orderByDesc('month')
                    ->get(),
            ],
            'stats' => [
                'new_amount' => (float) $student->quranRecitationSessions()->where('type', 'new')->sum('amount'),
                'revision_amount' => (float) $student->quranRecitationSessions()->where('type', 'revision')->sum('amount'),
                'latest_tasmee' => $student->quranRecitationSessions()->orderByDesc('date')->orderByDesc('created_at')->first(),
            ],
            'timeline' => $timeline,
        ];
    }

    private function stageFor(?QuranCompletion $completion, $qualifying, $ijazah, bool $isTeacher, Student $student, bool $hasActiveReadings = false): string
    {
        if ($isTeacher) {
            return self::STAGE_TEACHER;
        }

        if ($ijazah) {
            if ($ijazah->status !== ProgramEnrollmentStatus::Completed) {
                return self::STAGE_IJAZAH;
            }

            // القراءات اختيارية: مرحلة متقدمة بعد الإجازة، وعدم التسجيل فيها
            // لا يمنع «مكتمل».
            return $hasActiveReadings ? self::STAGE_READINGS : self::STAGE_COMPLETED;
        }

        if ($qualifying) {
            return self::STAGE_QUALIFYING;
        }

        if ($completion?->isConfirmed()) {
            return self::STAGE_HAFIZ;
        }

        if ($completion) {
            return self::STAGE_COMPLETION_PENDING;
        }

        return $student->quranRecitationSessions()->exists()
            ? self::STAGE_MEMORIZING
            : self::STAGE_NEW;
    }

    /**
     * التحاقات القراءات العشر (المرحلة المتقدمة الاختيارية) مع دورة كل قراءة
     * وتقدّمها — بترتيب التسجيل. بلا N+1: استعلام واحد للدورات.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function readingsFor(Student $student): Collection
    {
        $enrollments = ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Readings)
            ->orderBy('created_at')
            ->get();

        $programs = QuranListeningProgram::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->orderByRaw("case status when 'active' then 0 when 'completed' then 1 else 2 end")
            ->orderByDesc('created_at')
            ->get()
            ->unique('enrollment_id')
            ->keyBy('enrollment_id');

        return $enrollments->map(function (ProgramEnrollment $enrollment) use ($programs) {
            $program = $programs->get($enrollment->id);

            return [
                'id' => $enrollment->id,
                'reading' => $enrollment->reading?->value,
                'reading_label' => $enrollment->reading?->programLabel() ?? 'برنامج القراءات',
                'status' => $enrollment->status,
                'started_at' => $enrollment->started_at?->format('Y-m-d'),
                'completed_at' => $enrollment->completed_at?->format('Y-m-d'),
                'program_id' => $program?->id,
                'program_status' => $program?->status,
                'progress' => $program ? $this->batches->progressSummary($program) : null,
            ];
        })->values();
    }

    private function hasCompletedIjazah(Student $student): bool
    {
        return ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Completed)
            ->exists();
    }

    private function latestEnrollment(Student $student, ProgramType $type)
    {
        return $student->programEnrollments()
            ->where('program_type', $type)
            ->orderByDesc('created_at')
            ->first();
    }

    private function enrollmentSummary(Student $student, ProgramType $type, $enrollment): ?array
    {
        if (! $enrollment) {
            return null;
        }

        $weekly = $type === ProgramType::Qualifying;
        $passed = $weekly
            ? $student->qualifyingWeeklyEvaluations()->where('result', 'passed')->count()
            : $student->ijazahMonthlyEvaluations()->where('result', 'passed')->count();
        $total = $weekly
            ? $student->qualifyingWeeklyEvaluations()->count()
            : $student->ijazahMonthlyEvaluations()->count();

        return [
            'status' => $enrollment->status,
            'started_at' => $enrollment->started_at?->format('Y-m-d'),
            'completed_at' => $enrollment->completed_at?->format('Y-m-d'),
            'passed' => $passed,
            'total' => $total,
        ];
    }
}
