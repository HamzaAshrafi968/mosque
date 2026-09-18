<?php

namespace App\Services;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranListeningBatchStatus;
use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningProgramStatus;
use App\Enums\QuranListeningTestResult;
use App\Models\ProgramEnrollment;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\QuranListeningProgramItem;
use App\Models\QuranListeningTest;
use App\Models\QuranListeningTestItem;
use App\Models\Student;
use App\Models\User;
use App\Support\QuranJuzMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «برامج الاستماع» (تدريبي/إجازة/تأهيلي) — استماع أجزاء + اختبار كل 5 أجزاء:
 *
 *   30 جزءاً ÷ 5 = 6 دفعات، الدفعة k لا تُفتح إلا بنجاح اختبار k-1.
 *
 * - الحالة تُشتق من عناصر الأجزاء الخمسة: استماع الجميع → بانتظار الاختبار،
 *   رسوب جزء → يحتاج إعادة استماعه ثم إعادة الاختبار.
 * - الاختبار يدوي (نتيجة لكل جزء) والدرجة تُحسب في الـ Backend بحد نجاح الجامع.
 * - إتمام الدفعة السادسة يُتمّ البرنامج ويحوّل الطالب للبرنامج التالي
 *   تلقائياً (تدريبي → إجازة → تأهيلي).
 * - «وين موصل» و«شو مسمع»: progress() و panelData() يجهّزان شبكة الدفعات
 *   والأجزاء وسجل الاستماع وسجل الاختبارات للواجهات.
 */
