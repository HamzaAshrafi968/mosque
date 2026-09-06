<?php

namespace App\Services;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Models\QuranCompletion;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Collection;

/**
 * Computes the "Quran Journey" of a student (spec §11/§12):
 *
 *   Memorization → Quran Completed → Hafiz → Qualifying (weekly)
 *   → Qualifying completed → Ijazah (monthly) → Ijazah completed → Teacher/Sheikh
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

    public const STAGE_COMPLETED = 'completed';

    public const STAGE_TEACHER = 'teacher';

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
            self::STAGE_COMPLETED => 'مكتمل',
            self::STAGE_TEACHER => 'معلم / شيخ',
        ];
    }

    /**
     * The full journey payload for one student.
     *
     * @return array{stage: string, stage_label: string, is_hafiz: bool, completion: ?array, qualifying: ?array, ijazah: ?array, exams: array<string, mixed>, timeline: Collection<int, array{date: string, label: string, done: bool}>}
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

        $isTeacher = Teacher::where('user_id', $student->user_id)->exists();

        if ($isTeacher) {
            $timeline->push([
                'date' => '—',
                'label' => 'أصبح معلماً / شيخاً',
                'done' => true,
            ]);
        }

        $stage = $this->stageFor($completion, $qualifying, $ijazah, $isTeacher, $student);

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

    private function stageFor(?QuranCompletion $completion, $qualifying, $ijazah, bool $isTeacher, Student $student): string
    {
        if ($isTeacher) {
            return self::STAGE_TEACHER;
        }

        if ($ijazah) {
            return $ijazah->status === ProgramEnrollmentStatus::Completed
                ? self::STAGE_COMPLETED
                : self::STAGE_IJAZAH;
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
