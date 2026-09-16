<?php

namespace App\Services;

use App\Enums\QuranKhamsaItemStatus;
use App\Enums\QuranKhamsaReviewStatus;
use App\Enums\QuranListeningPlanStatus;
use App\Enums\QuranMemorizationBatchStatus;
use App\Enums\QuranTasmeeType;
use App\Models\QuranCompletion;
use App\Models\QuranKhamsaReview;
use App\Models\QuranListeningPlan;
use App\Models\QuranListeningTest;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Models\StudentJuzMemorization;
use App\Models\Teacher;
use App\Models\User;
use App\Support\QuranJuzMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * دورة دفعات الحفظ (كل جزأين = دفعة):
 *
 *   حفظ الجزأين → مراجعة 5 (خطة استماع مولّدة تلقائياً) → اختبار بحد نجاح
 *   الجامع → نجاح → فتح الدفعة التالية.
 *
 * القواعد:
 * - الدفعة k لا تُفتح إلا بعد اجتياز الدفعة k-1 (والدفعة 1 مفتوحة دائماً).
 * - الحالة تُشتق من الأجزاء المحفوظة فعلياً + مراجعة 5 + آخر اختبار.
 * - لا توجد أي حالة خاصة لعدد أجزاء معيّن: الاشتقاق رياضي بحت (30 جزءاً = 15 دفعة).
 * - الـ Backend وحده يحدد النجاح: score (محسوبة من العناصر) >= حد النجاح.
 * - تسميع «جديد» ممنوع خارج نطاق الدفعة الحالية (منع فعلي لا إخفاء واجهة).
 */
