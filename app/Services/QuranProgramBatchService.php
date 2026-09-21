<?php

namespace App\Services;

use App\Enums\ProgramType;
use App\Enums\QuranListeningBatchStatus;
use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningProgramStatus;
use App\Enums\QuranListeningTestResult;
use App\Enums\QuranTasmeeResult;
use App\Enums\QuranTasmeeType;
use App\Models\ProgramEnrollment;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\QuranListeningProgramItem;
use App\Models\QuranListeningTest;
use App\Models\QuranListeningTestItem;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\QuranJuzMap;
use App\Support\TasmeePageInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * محرك دورة برامج التأهيلي والإجازة والقراءات — دفعات 5 أجزاء:
 *
 *   حفظ صفحات ← تسميع مع تسجيل الأخطاء ← اكتمال أجزاء الدفعة ← اختبار تراكمي
 *   (من الجزء 1 إلى آخر جزء في الدفعة) ← نجاح يفتح الدفعة التالية، والرسوب
 *   يُعيد الأجزاء الراسبة فقط لإعادة التسميع ثم إعادة اختبارها فقط.
 *
 * لا خمسات ولا خطط استماع: التسميع (QuranRecitationSession) هو مصدر التغطية،
 * والاختبار يُخزَّن في quran_listening_tests/listening_batch_id مثل دفعات
 * الحفظ. الفرق بين الأنواع عند إتمام الدورة: التأهيلي يُنهي الالتحاق ويحوّل
 * تلقائياً للإجازة (ختم التأهيل)، والإجازة والقراءات تُنهيان دورتهما
 * والتحاقهما فقط بلا تحويل.
 */
class QuranProgramBatchService
{
    /** @var array<int, ProgramType> الأنواع التي يديرها هذا المحرك. */
    public const SUPPORTED_TYPES = [ProgramType::Qualifying, ProgramType::Ijazah, ProgramType::Readings];