class QuranListeningProgramService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
        private readonly QuranSettingsService $settings,
        private readonly QuranProgramService $programs,
    ) {}

    /** تسجيل طالب في البرنامج التدريبي (التحاق + دورة استماع كاملة). */
    public function enrollTraining(Student $student, User $actor): QuranListeningProgram
    {
        if ($this->activeProgram($student, ProgramType::Training)) {
            throw ValidationException::withMessages(['student_id' => ['الطالب مسجّل مسبقاً في البرنامج التدريبي']]);
        }

        return DB::transaction(function () use ($student, $actor) {
            $enrollment = $this->programs->enrollIfAbsent(
                ProgramType::Training,
                $student->id,
                Carbon::today()->format('Y-m-d'),
                $actor,
            );

            $program = $this->ensureForEnrollment($enrollment) ?? $this->createProgram($student, ProgramType::Training, $enrollment->id, $actor);

            $this->notifications->notifyStudentCircle(
                $student,
                'بدء البرنامج التدريبي',
                'تم تسجيلك في البرنامج التدريبي — استمع لأجزاء الدفعة الأولى (1–5)، وبعد كل 5 أجزاء يُسجَّل اختبارك.',
                route('student.quran-programs.index')
            );

            return $program;
        });
    }

    /**
     * ضمان وجود دورة استماع نشطة لالتحاق برنامج (تأهيلي/إجازة/تدريبي).
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

    /** الدورة المرتبطة ببرنامج تدريبي مُنشأ يدوياً. */
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

    /**
     * اشتقاق حالات الدفعات من عناصرها: فتح الدفعة التالية عند نجاح السابقة،
     * وقفل ما بعدها، وتحديث حالات «قيد الاستماع/بانتظار الاختبار/تحتاج إعادة».
     * Idempotent.
     *
     * @return Collection<int, array{batch_number: int, from_juz: int, to_juz: int, status: QuranListeningBatchStatus, batch: QuranListeningProgramBatch}>
     */
    public function sync(QuranListeningProgram $program): Collection
    {
        if (! $program->isActive()) {
            return $this->states($program);
        }

        $batches = $program->batches()->with('items')->get();
        $prevPassed = true;

        foreach ($batches as $batch) {
            if ($batch->isPassed()) {
                $prevPassed = true;

                continue;
            }

            if (! $prevPassed) {
                $this->lockBatch($batch);

                continue;
            }

            $prevPassed = false;

            $batch->items()
                ->where('status', QuranListeningItemStatus::Locked)
                ->update(['status' => QuranListeningItemStatus::Available]);

            $batch->load('items');

            $status = $this->deriveBatchStatus($batch);

            if ($batch->status !== $status) {
                $batch->update(['status' => $status]);
            }
        }

        return $this->states($program);
    }

    /** حالة الدفعة مشتقة من عناصر أجزائها الخمسة. */
    public function deriveBatchStatus(QuranListeningProgramBatch $batch): QuranListeningBatchStatus
    {
        $items = $batch->relationLoaded('items') ? $batch->items : $batch->items()->get();

        if ($items->isNotEmpty() && $items->every(fn (QuranListeningProgramItem $item) => $item->isPassed())) {
            return QuranListeningBatchStatus::Passed;
        }

        if ($items->contains(fn (QuranListeningProgramItem $item) => $item->isNeedsRepeat())) {
            return QuranListeningBatchStatus::NeedsRepeat;
        }

        $allListened = $items->isNotEmpty()
            && $items->every(fn (QuranListeningProgramItem $item) => $item->isListened() || $item->isPassed());

        return $allListened
            ? QuranListeningBatchStatus::ReadyForTest
            : QuranListeningBatchStatus::Listening;
    }

    /** الدفعة التي يعمل عليها الطالب الآن (أول دفعة مفتوحة غير ناجحة). */
    public function currentBatch(QuranListeningProgram $program): ?QuranListeningProgramBatch
    {
        $states = $this->sync($program);

        foreach ($states as $state) {
            if ($state['status'] !== QuranListeningBatchStatus::Locked
                && $state['status'] !== QuranListeningBatchStatus::Passed) {
                return $state['batch'];
            }
        }

        return null;
    }

    /** تسجيل استماع جزء (الطالب أو الأستاذ) مع فرض قيود الدفعة. */
    public function markListened(QuranListeningProgramItem $item, User $actor): void
    {
        $program = $item->program()->first();

        if (! $program || ! $program->isActive()) {
            throw ValidationException::withMessages(['item' => ['لا يمكن تسجيل الاستماع في برنامج غير نشط']]);
        }

        if ($item->isPassed()) {
            throw ValidationException::withMessages(['item' => ['هذا الجزء ناجح مسبقاً']]);
        }

        if (! $item->canBeListened()) {
            throw ValidationException::withMessages(['item' => ['هذا الجزء مقفل — أكمل استماع أجزاء الدفعة السابقة أولاً']]);
        }

        $item->update([
            'status' => QuranListeningItemStatus::Listened,
            'listened_at' => now(),
            'listened_by' => $actor->id,
        ]);

        $this->audit->logModel('quran_training.item_listened', $item, actor: $actor);

        $this->sync($program);
    }

    /** تتبع اختياري لثواني التشغيل المتراكمة للجزء. */
    public function recordProgress(QuranListeningProgramItem $item, int $seconds): void
    {
        $seconds = max(0, min($seconds, 3600));

        if ($seconds === 0) {
            return;
        }

        if (! $item->isListened() && ! $item->canBeListened()) {
            return;
        }

        $item->increment('listen_seconds', $seconds);
    }

    /**
     * تسجيل اختبار دفعة (5 أجزاء): نتيجة لكل جزء، والنجاح وفق الدرجة وحد
     * الجامع. الرسوب يرجع الأجزاء الراسبة لحالة «يحتاج إعادة» — إعادة
     * الاستماع فقط ثم إعادة الاختبار.
     *
     * @param  array<int|string, string>  $results  juz => pass|fail
     */
    public function recordBatchTest(QuranListeningProgramBatch $batch, array $results, User $actor, ?string $notes = null): QuranListeningTest
    {
        $program = $batch->program()->first();

        if (! $program || ! $program->isActive()) {
            throw ValidationException::withMessages(['results' => ['لا يمكن تسجيل اختبار لبرنامج غير نشط']]);
        }

        $this->assertBatchTestAllowed($batch);

        $normalized = [];

        foreach ($batch->juzNumbers() as $juz) {
            $raw = $results[$juz] ?? $results[(string) $juz] ?? null;
            $result = QuranListeningTestResult::tryFrom(is_array($raw) ? (string) ($raw['result'] ?? '') : (string) $raw);

            if (! $result) {
                throw ValidationException::withMessages([
                    'results' => ['حدّد نتيجة الجزء '.$juz.' (ناجح أو يحتاج إعادة)'],
                ]);
            }

            $normalized[$juz] = $result;
        }

        $passedCount = collect($normalized)
            ->filter(fn (QuranListeningTestResult $result) => $result === QuranListeningTestResult::Pass)
            ->count();

        $score = round($passedCount / max(1, count($normalized)) * 100, 2);
        $passingPercentage = $this->settings->minimumPassingPercentage();
        $overall = $score >= $passingPercentage
            ? QuranListeningTestResult::Pass
            : QuranListeningTestResult::Fail;

        return DB::transaction(function () use ($batch, $program, $normalized, $overall, $score, $passingPercentage, $actor, $notes) {
            $test = QuranListeningTest::create([
                'plan_id' => null,
                'batch_id' => null,
                'listening_batch_id' => $batch->id,
                'student_id' => $batch->student_id,
                'tested_by' => $actor->id,
                'tested_at' => now(),
                'result' => $overall,
                'score' => $score,
                'passing_percentage' => $passingPercentage,
                'notes' => $notes,
            ]);

            foreach ($normalized as $juz => $result) {
                $range = QuranJuzMap::pageRange($juz);

                QuranListeningTestItem::create([
                    'test_id' => $test->id,
                    'plan_item_id' => null,
                    'juz' => $juz,
                    'from_page' => $range['from'],
                    'to_page' => $range['to'],
                    'result' => $result,
                    'needs_repeat' => $result === QuranListeningTestResult::Fail,
                ]);
            }

            $this->audit->logModel('quran_training.test_recorded', $test, actor: $actor);

            $this->applyBatchOutcome($batch, $test, $actor);

            $this->sync($program);

            return $test->load('items');
        });
    }

    /** فرض «لا اختبار قبل اكتمال استماع الأجزاء الخمسة». */
    public function assertBatchTestAllowed(QuranListeningProgramBatch $batch): void
    {
        if ($batch->isPassed()) {
            throw ValidationException::withMessages(['results' => ['هذه الدفعة مجتازة مسبقاً']]);
        }

        if ($batch->isLocked()) {
            throw ValidationException::withMessages(['results' => ['هذه الدفعة مقفلة — اجتز اختبار الدفعة السابقة أولاً']]);
        }

        $program = $batch->program()->first();

        if ($program) {
            $this->sync($program);
            $batch->refresh();
        }

        if (! $batch->isReadyForTest()) {
            throw ValidationException::withMessages([
                'results' => ['لا يُفتح الاختبار قبل تسجيل استماع أجزاء الدفعة الخمسة كاملة'],
            ]);
        }
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
     * «وين موصل»: شبكة الدفعات والأجزاء، الدفعة الحالية، والجزء التالي.
     *
     * @return array{
     *     states: Collection<int, array{batch_number: int, from_juz: int, to_juz: int, status: QuranListeningBatchStatus, batch: QuranListeningProgramBatch}>,
     *     currentBatch: ?QuranListeningProgramBatch,
     *     juzGrid: Collection<int, array<string, mixed>>,
     *     summary: array<string, mixed>
     * }
     */
    public function progress(QuranListeningProgram $program): array
    {
        $states = $this->sync($program);
        $currentBatch = null;

        foreach ($states as $state) {
            if ($state['status'] !== QuranListeningBatchStatus::Locked
                && $state['status'] !== QuranListeningBatchStatus::Passed) {
                $currentBatch = $state['batch'];

                break;
            }
        }

        $items = $program->items()->with(['listenedBy:id,name', 'passedBy:id,name'])->get()->keyBy('juz');
        $batchNumbers = $program->batches()->pluck('batch_number', 'id');

        $juzGrid = collect(range(1, QuranJuzMap::TOTAL_JUZ))->map(function (int $juz) use ($items, $batchNumbers) {
            /** @var QuranListeningProgramItem|null $item */
            $item = $items->get($juz);

            return [
                'juz' => $juz,
                'from_page' => $item?->from_page ?? QuranJuzMap::pageRange($juz)['from'],
                'to_page' => $item?->to_page ?? QuranJuzMap::pageRange($juz)['to'],
                'status' => $item?->status,
                'batch_number' => $item ? ($batchNumbers->get($item->batch_id) ?? QuranListeningProgramBatch::numberForJuz($juz)) : null,
                'listened_at' => $item?->listened_at,
                'listened_by' => $item?->listenedBy?->name,
                'listen_seconds' => $item?->listen_seconds ?? 0,
            ];
        });

        $listened = $items->filter(fn (QuranListeningProgramItem $item) => $item->isListened() || $item->isPassed())->count();
        $passed = $items->filter(fn (QuranListeningProgramItem $item) => $item->isPassed())->count();
        $passedBatches = $states->filter(fn (array $state) => $state['status'] === QuranListeningBatchStatus::Passed)->count();
        $nextItem = $currentBatch
            ? $items->first(fn (QuranListeningProgramItem $item) => $item->batch_id === $currentBatch->id && $item->canBeListened())
            : null;

        return [
            'states' => $states,
            'currentBatch' => $currentBatch,
            'juzGrid' => $juzGrid,
            'summary' => [
                'total_juz' => QuranJuzMap::TOTAL_JUZ,
                'listened_juz' => $listened,
                'passed_juz' => $passed,
                'passed_batches' => $passedBatches,
                'total_batches' => QuranListeningProgramBatch::TOTAL_BATCHES,
                'percentage' => (int) round($listened / QuranJuzMap::TOTAL_JUZ * 100),
                'next_juz' => $nextItem?->juz,
                'next_juz_label' => $nextItem ? 'الجزء '.$nextItem->juz.' (صفحات '.$nextItem->from_page.'–'.$nextItem->to_page.')' : null,
                'total_listen_seconds' => (int) $items->sum('listen_seconds'),
            ],
        ];
    }

    /**
     * «شو مسمع»: سجل الاستماع لكل جزء + سجل الاختبارات بنتائجها.
     *
     * @return array{listeningLog: Collection<int, QuranListeningProgramItem>, tests: Collection<int, QuranListeningTest>}
     */
    public function history(QuranListeningProgram $program): array
    {
        $listeningLog = $program->items()
            ->with([
                'listenedBy:id,name',
                'passedBy:id,name',
                'batch:id,batch_number,from_juz,to_juz',
                'quranRecitationSession:id,result,from_page,to_page',
            ])
            ->whereNotNull('listened_at')
            ->orderByDesc('listened_at')
            ->get();

        $tests = $program->tests()
            ->with([
                'items',
                'examiner:id,name',
                'listeningBatch:id,batch_number,from_juz,to_juz',
            ])
            ->orderByDesc('tested_at')
            ->get();

        return [
            'listeningLog' => $listeningLog,
            'tests' => $tests,
        ];
    }

    /** بيانات صفحة الدورة كاملة (المتحكمات). @return array<string, mixed> */
    public function panelData(QuranListeningProgram $program): array
    {
        $program->load([
            'student:id,name,study_session_id',
            'enrollment:id,program_type,status',
        ]);

        $progress = $this->progress($program);
        $history = $this->history($program);

        $program->load([
            'batches.lastTest:id,result,score,passing_percentage,tested_at',
        ]);

        return [
            'program' => $program,
            'states' => $progress['states'],
            'currentBatch' => $progress['currentBatch'],
            'juzGrid' => $progress['juzGrid'],
            'summary' => $progress['summary'],
            'listeningLog' => $history['listeningLog'],
            'tests' => $history['tests'],
            'minimumPassingPercentage' => $this->settings->minimumPassingPercentage(),
        ];
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
                'listened_juz' => 0,
                'passed_juz' => 0,
                'passed_batches' => 0,
                'total_batches' => QuranListeningProgramBatch::TOTAL_BATCHES,
                'percentage' => 0,
                'next_juz' => null,
                'next_juz_label' => null,
                'total_listen_seconds' => 0,
            ],
            'listeningLog' => collect(),
            'tests' => collect(),
            'minimumPassingPercentage' => app(QuranSettingsService::class)->minimumPassingPercentage(),
        ];
    }

    /**
     * نتيجة اختبار الدفعة: تثبيت الناجحة وفتح التالية، أو إرجاع الأجزاء
     * الراسبة لإعادة الاستماع.
     */
    private function applyBatchOutcome(QuranListeningProgramBatch $batch, QuranListeningTest $test, User $actor): void
    {
        $test->loadMissing('items');

        if ($test->isPass()) {
            $batch->items()->update([
                'status' => QuranListeningItemStatus::Passed,
                'last_result' => QuranListeningTestResult::Pass,
                'attempts' => DB::raw('attempts + 1'),
                'passed_at' => now(),
                'passed_by' => $actor->id,
            ]);

            $batch->update([
                'status' => QuranListeningBatchStatus::Passed,
                'last_test_id' => $test->id,
                'passed_at' => now(),
            ]);

            $this->audit->logModel('quran_training.batch_passed', $batch, actor: $actor);

            if ($batch->batch_number === QuranListeningProgramBatch::TOTAL_BATCHES) {
                $this->completeProgram($batch->program()->first(), $actor);
            }
        } else {
            foreach ($test->items as $item) {
                $passed = $item->result === QuranListeningTestResult::Pass;

                $batch->items()
                    ->where('juz', $item->juz)
                    ->update([
                        'status' => $passed ? QuranListeningItemStatus::Passed : QuranListeningItemStatus::NeedsRepeat,
                        'last_result' => $item->result,
                        'attempts' => DB::raw('attempts + 1'),
                        'passed_at' => $passed ? now() : null,
                        'passed_by' => $passed ? $actor->id : null,
                    ]);
            }

            $batch->update([
                'status' => QuranListeningBatchStatus::NeedsRepeat,
                'last_test_id' => $test->id,
            ]);

            $this->audit->logModel('quran_training.batch_needs_repeat', $batch, actor: $actor);
        }

        $this->notifyTestResult($batch, $test);
    }

    /** إتمام البرنامج: إغلاق الدورة، إتمام الالتحاق، والتحويل للبرنامج التالي. */
    private function completeProgram(?QuranListeningProgram $program, User $actor): void
    {
        if (! $program || $program->isCompleted()) {
            return;
        }

        $program->update(['status' => QuranListeningProgramStatus::Completed]);

        $this->audit->logModel('quran_training.program_completed', $program, actor: $actor);

        if ($program->type === ProgramType::Training && $program->enrollment_id) {
            ProgramEnrollment::query()
                ->whereKey($program->enrollment_id)
                ->update([
                    'status' => ProgramEnrollmentStatus::Completed,
                    'completed_at' => Carbon::today()->format('Y-m-d'),
                    'completed_by' => $actor->id,
                ]);
        }

        $student = $program->student()->first();
        $next = $program->type->nextProgram();
        $nextLabel = null;

        if ($student && $next) {
            $enrollment = $this->programs->enrollIfAbsent($next, $student->id, Carbon::today()->format('Y-m-d'), $actor);
            $this->ensureForEnrollment($enrollment);
            $nextLabel = $next->label();
        }

        if ($student) {
            $this->notifications->notifyStudentCircle(
                $student,
                'اكتمل '.$program->label(),
                $nextLabel
                    ? 'ما شاء الله! أتممت '.$program->label().' — وتم إلحاقك بـ'.$nextLabel.' تلقائياً.'
                    : 'ما شاء الله! أتممت '.$program->label().' كاملاً.',
                route('student.quran-programs.index')
            );
        }
    }

    private function notifyTestResult(QuranListeningProgramBatch $batch, QuranListeningTest $test): void
    {
        $student = $batch->student()->first();

        if (! $student) {
            return;
        }

        $score = rtrim(rtrim(number_format((float) $test->score, 2, '.', ''), '0'), '.');

        if ($test->isPass()) {
            $this->notifications->notifyStudentCircle(
                $student,
                'نجاح في اختبار '.$batch->label(),
                'ما شاء الله! نجحت في اختبار '.$batch->label().' بنسبة '.$score.'%.',
                route('student.quran-programs.index')
            );

            return;
        }

        $failed = $test->items()
            ->where('result', QuranListeningTestResult::Fail)
            ->orderBy('juz')
            ->pluck('juz')
            ->implode('، ');

        $this->notifications->notifyStudentCircle(
            $student,
            'اختبار '.$batch->label().' — يحتاج إعادة',
            'نتيجة الاختبار: '.$score.'% — رسب في الأجزاء: '.$failed.' — أعد استماع الأجزاء الراسبة ثم أعد الاختبار.',
            route('student.quran-programs.index')
        );
    }

    /** قفل دفعة وعناصرها (دفعة لم تُفتح بعد). */
    private function lockBatch(QuranListeningProgramBatch $batch): void
    {
        if (! $batch->isLocked()) {
            $batch->update(['status' => QuranListeningBatchStatus::Locked]);
        }

        $batch->items()
            ->whereNotIn('status', [QuranListeningItemStatus::Passed->value, QuranListeningItemStatus::Locked->value])
            ->update(['status' => QuranListeningItemStatus::Locked]);
    }

    /** @return Collection<int, array{batch_number: int, from_juz: int, to_juz: int, status: QuranListeningBatchStatus, batch: QuranListeningProgramBatch}> */
    private function states(QuranListeningProgram $program): Collection
    {
        return $program->batches()
            ->with('lastTest:id,result,score,passing_percentage,tested_at')
            ->get()
            ->map(fn (QuranListeningProgramBatch $batch) => [
                'batch_number' => $batch->batch_number,
                'from_juz' => $batch->from_juz,
                'to_juz' => $batch->to_juz,
                'status' => $batch->status,
                'batch' => $batch,
            ]);
    }

    private function programForEnrollment(ProgramEnrollment $enrollment): ?QuranListeningProgram
    {
        return QuranListeningProgram::query()
            ->where('enrollment_id', $enrollment->id)
            ->latest()
            ->first();
    }
}