class QuranMemorizationGatingService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
        private readonly QuranSettingsService $settings,
    ) {}

    /** @return array<int, int> أرقام الأجزاء المحفوظة للطالب. */
    public function memorizedJuzNumbers(Student $student): array
    {
        return StudentJuzMemorization::query()
            ->where('student_id', $student->id)
            ->orderBy('juz')
            ->pluck('juz')
            ->map(fn ($juz) => (int) $juz)
            ->all();
    }

    /**
     * مزامنة دفعات الطالب: إنشاء الصفوف المفتوحة، توليد مراجعة 5 والخطة عند
     * اكتمال الجزأين، واشتقاق الحالة من الإنجاز الفعلي. Idempotent.
     *
     * @return Collection<int, array{batch_number: int, from_juz: int, to_juz: int, status: QuranMemorizationBatchStatus, batch: ?QuranMemorizationBatch}>
     */
    public function sync(Student $student, ?User $actor = null): Collection
    {
        $memorized = $this->memorizedJuzNumbers($student);
        $existing = QuranMemorizationBatch::query()
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('batch_number');

        $states = collect();
        $prevPassed = true;

        for ($number = 1; $number <= QuranMemorizationBatch::TOTAL_BATCHES; $number++) {
            $range = QuranMemorizationBatch::juzRange($number);
            $row = $existing->get($number);
            $bothMemorized = in_array($range['from'], $memorized, true)
                && in_array($range['to'], $memorized, true);

            if (! $prevPassed) {
                if ($row && ! $row->isPassed() && ! $row->isLocked()) {
                    $row->update(['status' => QuranMemorizationBatchStatus::Locked]);
                }

                $states->push($this->state($number, $range, QuranMemorizationBatchStatus::Locked, $row));

                continue;
            }

            if ($row?->isPassed()) {
                $states->push($this->state($number, $range, QuranMemorizationBatchStatus::Passed, $row));
                $prevPassed = true;

                continue;
            }

            $prevPassed = false;

            if (! $row) {
                $row = QuranMemorizationBatch::create([
                    'student_id' => $student->id,
                    'batch_number' => $number,
                    'from_juz' => $range['from'],
                    'to_juz' => $range['to'],
                    'status' => $bothMemorized
                        ? QuranMemorizationBatchStatus::PendingReview5
                        : QuranMemorizationBatchStatus::PendingMemorization,
                ]);

                $this->audit->logModel('memorization_batch.opened', $row, actor: $actor);
            }

            if (! $bothMemorized) {
                if (! $row->isLocked() && $row->status !== QuranMemorizationBatchStatus::PendingMemorization) {
                    $row->update(['status' => QuranMemorizationBatchStatus::PendingMemorization]);
                }

                $states->push($this->state($number, $range, QuranMemorizationBatchStatus::PendingMemorization, $row));

                continue;
            }

            $this->ensureCycle($row, $student, $actor);

            $test = $row->last_test_id ? $this->testFor($row) : null;

            if ($test?->isPass()) {
                if (! $row->isPassed()) {
                    $row->update([
                        'status' => QuranMemorizationBatchStatus::Passed,
                        'passed_at' => $row->passed_at ?? now(),
                    ]);
                }

                $status = QuranMemorizationBatchStatus::Passed;
                $prevPassed = true;
            } elseif ($test) {
                $status = QuranMemorizationBatchStatus::NeedsRepeat;

                if ($row->status !== $status) {
                    $row->update(['status' => $status]);
                }
            } elseif ($this->reviewFor($row)?->isCompleted()) {
                $status = QuranMemorizationBatchStatus::ReadyForTest;

                if ($row->status !== $status) {
                    $row->update(['status' => $status]);
                }
            } else {
                $status = QuranMemorizationBatchStatus::PendingReview5;

                if ($row->status !== $status) {
                    $row->update(['status' => $status]);
                }
            }

            $states->push($this->state($number, $range, $status, $row));
        }

        return $states;
    }

    /** الدفعة التي يعمل عليها الطالب الآن (أول دفعة مفتوحة غير مجتازة). */
    public function currentBatch(Student $student): ?QuranMemorizationBatch
    {
        return $this->currentBatchState($student)['batch'] ?? null;
    }

    /**
     * @return array{batch_number: int, from_juz: int, to_juz: int, status: QuranMemorizationBatchStatus, batch: ?QuranMemorizationBatch}|null
     */
    public function currentBatchState(Student $student): ?array
    {
        foreach ($this->sync($student) as $state) {
            if ($state['status'] !== QuranMemorizationBatchStatus::Locked
                && $state['status'] !== QuranMemorizationBatchStatus::Passed) {
                return $state;
            }
        }

        return null;
    }

    /**
     * منع تسجيل تسميع «جديد» خارج نطاق الدفعة الحالية — فرض على الـ Backend
     * حتى لو تجاوزت الواجهة القفل.
     */
    public function assertNewTasmeeAllowed(Student $student, ?int $fromPage, ?int $toPage): void
    {
        if ($fromPage === null || $toPage === null) {
            return;
        }

        $current = $this->currentBatch($student);

        if (! $current) {
            throw ValidationException::withMessages([
                'from_page' => ['لا يمكن تسجيل تسميع «جديد» — راجع دفعات الحفظ الحالية أولاً'],
            ]);
        }

        $range = $current->pagesRange();

        if ($fromPage < $range['from'] || $toPage > $range['to']) {
            throw ValidationException::withMessages([
                'from_page' => [
                    "تسميع «جديد» مسموح فقط داخل {$current->label()} (صفحات {$range['from']}–{$range['to']}) — أكمل مراجعة 5 واختبار الدفعة الحالية أولاً",
                ],
            ]);
        }
    }

    /** هل تستطيع الدفعة الحالية استقبال استماع/تسميع جديد؟ (لعرض الواجهات) */
    public function isNewTasmeeAllowed(Student $student, int $fromPage, int $toPage): bool
    {
        $current = $this->currentBatch($student);

        if (! $current) {
            return false;
        }

        $range = $current->pagesRange();

        return $fromPage >= $range['from'] && $toPage <= $range['to'];
    }

    /**
     * نطاقات الصفحات المغطاة بتسميع «جديد» (مدمجة ومرتّبة تصاعدياً).
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function coveredPageIntervals(string $studentId): array
    {
        $intervals = QuranRecitationSession::query()
            ->where('student_id', $studentId)
            ->where('type', QuranTasmeeType::New)
            ->whereNotNull('from_page')
            ->whereNotNull('to_page')
            ->get(['from_page', 'to_page'])
            ->map(fn (QuranRecitationSession $row) => [(int) $row->from_page, (int) $row->to_page])
            ->filter(fn (array $interval) => $interval[0] >= 1
                && $interval[1] <= QuranJuzMap::TOTAL_PAGES
                && $interval[0] <= $interval[1])
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

    /**
     * تقدّم حفظ الدفعة على مستوى الصفحات: الصفحات المغطاة فعلاً بتسميع
     * «جديد» داخل نطاق الدفعة، ونسبة كل جزء والدفعة، وأول صفحة متبقية.
     *
     * @return array{
     *     from: int, to: int, total: int, covered: int, remaining: int, percentage: float,
     *     next_page: ?int, covered_pages: array<int, int>,
     *     juz: array<int, array{juz: int, from: int, to: int, total: int, covered: int, percentage: float}>
     * }
     */
    public function batchMemorizationProgress(QuranMemorizationBatch $batch): array
    {
        $range = $batch->pagesRange();
        $intervals = $this->coveredPageIntervals($batch->student_id);

        $coveredPages = [];
        $juzRows = [];

        foreach ([$batch->from_juz, $batch->to_juz] as $juz) {
            $juzRange = QuranJuzMap::pageRange($juz);
            $juzCovered = 0;

            for ($page = $juzRange['from']; $page <= $juzRange['to']; $page++) {
                if ($this->pageCovered($intervals, $page)) {
                    $coveredPages[] = $page;
                    $juzCovered++;
                }
            }

            $juzTotal = $juzRange['to'] - $juzRange['from'] + 1;

            $juzRows[$juz] = [
                'juz' => $juz,
                'from' => $juzRange['from'],
                'to' => $juzRange['to'],
                'total' => $juzTotal,
                'covered' => $juzCovered,
                'percentage' => $juzTotal > 0 ? round($juzCovered / $juzTotal * 100, 2) : 0.0,
            ];
        }

        sort($coveredPages);

        $total = $range['to'] - $range['from'] + 1;
        $covered = count($coveredPages);
        $nextPage = null;

        for ($page = $range['from']; $page <= $range['to']; $page++) {
            if (! in_array($page, $coveredPages, true)) {
                $nextPage = $page;

                break;
            }
        }

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'total' => $total,
            'covered' => $covered,
            'remaining' => $total - $covered,
            'percentage' => $total > 0 ? round($covered / $total * 100, 2) : 0.0,
            'next_page' => $nextPage,
            'covered_pages' => $coveredPages,
            'juz' => $juzRows,
        ];
    }

    /**
     * الدفعة التي يقع نطاق صفحات كامل داخل حدودها (لربط تسميع «جديد»).
     * يعيد null إن كان النطاق غير صالح أو ممتداً على دفعتين.
     */
    public function batchForPageRange(Student $student, ?int $fromPage, ?int $toPage): ?QuranMemorizationBatch
    {
        if ($fromPage === null || $toPage === null || $fromPage < 1 || $toPage < $fromPage
            || $toPage > QuranJuzMap::TOTAL_PAGES) {
            return null;
        }

        $first = QuranMemorizationBatch::numberForJuz(QuranJuzMap::juzForPage($fromPage));
        $last = QuranMemorizationBatch::numberForJuz(QuranJuzMap::juzForPage($toPage));

        if ($first !== $last) {
            return null;
        }

        return QuranMemorizationBatch::query()
            ->where('student_id', $student->id)
            ->where('batch_number', $first)
            ->first();
    }

    /** هل الصفحة داخل أي نطاق مغطى؟ @param array<int, array{0: int, 1: int}> $intervals */
    private function pageCovered(array $intervals, int $page): bool
    {
        foreach ($intervals as [$from, $to]) {
            if ($page >= $from && $page <= $to) {
                return true;
            }
        }

        return false;
    }

    /** الدفعة المرتبطة بخطة استماع (إن كانت الخطة مولّدة لدورة دفعة). */
    public function batchForPlan(QuranListeningPlan $plan): ?QuranMemorizationBatch
    {
        return QuranMemorizationBatch::query()
            ->where('plan_id', $plan->id)
            ->first();
    }

    /**
     * نتيجة اختبار دفعة: يحفظ الـ Backend النتيجة وفق الدرجة المحسوبة من
     * العناصر وحد النجاح المخزَّن لقطةً على الاختبار، ثم يفتح الدفعة التالية
     * عند النجاح أو يبقيها مقفلة عند الحاجة لإعادة.
     */
    public function recordBatchOutcome(QuranMemorizationBatch $batch, QuranListeningTest $test, User $actor): void
    {
        $student = $this->studentFor($batch);

        if (! $student) {
            return;
        }

        if ($test->isPass()) {
            $batch->update([
                'status' => QuranMemorizationBatchStatus::Passed,
                'last_test_id' => $test->id,
                'passed_at' => now(),
            ]);

            $this->audit->logModel('memorization_batch.passed', $batch, actor: $actor);

            $this->completePendingKhamsat($batch, $actor);
            $this->completePlan($test, $actor);

            if ((int) $batch->batch_number === QuranMemorizationBatch::TOTAL_BATCHES) {
                $this->openCompletion($batch, $student, $actor);
            }

            $this->notifyBatchResult($batch, $test, passed: true);
        } else {
            $batch->update([
                'status' => QuranMemorizationBatchStatus::NeedsRepeat,
                'last_test_id' => $test->id,
            ]);

            $this->audit->logModel('memorization_batch.needs_repeat', $batch, actor: $actor);

            $this->notifyBatchResult($batch, $test, passed: false);
        }

        $this->sync($student, $actor);
    }

    /**
     * خيار «مراجعة 5 جديدة» بعد الرسوب: إلغاء الدورة الحالية للدفعة وإعادة
     * توليدها (خطة + مراجعة 5 جديدة) من الصفر.
     */
    public function regenerateReview(QuranMemorizationBatch $batch, User $actor): QuranMemorizationBatch
    {
        $batch->refresh();

        if ($batch->isPassed()) {
            throw ValidationException::withMessages(['batch' => ['لا يمكن إعادة مراجعة دفعة مجتازة']]);
        }

        $plan = $this->planFor($batch);

        if ($plan && $plan->isActive()) {
            app(QuranListeningService::class)->cancelPlan($plan, $actor);
        }

        $review = $this->reviewFor($batch);

        if ($review && ! $review->isCompleted() && ! $review->isCancelled()) {
            app(QuranKhamsaService::class)->cancelReview($review, $actor);
        }

        $batch->update([
            'status' => QuranMemorizationBatchStatus::PendingReview5,
            'plan_id' => null,
            'review_5_id' => null,
            'last_test_id' => null,
            'passed_at' => null,
        ]);

        $this->audit->logModel('memorization_batch.review_regenerated', $batch, actor: $actor);

        $student = $this->studentFor($batch);

        if ($student) {
            $this->sync($student, $actor);
        }

        return $batch->refresh();
    }

    /**
     * ضمان وجود دورة (خطة استماع + مراجعة 5) للدفعة المكتملة الحفظ، مرة واحدة.
     * قد يفشل التوليد إن لم يتوفر أستاذ/دوام أو كانت الخمسات قيد مراجعة يدوية.
     */
    private function ensureCycle(QuranMemorizationBatch $batch, Student $student, ?User $actor): void
    {
        DB::transaction(function () use ($batch, $student) {
            $locked = QuranMemorizationBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return;
            }

            $plan = $this->planFor($locked);

            if ($plan && $plan->isActive()) {
                if ($locked->review_5_id !== $plan->khamsa_review_id) {
                    $locked->update(['review_5_id' => $plan->khamsa_review_id]);
                }

                return;
            }

            $review = $this->reviewFor($locked);

            if ($review && $review->status === QuranKhamsaReviewStatus::Pending && ! $locked->plan_id) {
                return;
            }

            if (! $student->study_session_id) {
                return;
            }

            $teacher = $this->resolveTeacher($student);

            if (! $teacher) {
                return;
            }

            // دورة الدفعة تُنسب دائماً لكادر الجامع (الأستاذ/المدير) وليس لعارض الصفحة.
            $actor = $teacher->user ?? $this->resolveManager($student);

            if (! $actor) {
                return;
            }

            $items = [];

            foreach ([$locked->from_juz, $locked->to_juz] as $juz) {
                for ($khamsa = 1; $khamsa <= QuranJuzMap::KHAMSAT_PER_JUZ; $khamsa++) {
                    $items[] = ['type' => 'review', 'juz' => $juz, 'khamsa' => $khamsa];
                }
            }

            try {
                $plan = app(QuranListeningService::class)->createPlan([
                    'student_id' => $student->id,
                    'teacher_id' => $teacher->id,
                    'study_session_id' => $student->study_session_id,
                    'title' => 'مراجعة 5 واختبار '.$locked->label(),
                    'gate_size' => count($items),
                    'notes' => 'توليد تلقائي من دورة دفعات الحفظ',
                    'items' => $items,
                ], $actor);
            } catch (ValidationException) {
                return;
            }

            $locked->update([
                'plan_id' => $plan->id,
                'review_5_id' => $plan->khamsa_review_id,
            ]);

            $this->audit->logModel('memorization_batch.cycle_generated', $locked, actor: $actor);
        });
    }

    /** إنهاء الخمسات المتبقية عند اجتياز اختبار الدفعة. */
    private function completePendingKhamsat(QuranMemorizationBatch $batch, User $actor): void
    {
        $review = $this->reviewFor($batch);

        if (! $review || $review->isCancelled()) {
            return;
        }

        $pending = $review->items()
            ->where('status', QuranKhamsaItemStatus::Pending)
            ->get();

        foreach ($pending as $item) {
            app(QuranKhamsaService::class)->completeItem($item, [
                'notes' => 'اكتمل باجتياز اختبار الدفعة',
            ], $actor, syncPlan: false);
        }
    }

    /** إغلاق خطة الدفعة عند اجتياز الاختبار حتى لو تبقى عنصر راسب تاريخياً. */
    private function completePlan(QuranListeningTest $test, User $actor): void
    {
        $plan = QuranListeningPlan::query()->find($test->plan_id);

        if (! $plan || $plan->isCompleted() || $plan->isCancelled()) {
            return;
        }

        $plan->update([
            'status' => QuranListeningPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->audit->logModel('quran_listening.plan.completed', $plan, actor: $actor);
    }

    /** نجاح الدفعة الأخيرة (29–30): فتح طلب إتمام الحفظ والمسار التأهيلي. */
    private function openCompletion(QuranMemorizationBatch $batch, Student $student, User $actor): void
    {
        if (QuranCompletion::query()->where('student_id', $student->id)->exists()) {
            return;
        }

        app(QuranProgramService::class)->recordCompletion(
            $student,
            Carbon::today()->toDateString(),
            'اكتمل حفظ القرآن بإتمام جميع دفعات الحفظ ('.$batch->label().')',
            $actor
        );

        $this->audit->logModel('memorization_batch.journey_completed', $batch, actor: $actor);

        $this->notifications->notifyStudentCircle(
            $student,
            'اكتمل حفظ القرآن',
            'ما شاء الله! أتممت حفظ القرآن الكريم — بانتظار تأكيد الإدارة لبدء البرنامج التأهيلي.',
            route('student.quran-profile'),
            staffToo: true
        );
    }

    private function notifyBatchResult(QuranMemorizationBatch $batch, QuranListeningTest $test, bool $passed): void
    {
        $student = $this->studentFor($batch);

        if (! $student) {
            return;
        }

        $score = $test->score !== null ? $this->formatPercent((float) $test->score) : '—';
        $threshold = $test->passing_percentage !== null ? $this->formatPercent((float) $test->passing_percentage) : '—';

        $body = $passed
            ? 'ما شاء الله! اجتزت '.$batch->label().' بنسبة '.$score.' (حد النجاح '.$threshold.'). تم فتح الدفعة التالية إن وُجدت.'
            : 'نتيجة '.$batch->label().': '.$score.' — تحتاج إعادة (حد النجاح '.$threshold.').';

        $this->notifications->notifyStudentCircle(
            $student,
            $passed ? 'نجاح في اختبار الدفعة' : 'اختبار الدفعة — يحتاج إعادة',
            $body,
            route('student.quran-profile')
        );
    }

    /** أستاذ من دوام الطالب (يفضّل أستاذ آخر تسميع له). */
    private function resolveTeacher(Student $student): ?Teacher
    {
        $sessionId = $student->study_session_id;

        if (! $sessionId) {
            return null;
        }

        $teachers = Teacher::query()->where('is_active', true)->get();

        $lastTeacherId = QuranRecitationSession::query()
            ->where('student_id', $student->id)
            ->whereNotNull('teacher_id')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->value('teacher_id');

        if ($lastTeacherId) {
            $teacher = $teachers->firstWhere('id', $lastTeacherId);

            if ($teacher && $this->teacherInSession($teacher, $sessionId)) {
                return $teacher;
            }
        }

        return $teachers->first(fn (Teacher $teacher) => $this->teacherInSession($teacher, $sessionId));
    }

    private function teacherInSession(Teacher $teacher, string $sessionId): bool
    {
        return (string) $teacher->study_session_id === (string) $sessionId
            || $teacher->studySessions()->whereKey($sessionId)->exists();
    }

    private function resolveManager(Student $student): ?User
    {
        return User::query()
            ->where('tenant_id', $student->tenant_id)
            ->where('role', User::ROLE_ADMIN)
            ->orderBy('created_at')
            ->first();
    }

    private function planFor(QuranMemorizationBatch $batch): ?QuranListeningPlan
    {
        return $batch->plan_id
            ? QuranListeningPlan::withoutGlobalScope('study_session')->find($batch->plan_id)
            : null;
    }

    private function reviewFor(QuranMemorizationBatch $batch): ?QuranKhamsaReview
    {
        return $batch->review_5_id
            ? QuranKhamsaReview::withoutGlobalScope('study_session')->find($batch->review_5_id)
            : null;
    }

    private function testFor(QuranMemorizationBatch $batch): ?QuranListeningTest
    {
        return $batch->last_test_id
            ? QuranListeningTest::query()->find($batch->last_test_id)
            : null;
    }

    private function studentFor(QuranMemorizationBatch $batch): ?Student
    {
        return $batch->student_id
            ? Student::withoutGlobalScope('study_session')->find($batch->student_id)
            : null;
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
    }

    /**
     * @param  array{from: int, to: int}  $range
     * @return array{batch_number: int, from_juz: int, to_juz: int, status: QuranMemorizationBatchStatus, batch: ?QuranMemorizationBatch}
     */
    private function state(int $number, array $range, QuranMemorizationBatchStatus $status, ?QuranMemorizationBatch $batch): array
    {
        return [
            'batch_number' => $number,
            'from_juz' => $range['from'],
            'to_juz' => $range['to'],
            'status' => $status,
            'batch' => $batch,
        ];
    }
}
