<?php

namespace App\Services;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranListeningBatchStatus;
use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningProgramStatus;
use App\Models\ProgramEnrollment;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\QuranListeningProgramItem;
use App\Models\Student;
use App\Models\User;
use App\Support\QuranJuzMap;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * «برامج الاستماع» (الإجازة/التأهيلي) — توليد دورة الدفعات وربطها بالالتحاق:
 *
 * - 30 جزءاً ÷ 5 = 6 دفعات لكل دورة، والدورة تُنشأ كسولاً لالتحاق نشط.
 * - التسميع والاختبار التراكمي وإعادة الأجزاء الراسبة يديرها
 *   QuranProgramBatchService؛ هذه الخدمة تتولى الإنشاء والعرض والإلغاء.
 */
class QuranListeningProgramService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * ضمان وجود دورة استماع نشطة لالتحاق برنامج (تأهيلي/إجازة).
     * Idempotent: تعيد الدورة القائمة أو تنشئها إن لم توجد.
     */
    public function ensureForEnrollment(ProgramEnrollment $enrollment): ?QuranListeningProgram
    {
        if (! $enrollment->isActive()) {
            return $this->programForEnrollment($enrollment);
        }

        $student = Student::query()
            ->withoutGlobalScope('study_session')
            ->find($enrollment->student_id);

        if (! $student) {
            return null;
        }

        return $this->activeProgram($student, $enrollment->program_type)
            ?? $this->createProgram($student, $enrollment->program_type, $enrollment->id, null);
    }

    /** كل برامج الاستماع للطالب مع توليد دورات الالتحاقات النشطة كسولاً. */
    public function programsForStudent(Student $student): Collection
    {
        ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->get()
            ->each(fn (ProgramEnrollment $enrollment) => $this->ensureForEnrollment($enrollment));

        return QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->with(['enrollment:id,program_type,status'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function activeProgram(Student $student, ProgramType $type): ?QuranListeningProgram
    {
        return QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', $type)
            ->where('status', QuranListeningProgramStatus::Active)
            ->latest()
            ->first();
    }

    /** إنشاء دورة استماع جديدة (6 دفعات × 5 أجزاء). */
    public function createProgram(Student $student, ProgramType $type, ?string $enrollmentId, ?User $actor = null): QuranListeningProgram
    {
        $program = QuranListeningProgram::create([
            'student_id' => $student->id,
            'enrollment_id' => $enrollmentId,
            'type' => $type,
            'status' => QuranListeningProgramStatus::Active,
        ]);

        for ($number = 1; $number <= QuranListeningProgramBatch::TOTAL_BATCHES; $number++) {
            $range = QuranListeningProgramBatch::juzRange($number);
            $isFirst = $number === 1;

            $batch = QuranListeningProgramBatch::create([
                'program_id' => $program->id,
                'student_id' => $student->id,
                'batch_number' => $number,
                'from_juz' => $range['from'],
                'to_juz' => $range['to'],
                'status' => $isFirst ? QuranListeningBatchStatus::Listening : QuranListeningBatchStatus::Locked,
            ]);

            foreach (range($range['from'], $range['to']) as $juz) {
                $pages = QuranJuzMap::pageRange($juz);

                QuranListeningProgramItem::create([
                    'program_id' => $program->id,
                    'batch_id' => $batch->id,
                    'juz' => $juz,
                    'from_page' => $pages['from'],
                    'to_page' => $pages['to'],
                    'status' => $isFirst ? QuranListeningItemStatus::Available : QuranListeningItemStatus::Locked,
                ]);
            }
        }

        $this->audit->logModel('quran_training.program_created', $program, actor: $actor);

        return $program;
    }

    public function cancelProgram(QuranListeningProgram $program, ?User $actor = null): void
    {
        if ($program->isCompleted()) {
            throw ValidationException::withMessages(['program' => ['لا يمكن إلغاء برنامج مكتمل']]);
        }

        if ($program->isCancelled()) {
            return;
        }

        $program->update(['status' => QuranListeningProgramStatus::Cancelled]);

        $this->audit->logModel('quran_training.program_cancelled', $program, actor: $actor);
    }

    /**
     * بيانات فارغة عند عدم اختيار برنامج (نفس مفاتيح panelData) حتى تبقى
     * الواجهات قادرة على تمرير المتغيرات دون شروط إضافية.
     *
     * @return array<string, mixed>
     */
    public static function emptyPanel(): array
    {
        return [
            'program' => null,
            'states' => collect(),
            'currentBatch' => null,
            'juzGrid' => collect(),
            'summary' => [
                'total_juz' => QuranJuzMap::TOTAL_JUZ,
                'passed_juz' => 0,
                'passed_batches' => 0,
                'total_batches' => QuranListeningProgramBatch::TOTAL_BATCHES,
                'percentage' => 0,
            ],
            'coverage' => null,
            'failedJuz' => [],
            'testScopeJuz' => [],
            'tasmeeSessions' => collect(),
            'tests' => collect(),
            'memorizedJuz' => [],
            'placementTestAllowed' => false,
            'placementTestScope' => [],
            'placementTestJuz' => [],
            'minimumPassingPercentage' => app(QuranSettingsService::class)->minimumPassingPercentage(),
        ];
    }

    private function programForEnrollment(ProgramEnrollment $enrollment): ?QuranListeningProgram
    {
        return QuranListeningProgram::query()
            ->where('enrollment_id', $enrollment->id)
            ->latest()
            ->first();
    }
}