    /** نطاق اختبار الإعادة الكامل (1..آخر جزء) بعد الرسوب. */
    public const RETAKE_SCOPE_FULL = 'full';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
        private readonly QuranSettingsService $settings,
        private readonly QuranPageService $pages,
        private readonly QuranProgramService $programs,
        private readonly QuranMemorizationGatingService $memorization,
    ) {}

    public function supports(QuranListeningProgram $program): bool
    {
        return in_array($program->type, self::SUPPORTED_TYPES, true);
    }

    /**
     * دورة التأهيلي/الإجازة النشطة للطالب (إن وُجدت).
     *
     * لا تشمل القراءات عن قصد: القراءات قد تكون متوازية (أكثر من قراءة
     * نشطة)، فتسميعها يُسجَّل من صفحة الدورة نفسها لا من الشاشة العامة.
     */
    public function activeCycle(Student $student): ?QuranListeningProgram
    {
        return QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->whereIn('type', [ProgramType::Qualifying->value, ProgramType::Ijazah->value])
            ->where('status', QuranListeningProgramStatus::Active->value)
            ->latest()
            ->first();
    }

    /** أستاذ من دوام الطالب (يفضّل أستاذ آخر تسميع له) — للتسميع التلقائي. */
    public function resolveTeacher(Student $student): ?Teacher
    {
        return $this->memorization->resolveTeacher($student);
    }

    /**
     * مزامنة دورة الطالب: اشتقاق حالات الدفعات والعناصر من التسميع المسجّل
     * ونتائج الاختبارات، وقفل/فتح الدفعات. Idempotent.
     *
     * @return Collection<int, array{batch_number: int, from_juz: int, to_juz: int, status: QuranListeningBatchStatus, batch: QuranListeningProgramBatch}>
     */
    public function sync(QuranListeningProgram $program): Collection
    {
        if (! $this->supports($program) || ! $program->isActive()) {
            return $this->states($program);
        }

        $batches = $program->batches()->with('items')->get();
        $prevPassed = true;

        foreach ($batches as $batch) {
            if ($batch->isPassed()) {
                $this->markItemsPassed($batch);

                $prevPassed = true;

                continue;
            }

            if (! $prevPassed) {
                $this->lockBatch($batch);

                continue;
            }

            $prevPassed = false;

            $status = $this->deriveBatchStatus($batch);

            if ($batch->status !== $status) {
                $batch->update(['status' => $status]);
            }

            if ($status === QuranListeningBatchStatus::Passed) {
                $prevPassed = true;
            }
        }

        return $this->states($program);
    }

    /** الدفعة التي يعمل عليها الطالب الآن (أول دفعة مفتوحة غير مجتازة). */
    public function currentBatch(QuranListeningProgram $program): ?QuranListeningProgramBatch
    {
        foreach ($this->sync($program) as $state) {
            if ($state['status'] !== QuranListeningBatchStatus::Locked
                && $state['status'] !== QuranListeningBatchStatus::Passed) {
                return $state['batch'];
            }
        }

        return null;
    }

    /**
     * حالة الدفعة مشتقة من تغطية التسميع ونتيجة آخر اختبار:
     * passed → needs_repeat (راسب بانتظار إعادة تسميع الراسب) → ready_for_test
     * (كل الأجزاء مسمّعة) → listening (قيد التسميع) → locked.
     */
    public function deriveBatchStatus(QuranListeningProgramBatch $batch): QuranListeningBatchStatus
    {
        $items = $batch->relationLoaded('items') ? $batch->items : $batch->items()->get();
        $lastTest = $batch->last_test_id ? QuranListeningTest::query()->find($batch->last_test_id) : null;
        $lastFailedAt = $lastTest && ! $lastTest->isPass() ? $lastTest->created_at : null;

        $coverage = $this->juzCoverage($batch);
        $afterIntervals = $lastFailedAt ? $this->coveredIntervals($batch, $lastFailedAt) : [];

        $allPassed = $items->isNotEmpty();
        $allListened = $items->isNotEmpty();
        $anyNeedsRepeat = false;

        foreach ($items as $item) {
            $status = $this->deriveItemStatus($item, $coverage, $afterIntervals, $lastFailedAt);

            if ($item->status !== $status) {
                $item->update(['status' => $status]);
            }

            $allPassed = $allPassed && $status === QuranListeningItemStatus::Passed;
            $anyNeedsRepeat = $anyNeedsRepeat || $status === QuranListeningItemStatus::NeedsRepeat;
            $allListened = $allListened && in_array($status, [
                QuranListeningItemStatus::Listened,
                QuranListeningItemStatus::Passed,
            ], true);
        }

        if ($allPassed) {
            return QuranListeningBatchStatus::Passed;
        }

        if ($anyNeedsRepeat) {
            return QuranListeningBatchStatus::NeedsRepeat;
        }

        return $allListened
            ? QuranListeningBatchStatus::ReadyForTest
            : QuranListeningBatchStatus::Listening;
    }

    /**
     * تغطية أجزاء الدفعة بتسميع «جديد»: صفحة مكتملة/جزء مكتمل/الدفعة كاملة.
     *
     * @return array{from: int, to: int, total: int, covered: int, remaining: int, percentage: float, juz: array<int, array<string, mixed>>}
     */
    public function batchCoverage(QuranListeningProgramBatch $batch): array
    {
        $juzRows = $this->juzCoverage($batch);

        $total = (int) collect($juzRows)->sum('total');
        $covered = (int) collect($juzRows)->sum('covered');
        $range = $batch->pagesRange();

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'total' => $total,
            'covered' => $covered,
            'remaining' => max(0, $total - $covered),
            'percentage' => $total > 0 ? round($covered / $total * 100, 2) : 0.0,
            'juz' => $juzRows,
        ];
    }

    /**
     * تغطية كل جزء داخل الدفعة على حدة، ويمكن حصر التغطية بجلسات ما بعد
     * تاريخ معيّن (لتغطية إعادة التسميع بعد الرسوب).
     *
     * @return array<int, array{juz: int, from: int, to: int, total: int, covered: int, percentage: float, complete: bool}>
     */
    public function juzCoverage(QuranListeningProgramBatch $batch, ?Carbon $after = null): array
    {
        $intervals = $this->coveredIntervals($batch, $after);
        $rows = [];

        foreach ($batch->juzNumbers() as $juz) {
            $range = QuranJuzMap::pageRange($juz);
            $total = $range['to'] - $range['from'] + 1;
            $covered = 0;

            for ($page = $range['from']; $page <= $range['to']; $page++) {
                if ($this->pageInIntervals($intervals, $page)) {
                    $covered++;
                }
            }

            $rows[$juz] = [
                'juz' => $juz,
                'from' => $range['from'],
                'to' => $range['to'],
                'total' => $total,
                'covered' => $covered,
                'percentage' => $total > 0 ? round($covered / $total * 100, 2) : 0.0,
                'complete' => $covered >= $total,
            ];
        }

        return $rows;
    }

    /**
     * تغطية إعادة التسميع بعد الرسوب: الأجزاء الراسبة فقط، وبما سُجّل من
     * جلسات بعد تاريخ الاختبار الراسب — لعرض تقدم إعادة الدراسة.
     *
     * @return array<int, array{juz: int, from: int, to: int, total: int, covered: int, percentage: float, complete: bool}>
     */
    public function retakeCoverage(QuranListeningProgramBatch $batch): array
    {
        $test = $batch->last_test_id ? QuranListeningTest::query()->find($batch->last_test_id) : null;
        $failed = $this->failedJuzNumbers($batch);

        if (! $test || $test->isPass() || $failed === []) {
            return [];
        }

        $rows = $this->juzCoverage($batch, $test->created_at);

        return collect($failed)
            ->mapWithKeys(fn (int $juz) => [$juz => $rows[$juz] ?? null])
            ->filter()
            ->all();
    }

    /**
     * الصفحات المغطاة لكل جزء في الدفعة (مرتبة) — للواجهات.
     *
     * @return array<int, array<int, int>>
     */
    public function coveredPages(QuranListeningProgramBatch $batch): array
    {
        $intervals = $this->coveredIntervals($batch);
        $pages = [];

        foreach ($batch->juzNumbers() as $juz) {
            $range = QuranJuzMap::pageRange($juz);

            for ($page = $range['from']; $page <= $range['to']; $page++) {
                if ($this->pageInIntervals($intervals, $page)) {
                    $pages[$juz][] = $page;
                }
            }
        }

        return $pages;
    }

    /**
     * الصفحات المتبقية في جزء العنصر حسب التغطية المسجّلة.
     */
    public function remainingPages(QuranListeningProgramItem $item): int
    {
        $batch = $item->batch()->first();

        if (! $batch) {
            return $item->pagesCount();
        }

        $row = $this->juzCoverage($batch)[$item->juz] ?? null;

        return $row ? max(0, $row['total'] - $row['covered']) : $item->pagesCount();
    }

    /**
     * فترات الصفحات المغطاة بتسميع «جديد» المرتبط بالدفعة (مدمجة ومقصوصة
     * على نطاق الدفعة)، ويمكن حصرها بما سُجّل بعد تاريخ معيّن.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function coveredIntervals(QuranListeningProgramBatch $batch, ?Carbon $after = null): array
    {
        $range = $batch->pagesRange();

        $sessions = QuranRecitationSession::query()
            ->where('program_batch_id', $batch->id)
            ->where('type', QuranTasmeeType::New->value)
            ->whereNotNull('from_page')
            ->whereNotNull('to_page')
            ->when($after, fn ($query) => $query->where('created_at', '>', $after))
            ->get(['from_page', 'to_page']);

        $intervals = $sessions
            ->map(function (QuranRecitationSession $session) use ($range) {
                $from = max($range['from'], (int) $session->from_page);
                $to = min($range['to'], (int) $session->to_page);

                return $from <= $to ? [$from, $to] : null;
            })
            ->filter()
            ->values()
            ->all();

        if ($intervals === []) {
            return [];
        }

        usort($intervals, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($intervals as [$from, $to]) {
            $last = count($merged) - 1;

            if ($last >= 0 && $from <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $to);
            } else {
                $merged[] = [$from, $to];
            }
        }

        return $merged;
    }

    /** هل للدفعة أي جلسة تسميع مسجّلة؟ */
    public function hasTasmeeSessions(QuranListeningProgramBatch $batch): bool
    {
        return QuranRecitationSession::query()
            ->where('program_batch_id', $batch->id)
            ->where('type', QuranTasmeeType::New->value)
            ->exists();
    }

    /**
     * جلسات تسميع دفعة (الأحدث أولاً) مع الأستاذ.
     *
     * @return Collection<int, QuranRecitationSession>
     */
    public function tasmeeSessions(QuranListeningProgramBatch $batch): Collection
    {
        return QuranRecitationSession::query()
            ->where('program_batch_id', $batch->id)
            ->where('type', QuranTasmeeType::New->value)
            ->with('teacher:id,name')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();
    }

    /** فرض «لا تسميع إلا داخل الدفعة الحالية وفي برنامج نشط». */
    public function assertTasmeeAllowed(QuranListeningProgramItem $item): void
    {
        $program = $item->program()->first();

        if (! $program || ! $program->isActive()) {
            throw ValidationException::withMessages(['item' => ['لا يمكن تسجيل التسميع في برنامج غير نشط']]);
        }

        if ($item->isPassed()) {
            throw ValidationException::withMessages(['item' => ['هذا الجزء ناجح مسبقاً']]);
        }

        $current = $this->currentBatch($program);

        if (! $current || (string) $current->id !== (string) $item->batch_id) {
            throw ValidationException::withMessages([
                'item' => ['هذا الجزء ليس ضمن الدفعة الحالية — أكمل الدفعة الحالية واختبارها أولاً'],
            ]);
        }
    }

    /**
     * تسجيل تسميع جزء كامل مع الأخطاء: جلسة «جديد» مرتبطة بالدفعة + تحديث
     * العنصر، ثم إعادة اشتقاق حالة الدفعة.
     *
     * @param  array{date?: ?string, notes?: ?string, word_statuses?: array<string, string>}  $data
     */
    public function recordTasmee(QuranListeningProgramItem $item, array $data, User $actor, string $teacherId): QuranRecitationSession
    {
        $this->assertTasmeeAllowed($item);

        $program = $item->program()->first();

        return DB::transaction(function () use ($item, $data, $actor, $teacherId, $program) {
            $session = $this->createTasmeeSession($item, $data, $teacherId);

            $item->update([
                'status' => QuranListeningItemStatus::Listened,
                'listened_at' => now(),
                'listened_by' => $actor->id,
                'quran_recitation_session_id' => $session->id,
            ]);

            $this->audit->logModel('quran_training.item_tasmee_recorded', $item, actor: $actor);

            if ($program) {
                $this->sync($program);
            }

            return $session;
        });
    }

    /**
     * تسجيل استماع جزئي لصفحات من جزء مع بقاء صفحات أخرى: جلسة «جديد»
     * مرتبطة بالدفعة تُحتسب في التغطية دون إنهاء الجزء، ويبقى العنصر
     * «قيد التسميع» حتى تكتمل صفحاته، و«يحتاج إعادة» حتى يُعاد تسميعها
     * كاملة بعد الرسوب. النطاق الكامل للجزء يُحوَّل إلى recordTasmee.
     *
     * @param  array{date?: ?string, from_page?: int|string, to_page?: int|string, notes?: ?string}  $data
     */
    public function recordPartialListening(QuranListeningProgramItem $item, array $data, User $actor, string $teacherId): QuranRecitationSession
    {
        $this->assertTasmeeAllowed($item);

        $from = (int) ($data['from_page'] ?? 0);
        $to = (int) ($data['to_page'] ?? 0);

        if ($from < (int) $item->from_page || $to > (int) $item->to_page || $from > $to) {
            throw ValidationException::withMessages([
                'from_page' => ['نطاق الصفحات يجب أن يكون داخل '.$item->label().' — من '.$item->from_page.' إلى '.$item->to_page],
            ]);
        }

        if ($from === (int) $item->from_page && $to === (int) $item->to_page) {
            return $this->recordTasmee($item, $data, $actor, $teacherId);
        }

        $program = $item->program()->first();

        return DB::transaction(function () use ($item, $data, $actor, $teacherId, $from, $to, $program) {
            $session = QuranRecitationSession::create([
                'student_id' => $program?->student_id,
                'teacher_id' => $teacherId,
                'program_batch_id' => $item->batch_id,
                'type' => QuranTasmeeType::New,
                'date' => $data['date'] ?? now()->toDateString(),
                'amount' => $to - $from + 1,
                'recited_portion' => "من الصفحة {$from} إلى الصفحة {$to}",
                'from_page' => $from,
                'to_page' => $to,
                'result' => null,
                'notes' => $data['notes'] ?? null,
                'word_statuses' => null,
            ]);

            $this->audit->logModel('quran_training.item_partial_listened', $item, actor: $actor);

            if ($program) {
                $this->sync($program);
            }

            return $session;
        });
    }

    /**
     * إنشاء جلسة تسميع «جديد» على صفحات الجزء كاملة (بلا تحديث حالة العنصر).
     * مشتركة بين دورة التأهيلي والإجازة.
     *
     * @param  array{date?: ?string, notes?: ?string, word_statuses?: array<string, string>}  $data
     */
    public function createTasmeeSession(QuranListeningProgramItem $item, array $data, string $teacherId): QuranRecitationSession
    {
        $program = $item->program()->first();
        $errors = TasmeePageInput::errorStatuses($data['word_statuses'] ?? null);

        return QuranRecitationSession::create([
            'student_id' => $program?->student_id,
            'teacher_id' => $teacherId,
            'program_batch_id' => $item->batch_id,
            'type' => QuranTasmeeType::New,
            'date' => $data['date'] ?? now()->toDateString(),
            'amount' => $item->pagesCount(),
            'recited_portion' => "من الصفحة {$item->from_page} إلى الصفحة {$item->to_page}",
            'from_page' => $item->from_page,
            'to_page' => $item->to_page,
            'result' => QuranTasmeeResult::fromMastery($this->masteryFor($item, $errors)),
            'notes' => $data['notes'] ?? null,
            'word_statuses' => $errors,
        ]);
    }

    /**
     * ربط جلسة تسميع مسجّلة من الشاشة العامة بدورة التأهيلي/الإجازة: تُحدَّث
     * العناصر المغطاة بالكامل وتُشتق حالة الدفعة.
     */
    public function linkTasmeeSession(QuranRecitationSession $session, ?User $actor = null): void
    {
        if (! $session->program_batch_id || $session->type !== QuranTasmeeType::New) {
            return;
        }

        $batch = QuranListeningProgramBatch::query()->find($session->program_batch_id);

        if (! $batch) {
            return;
        }

        $program = $batch->program()->first();

        if (! $program || ! $this->supports($program) || ! $program->isActive()) {
            return;
        }

        if ((int) $session->from_page <= (int) $session->to_page) {
            foreach ($batch->items()->get() as $item) {
                $coversItem = (int) $session->from_page <= $item->from_page
                    && (int) $session->to_page >= $item->to_page;

                if (! $coversItem || $item->isPassed()) {
                    continue;
                }

                $item->update([
                    'status' => QuranListeningItemStatus::Listened,
                    'listened_at' => $session->date?->startOfDay() ?? now(),
                    'listened_by' => $actor?->id ?? $item->listened_by,
                    'quran_recitation_session_id' => $session->id,
                ]);
            }
        }

        $this->sync($program);
    }

    /** فرض شروط فتح الاختبار التراكمي: كل أجزاء الدفعة مسمّعة (والراسب أعيد تسميعه). */
    public function assertBatchTestAllowed(QuranListeningProgramBatch $batch): void
    {
        $program = $batch->program()->first();

        if (! $program || ! $program->isActive()) {
            throw ValidationException::withMessages(['results' => ['لا يمكن تسجيل اختبار لبرنامج غير نشط']]);
        }

        if ($batch->isPassed()) {
            throw ValidationException::withMessages(['results' => ['هذه الدفعة مجتازة مسبقاً']]);
        }

        if ($batch->isLocked()) {
            throw ValidationException::withMessages(['results' => ['هذه الدفعة مقفلة — اجتز اختبار الدفعة السابقة أولاً']]);
        }

        $this->sync($program);
        $batch->refresh();

        if (! $batch->isReadyForTest()) {
            throw ValidationException::withMessages([
                'results' => ['لا يُفتح الاختبار التراكمي قبل اكتمال تسميع أجزاء الدفعة الخمسة كاملة'],
            ]);
        }
    }

    /**
     * نطاق أجزاء الاختبار: الأجزاء الراسبة فقط عند وجود اختبار راسب سابق،
     * وإلا النطاق التراكمي من الجزء 1 إلى آخر جزء في الدفعة. ومع
     * scope=full بعد الرسوب يُعاد الاختبار التراكمي كاملاً (1..آخر جزء).
     *
     * @return array<int, int>
     */
    public function testScopeJuzNumbers(QuranListeningProgramBatch $batch, ?string $scope = null): array
    {
        if ($scope === self::RETAKE_SCOPE_FULL && $this->fullRetakeAvailable($batch)) {
            return $this->cumulativeJuzNumbers($batch);
        }

        $failed = $this->failedJuzNumbers($batch);

        return $failed !== [] ? $failed : $this->cumulativeJuzNumbers($batch);
    }

    /**
     * هل للدفعة اختبار سابق بنطاق تراكمي كامل (1..آخر جزء)؟ يميّز الاختبار
     * التراكمي عن الاختبار المباشر (غير التراكمي) عند عرض خيار الإعادة.
     */
    public function hasCumulativeTest(QuranListeningProgramBatch $batch): bool
    {
        return QuranListeningTest::query()
            ->where('listening_batch_id', $batch->id)
            ->with('items:id,test_id,juz')
            ->get()
            ->contains(function (QuranListeningTest $test) use ($batch) {
                $juz = $test->items
                    ->pluck('juz')
                    ->map(fn ($value) => (int) $value)
                    ->sort()
                    ->values()
                    ->all();

                return $juz === range(1, $batch->to_juz);
            });
    }

    /** هل يتاح بعد الرسوب إعادة الاختبار التراكمي كاملاً (لا الراسب فقط)؟ */
    public function fullRetakeAvailable(QuranListeningProgramBatch $batch): bool
    {
        return $this->failedJuzNumbers($batch) !== [] && $this->hasCumulativeTest($batch);
    }

    /**
     * الأجزاء التراكمية للدفعة: من 1 إلى آخر جزء (دفعة 3 → 1..15).
     *
     * @return array<int, int>
     */
    public function cumulativeJuzNumbers(QuranListeningProgramBatch $batch): array
    {
        return range(1, $batch->to_juz);
    }

    /**
     * الأجزاء الراسبة في آخر اختبار للدفعة.
     *
     * @return array<int, int>
     */
    public function failedJuzNumbers(QuranListeningProgramBatch $batch): array
    {
        $test = $batch->last_test_id ? QuranListeningTest::query()->find($batch->last_test_id) : null;

        if (! $test || $test->isPass()) {
            return [];
        }

        return $test->items()
            ->where('result', QuranListeningTestResult::Fail)
            ->orderBy('juz')
            ->pluck('juz')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();
    }

    /**
     * تسجيل الاختبار التراكمي (أو إعادة اختبار الأجزاء الراسبة، أو إعادة
     * الاختبار كاملاً عبر scope=full): نتيجة لكل جزء من النطاق، والنجاح
     * وفق الدرجة وحد الجامع.
     *
     * @param  array<int|string, string>  $results  juz => pass|fail
     */
    public function recordBatchTest(QuranListeningProgramBatch $batch, array $results, User $actor, ?string $notes = null, ?string $scope = null): QuranListeningTest
    {
        $student = $batch->student()->first();

        if (! $student) {
            throw ValidationException::withMessages(['results' => ['الطالب غير موجود']]);
        }

        $this->assertBatchTestAllowed($batch);
        $batch->refresh();

        return $this->recordTest($batch, $student, $this->testScopeJuzNumbers($batch, $scope), $results, $actor, $notes);
    }

    /**
     * الاختبار المباشر للأجزاء المحفوظة مسبقاً: الدفعة الحالية ثم كل دفعة
     * تالية أجزاؤها الخمسة مسجّلة محفوظاً ولم تدخل مسار تسميع/اختبار سابق.
     * الاختبار لكل دفعة على أجزائها الخمسة (غير تراكمي)، والنجاح يثبّتها
     * والرسوب يعيد الأجزاء الراسبة فقط.
     */
    public function placementTestAllowed(QuranListeningProgramBatch $batch): bool
    {
        if ($batch->isPassed() || $batch->last_test_id !== null) {
            return false;
        }

        if ($batch->status !== QuranListeningBatchStatus::Listening) {
            return false;
        }

        if ($this->hasTasmeeSessions($batch)) {
            return false;
        }

        $student = $batch->student()->first();

        if (! $student) {
            return false;
        }

        $memorized = $this->memorization->memorizedJuzNumbers($student);

        foreach ($batch->juzNumbers() as $juz) {
            if (! in_array($juz, $memorized, true)) {
                return false;
            }
        }

        return true;
    }

    /** فرض شروط الاختبار المباشر مع تحديث حالات الدفعات أولاً. */
    public function assertPlacementTestAllowed(QuranListeningProgramBatch $batch): void
    {
        $program = $batch->program()->first();

        if (! $program || ! $program->isActive()) {
            throw ValidationException::withMessages(['results' => ['لا يمكن تسجيل اختبار لبرنامج غير نشط']]);
        }

        $this->sync($program);
        $batch->refresh();

        if (! $this->placementTestAllowed($batch)) {
            throw ValidationException::withMessages([
                'results' => ['الاختبار المباشر متاح فقط للأجزاء المسجّلة محفوظاً قبل بدء تسميع الدفعة — سجّل التسميع ثم الاختبار التراكمي'],
            ]);
        }
    }

    /**
     * نطاق الدفعات المتتالية المؤهلة للاختبار المباشر.
     *
     * @return array<int, array{batch_number: int, from_juz: int, to_juz: int, batch: ?QuranListeningProgramBatch}>
     */
    public function placementTestScope(QuranListeningProgramBatch $batch): array
    {
        if (! $this->placementTestAllowed($batch)) {
            return [];
        }

        $student = $batch->student()->first();

        if (! $student) {
            return [];
        }

        $memorized = $this->memorization->memorizedJuzNumbers($student);
        $program = $batch->program()->first();
        $existing = $program
            ? $program->batches()->get()->keyBy('batch_number')
            : collect();

        $scope = [];

        for ($number = $batch->batch_number; $number <= QuranListeningProgramBatch::TOTAL_BATCHES; $number++) {
            $range = QuranListeningProgramBatch::juzRange($number);

            foreach ([$range['from'], $range['to']] as $juz) {
                if (! in_array($juz, $memorized, true)) {
                    break 2;
                }
            }

            $row = $number === $batch->batch_number ? $batch : $existing->get($number);

            if ($number !== $batch->batch_number && $row && $this->hasPlacementTestHistory($row)) {
                break;
            }

            $scope[] = [
                'batch_number' => $number,
                'from_juz' => $range['from'],
                'to_juz' => $range['to'],
                'batch' => $row,
            ];
        }

        return $scope;
    }

    /** @return array<int, int> */
    public function placementTestJuzNumbers(QuranListeningProgramBatch $batch): array
    {
        $juz = [];

        foreach ($this->placementTestScope($batch) as $entry) {
            foreach (range($entry['from_juz'], $entry['to_juz']) as $number) {
                $juz[] = $number;
            }
        }

        return array_values(array_unique($juz));
    }

    /**
     * تسجيل الاختبار المباشر متعدد الدفعات.
     *
     * @param  array<int|string, string>  $results  juz => pass|fail
     * @return Collection<int, QuranListeningTest>
     */
    public function recordPlacementTest(QuranListeningProgramBatch $batch, array $results, User $actor, ?string $notes = null): Collection
    {
        $student = $batch->student()->first();

        if (! $student) {
            throw ValidationException::withMessages(['results' => ['الطالب غير موجود']]);
        }

        $this->assertPlacementTestAllowed($batch);
        $batch->refresh();

        $scope = $this->placementTestScope($batch);

        if ($scope === []) {
            throw ValidationException::withMessages([
                'results' => ['الاختبار المباشر متاح فقط للأجزاء المسجّلة محفوظاً قبل بدء تسميع الدفعة — سجّل التسميع ثم الاختبار التراكمي'],
            ]);
        }

        $this->assertPlacementResultsComplete($scope, $results);

        $tests = DB::transaction(function () use ($scope, $student, $results, $actor, $notes, $batch) {
            $tests = collect();

            foreach (array_reverse($scope) as $entry) {
                $row = $entry['batch'] ?? QuranListeningProgramBatch::create([
                    'program_id' => $batch->program_id,
                    'student_id' => $student->id,
                    'batch_number' => $entry['batch_number'],
                    'from_juz' => $entry['from_juz'],
                    'to_juz' => $entry['to_juz'],
                    'status' => QuranListeningBatchStatus::Listening,
                ]);

                $tests->push($this->recordTest(
                    $row,
                    $student,
                    range($entry['from_juz'], $entry['to_juz']),
                    $results,
                    $actor,
                    $notes,
                    notify: false,
                    sync: false,
                ));
            }

            $program = $batch->program()->first();

            if ($program) {
                $this->sync($program);
            }

            return $tests->reverse()->values();
        });

        $this->notifyPlacementResult($student, $tests);

        return $tests;
    }

    /**
     * ملخص نتيجة الاختبار المباشر لعرضه للمستخدم.
     *
     * @param  Collection<int, QuranListeningTest>  $tests
     * @return array{passed: bool, score: float, failed_juz: array<int, int>, scope_juz: array<int, int>, scope_label: string, batches: int, passed_batches: int}
     */
    public function placementTestSummary(Collection $tests): array
    {
        $items = $tests->flatMap(fn (QuranListeningTest $test) => $test->items);

        $scopeJuz = $items->pluck('juz')->map(fn ($juz) => (int) $juz)->unique()->sort()->values()->all();

        $failedJuz = $items
            ->filter(fn (QuranListeningTestItem $item) => $item->result === QuranListeningTestResult::Fail)
            ->pluck('juz')
            ->map(fn ($juz) => (int) $juz)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $passedJuz = $items
            ->filter(fn (QuranListeningTestItem $item) => $item->result === QuranListeningTestResult::Pass)
            ->count();

        return [
            'passed' => $tests->isNotEmpty() && $tests->every(fn (QuranListeningTest $test) => $test->isPass()),
            'score' => round($passedJuz / max(1, $items->count()) * 100, 2),
            'failed_juz' => $failedJuz,
            'scope_juz' => $scopeJuz,
            'scope_label' => $this->formatJuzRange($scopeJuz),
            'batches' => $tests->count(),
            'passed_batches' => $tests->filter(fn (QuranListeningTest $test) => $test->isPass())->count(),
        ];
    }

    /**
     * تسجيل اختبار على نطاق محدد: تطبيع النتائج، حساب الدرجة وحد النجاح،
     * ثم تمرير النتيجة إلى applyBatchOutcome.
     *
     * @param  array<int, int>  $scope
     * @param  array<int|string, string>  $results
     */
    private function recordTest(
        QuranListeningProgramBatch $batch,
        Student $student,
        array $scope,
        array $results,
        User $actor,
        ?string $notes,
        bool $notify = true,
        bool $sync = true,
    ): QuranListeningTest {
        $normalized = [];

        foreach ($scope as $juz) {
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

        return DB::transaction(function () use ($batch, $student, $normalized, $overall, $score, $passingPercentage, $actor, $notes, $notify, $sync) {
            $test = QuranListeningTest::create([
                'plan_id' => null,
                'batch_id' => null,
                'listening_batch_id' => $batch->id,
                'student_id' => $student->id,
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

            $this->applyBatchOutcome($batch, $test, $actor, $notify, $sync);

            return $test->load('items');
        });
    }

    /**
     * بيانات صفحة دورة التأهيلي/الإجازة كاملة (المتحكمات).
     *
     * @return array<string, mixed>
     */
    public function panelData(QuranListeningProgram $program): array
    {
        $program->load([
            'student:id,name,study_session_id',
            'enrollment:id,program_type,status',
        ]);

        $states = $this->sync($program);
        $currentBatch = null;

        foreach ($states as $state) {
            if ($state['status'] !== QuranListeningBatchStatus::Locked
                && $state['status'] !== QuranListeningBatchStatus::Passed) {
                $currentBatch = $state['batch'];

                break;
            }
        }

        $items = $program->items()->get()->keyBy('juz');
        $batchNumbers = $program->batches()->pluck('batch_number', 'id');

        $juzGrid = collect(range(1, QuranJuzMap::TOTAL_JUZ))->map(function (int $juz) use ($items, $batchNumbers) {
            /** @var QuranListeningProgramItem|null $item */
            $item = $items->get($juz);

            return [
                'juz' => $juz,
                'status' => $item?->status,
                'batch_number' => $item ? ($batchNumbers->get($item->batch_id) ?? QuranListeningProgramBatch::numberForJuz($juz)) : null,
            ];
        });

        $passedJuz = $items->filter(fn (QuranListeningProgramItem $item) => $item->isPassed())->count();
        $passedBatches = $states->filter(fn (array $state) => $state['status'] === QuranListeningBatchStatus::Passed)->count();

        $coverage = null;
        $failedJuz = [];
        $testScopeJuz = [];
        $retakeCoverage = [];
        $fullRetakeAvailable = false;
        $fullRetakeJuz = [];
        $coveredPages = [];
        $tasmeeSessions = collect();
        $placementTestAllowed = false;
        $placementTestScope = [];
        $placementTestJuz = [];

        if ($currentBatch) {
            $currentBatch->load([
                'items.listenedBy:id,name',
                'items.quranRecitationSession:id,result,date',
                'lastTest.items',
            ]);
            $coverage = $this->batchCoverage($currentBatch);
            $failedJuz = $this->failedJuzNumbers($currentBatch);
            $testScopeJuz = $this->testScopeJuzNumbers($currentBatch);
            $retakeCoverage = $this->retakeCoverage($currentBatch);
            $fullRetakeAvailable = $this->fullRetakeAvailable($currentBatch);
            $fullRetakeJuz = $fullRetakeAvailable ? $this->cumulativeJuzNumbers($currentBatch) : [];
            $coveredPages = $this->coveredPages($currentBatch);
            $tasmeeSessions = $this->tasmeeSessions($currentBatch);
            $placementTestAllowed = $this->placementTestAllowed($currentBatch);

            if ($placementTestAllowed) {
                $placementTestScope = $this->placementTestScope($currentBatch);
                $placementTestJuz = $this->placementTestJuzNumbers($currentBatch);
            }
        }

        $tests = $program->tests()
            ->with([
                'items',
                'examiner:id,name',
                'listeningBatch:id,batch_number,from_juz,to_juz',
            ])
            ->orderByDesc('tested_at')
            ->get();

        $student = $program->student;

        return [
            'program' => $program,
            'states' => $states,
            'currentBatch' => $currentBatch,
            'juzGrid' => $juzGrid,
            'summary' => [
                'total_juz' => QuranJuzMap::TOTAL_JUZ,
                'passed_juz' => $passedJuz,
                'passed_batches' => $passedBatches,
                'total_batches' => QuranListeningProgramBatch::TOTAL_BATCHES,
                'percentage' => (int) round($passedJuz / QuranJuzMap::TOTAL_JUZ * 100),
            ],
            'coverage' => $coverage,
            'failedJuz' => $failedJuz,
            'testScopeJuz' => $testScopeJuz,
            'retakeCoverage' => $retakeCoverage,
            'fullRetakeAvailable' => $fullRetakeAvailable,
            'fullRetakeJuz' => $fullRetakeJuz,
            'coveredPages' => $coveredPages,
            'tasmeeSessions' => $tasmeeSessions,
            'tests' => $tests,
            'memorizedJuz' => $student ? $this->memorization->memorizedJuzNumbers($student) : [],
            'placementTestAllowed' => $placementTestAllowed,
            'placementTestScope' => $placementTestScope,
            'placementTestJuz' => $placementTestJuz,
            'minimumPassingPercentage' => $this->settings->minimumPassingPercentage(),
        ];
    }

    /**
     * نتيجة اختبار الدفعة: النجاح يثبّت الدفعة (وكل أجزائها) ويفتح التالية
     * ويُتمّ الدورة عند الدفعة السادسة، والرسوب يُعيد الأجزاء الراسبة فقط.
     */
    public function applyBatchOutcome(
        QuranListeningProgramBatch $batch,
        QuranListeningTest $test,
        User $actor,
        bool $notify = true,
        bool $sync = true,
    ): void {
        $program = $batch->program()->first();

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

            if ($batch->batch_number === QuranListeningProgramBatch::TOTAL_BATCHES && $program) {
                $this->completeCycle($program, $actor);
            }

            if ($notify) {
                $this->notifyBatchResult($batch, $test, passed: true);
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

            if ($notify) {
                $this->notifyBatchResult($batch, $test, passed: false, failedJuz: $this->failedJuzNumbers($batch));
            }
        }

        if ($sync && $program) {
            $this->sync($program);
        }
    }

    /**
     * إتمام الدورة عند نجاح اختبار الدفعة السادسة (1–30):
     * - التأهيلي: إنهاء الالتحاق + ختم التأهيل + تحويل تلقائي للإجازة.
     * - الإجازة: إنهاء الدورة والالتحاق فقط بلا ختم ولا تحويل.
     * - القراءات: إنهاء الدورة والالتحاق وإشعار باسم القراءة بلا أي تحويل
     *   (القراءات مرحلة متقدمة اختيارية مستقلة عن رحلة البرامج).
     */
    private function completeCycle(QuranListeningProgram $program, User $actor): void
    {
        if (! $program->isCompleted()) {
            $program->update(['status' => QuranListeningProgramStatus::Completed]);

            $this->audit->logModel('quran_training.program_completed', $program, actor: $actor);
        }

        $student = $program->student()->first();
        $enrollment = $program->enrollment_id
            ? ProgramEnrollment::query()->find($program->enrollment_id)
            : null;

        if ($enrollment && $enrollment->isActive()) {
            $this->programs->completeCycleEnrollment($enrollment, $actor);
        }

        if (! $student) {
            return;
        }

        if ($program->type === ProgramType::Readings) {
            $this->notifications->notifyStudentCircle(
                $student,
                'اكتمل برنامج القراءات',
                'ما شاء الله! أتممت '.($program->readingLabel() ?? 'برنامج القراءات').' كاملة (اختبار 1–30).',
                route('student.quran-programs.index'),
            );

            return;
        }

        if ($program->type === ProgramType::Qualifying) {
            $ijazah = $this->programs->enrollIfAbsent(
                ProgramType::Ijazah,
                $student->id,
                Carbon::today()->format('Y-m-d'),
                $actor,
            );

            app(QuranListeningProgramService::class)->ensureForEnrollment($ijazah);

            $this->notifications->notifyStudentCircle(
                $student,
                'ختم البرنامج التأهيلي',
                'ما شاء الله! أتممت البرنامج التأهيلي كاملاً (اختبار 1–30) — تم ختم التأهيل والتحاقك ببرنامج الإجازة تلقائياً.',
                route('student.quran-programs.index'),
            );

            return;
        }

        $this->notifications->notifyStudentCircle(
            $student,
            'اكتمل برنامج الإجازة',
            'ما شاء الله! أتممت برنامج الإجازة كاملاً (اختبار 1–30).',
            route('student.quran-programs.index'),
        );
    }

    /** هل للدفعة تاريخ تسميع/اختبار يمنع ضمّها لنطاق الاختبار المباشر؟ */
    private function hasPlacementTestHistory(QuranListeningProgramBatch $batch): bool
    {
        return $batch->isPassed()
            || $batch->last_test_id !== null
            || $this->hasTasmeeSessions($batch);
    }

    /**
     * @param  array<int, array{batch_number: int, from_juz: int, to_juz: int, batch: ?QuranListeningProgramBatch}>  $scope
     * @param  array<int|string, string>  $results
     */
    private function assertPlacementResultsComplete(array $scope, array $results): void
    {
        foreach ($scope as $entry) {
            foreach (range($entry['from_juz'], $entry['to_juz']) as $juz) {
                $raw = $results[$juz] ?? $results[(string) $juz] ?? null;
                $result = QuranListeningTestResult::tryFrom(is_array($raw) ? (string) ($raw['result'] ?? '') : (string) $raw);

                if (! $result) {
                    throw ValidationException::withMessages([
                        'results' => ['حدّد نتيجة الجزء '.$juz.' (ناجح أو يحتاج إعادة)'],
                    ]);
                }
            }
        }
    }

    /**
     * اشتقاق حالة عنصر جزء واحد:
     * - الناجح يبقى ناجحاً.
     * - الراسب يبقى راسباً حتى يُعاد تسميعه (جلسة بعد تاريخ الرسوب).
     * - غير ذلك: التغطية الكاملة للتسميع → «تمت الجلسة»، وإلا «قيد التسميع».
     *
     * @param  array<int, array<string, mixed>>  $coverage
     * @param  array<int, array{0: int, 1: int}>  $afterIntervals
     */
    private function deriveItemStatus(
        QuranListeningProgramItem $item,
        array $coverage,
        array $afterIntervals,
        ?Carbon $lastFailedAt,
    ): QuranListeningItemStatus {
        if ($item->isPassed()) {
            return QuranListeningItemStatus::Passed;
        }

        $juzCoverage = $coverage[$item->juz] ?? null;
        $complete = (bool) ($juzCoverage['complete'] ?? false);

        if ($item->isNeedsRepeat()) {
            if ($lastFailedAt && $complete && $this->juzFullyCovered($afterIntervals, $item->juz)) {
                return QuranListeningItemStatus::Listened;
            }

            return QuranListeningItemStatus::NeedsRepeat;
        }

        return $complete
            ? QuranListeningItemStatus::Listened
            : QuranListeningItemStatus::Available;
    }

    /** @param array<int, array{0: int, 1: int}> $intervals */
    private function juzFullyCovered(array $intervals, int $juz): bool
    {
        $range = QuranJuzMap::pageRange($juz);

        for ($page = $range['from']; $page <= $range['to']; $page++) {
            if (! $this->pageInIntervals($intervals, $page)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, array{0: int, 1: int}> $intervals */
    private function pageInIntervals(array $intervals, int $page): bool
    {
        foreach ($intervals as [$from, $to]) {
            if ($page >= $from && $page <= $to) {
                return true;
            }
        }

        return false;
    }

    /** نسبة الإتقان من عدد الأخطاء المعلَّمة مقابل كلمات الجزء. */
    private function masteryFor(QuranListeningProgramItem $item, ?array $errors): float
    {
        $total = 0;

        foreach ($this->pages->ayahsForRange($item->from_page, $item->to_page) as $ayah) {
            foreach (explode(' ', $ayah->text) as $word) {
                if ($word !== '') {
                    $total++;
                }
            }
        }

        if ($total === 0) {
            return 100.0;
        }

        $errorCount = min($total, $errors ? count($errors) : 0);

        return round(max(0, $total - $errorCount) / $total * 100, 2);
    }

    /** قفل دفعة وعناصرها غير الناجحة. */
    private function lockBatch(QuranListeningProgramBatch $batch): void
    {
        if (! $batch->isLocked()) {
            $batch->update(['status' => QuranListeningBatchStatus::Locked]);
        }

        $batch->items()
            ->whereNotIn('status', [QuranListeningItemStatus::Passed->value, QuranListeningItemStatus::Locked->value])
            ->update(['status' => QuranListeningItemStatus::Locked]);
    }

    /** تثبيت كل عناصر دفعة مجتازة (اتساق البيانات). */
    private function markItemsPassed(QuranListeningProgramBatch $batch): void
    {
        $batch->items()
            ->where('status', '!=', QuranListeningItemStatus::Passed->value)
            ->update([
                'status' => QuranListeningItemStatus::Passed,
                'passed_at' => DB::raw('COALESCE(passed_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    private function notifyBatchResult(
        QuranListeningProgramBatch $batch,
        QuranListeningTest $test,
        bool $passed,
        array $failedJuz = [],
    ): void {
        $student = $batch->student()->first();

        if (! $student) {
            return;
        }

        $score = $test->score !== null ? $this->formatPercent((float) $test->score) : '—';
        $threshold = $test->passing_percentage !== null ? $this->formatPercent((float) $test->passing_percentage) : '—';

        $body = $passed
            ? 'ما شاء الله! اجتزت '.$batch->label().' بنسبة '.$score.' (حد النجاح '.$threshold.'). تم فتح الدفعة التالية إن وُجدت.'
            : 'نتيجة '.$batch->label().': '.$score.' — رسبت في الأجزاء: '.($failedJuz === [] ? '—' : implode('، ', $failedJuz)).'. أعد تسميع الأجزاء الراسبة ثم يُعاد اختبارها (حد النجاح '.$threshold.').';

        $this->notifications->notifyStudentCircle(
            $student,
            $passed ? 'نجاح في اختبار الدفعة' : 'اختبار الدفعة — يحتاج إعادة',
            $body,
            route('student.quran-programs.index'),
        );
    }

    /** @param Collection<int, QuranListeningTest> $tests */
    private function notifyPlacementResult(Student $student, Collection $tests): void
    {
        $summary = $this->placementTestSummary($tests);

        if ($summary['passed']) {
            $this->notifications->notifyStudentCircle(
                $student,
                'نجاح في الاختبار المباشر',
                'ما شاء الله! اجتزت الاختبار المباشر للأجزاء '.$summary['scope_label'].' ('.$summary['passed_batches'].' دفعة). تم فتح الدفعة التالية إن وُجدت.',
                route('student.quran-programs.index'),
            );

            return;
        }

        $this->notifications->notifyStudentCircle(
            $student,
            'نتيجة الاختبار المباشر',
            'نتيجة الاختبار المباشر للأجزاء '.$summary['scope_label'].': '.$this->formatPercent($summary['score']).' — ثُبّتت '.$summary['passed_batches'].' دفعة — رسب في الأجزاء: '.($summary['failed_juz'] === [] ? '—' : implode('، ', $summary['failed_juz'])).'.',
            route('student.quran-programs.index'),
        );
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

    /** وصف مختصر لنطاق أجزاء: «1–8» للنطاق المتصل وإلا قائمة. @param array<int, int> $juz */
    private function formatJuzRange(array $juz): string
    {
        $juz = collect($juz)->map(fn ($value) => (int) $value)->unique()->sort()->values()->all();

        if ($juz === []) {
            return '—';
        }

        if (count($juz) > 1 && $juz === range($juz[0], $juz[count($juz) - 1])) {
            return $juz[0].'–'.$juz[count($juz) - 1];
        }

        return implode('، ', $juz);
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
    }
}
