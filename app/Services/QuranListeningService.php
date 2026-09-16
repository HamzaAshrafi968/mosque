<?php

namespace App\Services;

use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningItemType;
use App\Enums\QuranListeningPlanStatus;
use App\Enums\QuranListeningTestResult;
use App\Models\QuranListeningPlan;
use App\Models\QuranListeningPlanItem;
use App\Models\QuranListeningTest;
use App\Models\QuranListeningTestItem;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\User;
use App\Support\QuranJuzMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «خطة الاستماع والاختبار»:
 *
 * - الخطة عناصر مرتبة: «جديد» بنطاق صفحات داخل حدود الجزء، أو «مراجعة 5»
 *   (خمسة كاملة من جزء محفوظ) تُدمج مع «مراجعة 5» في رأس واحد مرتبط.
 * - تُفتح العناصر دفعةً دفعة بحجم gate_size، ويُختبر كل ما فُتح معاً.
 * - نجاح كل العناصر المفتوحة يفتح الدفعة التالية، وأي عنصر راسب يحتاج
 *   إعادة الاستماع ثم إعادة الاختبار قبل فتح ما بعده.
 * - نجاح عنصر «مراجعة 5» يُنهي الخمسة المرتبطة تلقائياً، وإنهاء الخمسة من
 *   شاشة «مراجعة 5» يسجّل استماع عنصر الخطة المقابل (مزامنة اتجاهين).
 */
class QuranListeningService
{
    public const MAX_GATE_SIZE = 10;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
        private readonly QuranKhamsaService $khamsa,
        private readonly QuranMemorizationGatingService $gating,
        private readonly QuranSettingsService $settings,
    ) {}

    /**
     * @param  array{student_id?: string, teacher_id?: string, study_session_id?: string, title?: ?string, gate_size?: int|string|null, notes?: ?string, items?: array<int|string, array{type?: string, juz?: int|string, khamsa?: int|string, from_page?: int|string, to_page?: int|string}>}  $data
     */
    public function createPlan(array $data, User $actor): QuranListeningPlan
    {
        $student = Student::query()->find($data['student_id'] ?? null);

        if (! $student) {
            throw ValidationException::withMessages(['student_id' => ['الطالب غير موجود']]);
        }

        $teacher = Teacher::query()->find($data['teacher_id'] ?? null);

        if (! $teacher) {
            throw ValidationException::withMessages(['teacher_id' => ['الأستاذ غير موجود']]);
        }

        $session = StudySession::query()->find($data['study_session_id'] ?? null);

        if (! $session) {
            throw ValidationException::withMessages(['study_session_id' => ['الدوام المحدد غير موجود']]);
        }

        if ((string) $student->study_session_id !== (string) $session->id) {
            throw ValidationException::withMessages(['study_session_id' => ['الطالب ليس ضمن الدوام المحدد']]);
        }

        $teacherInSession = (string) $teacher->study_session_id === (string) $session->id
            || $teacher->studySessions()->whereKey($session->id)->exists();

        if (! $teacherInSession) {
            throw ValidationException::withMessages(['teacher_id' => ['الأستاذ ليس ضمن الدوام المحدد']]);
        }

        $items = $this->normalizeItems($data['items'] ?? []);

        if ($items === []) {
            throw ValidationException::withMessages(['items' => ['اختر جزءاً واحداً على الأقل مع نطاق صفحاته']]);
        }

        $this->assertReviewItemsAllowed($student, $items);

        $gateSize = $this->normalizeGateSize($data['gate_size'] ?? null, count($items));

        return DB::transaction(function () use ($student, $teacher, $session, $items, $gateSize, $data, $actor) {
            $plan = QuranListeningPlan::create([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'study_session_id' => $session->id,
                'created_by' => $actor->id,
                'title' => $data['title'] ?? null,
                'gate_size' => $gateSize,
                'status' => QuranListeningPlanStatus::Active,
                'notes' => $data['notes'] ?? null,
            ]);

            /** @var array<string, QuranListeningPlanItem> $planItems */
            $planItems = [];

            foreach ($items as $index => $item) {
                $planItems[$this->itemKey($item['type'], $item['juz'], $item['khamsa'])] = QuranListeningPlanItem::create([
                    'plan_id' => $plan->id,
                    'position' => $index + 1,
                    'juz' => $item['juz'],
                    'type' => $item['type'],
                    'khamsa' => $item['khamsa'],
                    'from_page' => $item['from_page'],
                    'to_page' => $item['to_page'],
                    'status' => QuranListeningItemStatus::Locked,
                ]);
            }

            $this->attachKhamsaReview($plan, $items, $planItems, $student, $teacher, $session, $actor, $data['notes'] ?? null);

            $this->recomputeGating($plan);

            $this->audit->logModel('quran_listening.plan.created', $plan, actor: $actor);

            $first = $items[0];
            $firstLabel = $first['type'] === QuranListeningItemType::Review
                ? QuranJuzMap::khamsaLabel($first['juz'], $first['khamsa'])
                : 'الجزء '.$first['juz'];

            $this->notifications->notifyStudentCircle(
                $student,
                'خطة استماع جديدة',
                'تم تخصيص خطة استماع لك ('.count($items).' عنصراً). ابدأ بالاستماع لـ'.$firstLabel.'.',
                route('student.quran-profile')
            );

            return $plan->load('items');
        });
    }

    /**
     * دمج عناصر «مراجعة 5»: رأس مراجعة واحد مرتبط بالخطة + ربط كل عنصر خطة
     * بعنصر الخمسة المقابل، فتظهر الخمسات في شاشة «مراجعة 5» وتُزامن النتائج.
     *
     * @param  array<int, array{type: QuranListeningItemType, juz: int, khamsa: int, from_page: int, to_page: int}>  $items
     * @param  array<string, QuranListeningPlanItem>  $planItems
     */
    private function attachKhamsaReview(
        QuranListeningPlan $plan,
        array $items,
        array $planItems,
        Student $student,
        Teacher $teacher,
        StudySession $session,
        User $actor,
        ?string $notes,
    ): void {
        $reviewItems = collect($items)
            ->filter(fn (array $item) => $item['type'] === QuranListeningItemType::Review);

        if ($reviewItems->isEmpty()) {
            return;
        }

        $review = $this->khamsa->createReview([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'listening_plan_id' => $plan->id,
            'assigned_at' => now()->toDateString(),
            'notes' => $notes,
            'items' => $reviewItems
                ->map(fn (array $item) => ['juz' => $item['juz'], 'khamsa' => $item['khamsa']])
                ->values()
                ->all(),
        ], $actor);

        $plan->update(['khamsa_review_id' => $review->id]);

        $review->load('items');

        foreach ($review->items as $khamsaItem) {
            $key = $this->itemKey(QuranListeningItemType::Review, (int) $khamsaItem->juz, (int) $khamsaItem->khamsa);
            $planItems[$key]?->update(['khamsa_review_item_id' => $khamsaItem->id]);
        }
    }

    /** تحقق من أهلية عناصر المراجعة: الجزء محفوظ والخمسة غير مخصّصة مسبقاً. */
    private function assertReviewItemsAllowed(Student $student, array $items): void
    {
        $memorized = $this->khamsa->memorizedJuzNumbers($student);

        foreach ($items as $item) {
            if ($item['type'] !== QuranListeningItemType::Review) {
                continue;
            }

            if (! in_array($item['juz'], $memorized, true)) {
                throw ValidationException::withMessages([
                    'items' => ['لا يمكن إضافة مراجعة من الجزء '.$item['juz'].' قبل تسجيل حفظه للطالب'],
                ]);
            }

            if ($this->khamsa->isKhamsaPending($student, $item['juz'], $item['khamsa'])) {
                throw ValidationException::withMessages([
                    'items' => ['الخمسة '.$item['khamsa'].' من الجزء '.$item['juz'].' مخصّصة مسبقاً وقيد المراجعة'],
                ]);
            }
        }
    }

    private function itemKey(QuranListeningItemType $type, int $juz, int $khamsa): string
    {
        return $type->value.':'.$juz.':'.$khamsa;
    }

    /**
     * فتح الدفعة التالية: لا يُفتح جزء جديد إلا إذا لم يبقَ جزء مفتوح
     * غير ناجح (أي نجحت الدفعة الحالية بالكامل).
     */
    public function recomputeGating(QuranListeningPlan $plan): void
    {
        $items = $plan->items()->orderBy('position')->get();

        $open = $items
            ->filter(fn (QuranListeningPlanItem $item) => $item->isListened() || $item->canBeListened())
            ->count();

        if ($open > 0) {
            return;
        }

        $gateSize = max(1, (int) $plan->gate_size);
        $unlocked = 0;

        foreach ($items as $item) {
            if ($unlocked >= $gateSize) {
                break;
            }

            if ($item->isLocked()) {
                $item->update(['status' => QuranListeningItemStatus::Available]);
                $unlocked++;
            }
        }
    }

    /**
     * تسجيل استماع الطالب للعنصر (أو من ينوب عنه)، مع ربط اختياري بجلسة
     * «الاستماع مع المعلم» الخاصة بالطالب.
     */
    public function markListened(QuranListeningPlanItem $item, User $actor, ?string $quranReviewSessionId = null): void
    {
        $plan = $item->relationLoaded('plan')
            ? $item->plan
            : ($item->plan_id ? QuranListeningPlan::withoutGlobalScope('study_session')->find($item->plan_id) : null);

        if (! $plan || ! $plan->isActive()) {
            throw ValidationException::withMessages(['item' => ['لا يمكن تسجيل الاستماع في خطة غير نشطة']]);
        }

        if ($item->isPassed()) {
            throw ValidationException::withMessages(['item' => ['هذا الجزء ناجح مسبقاً']]);
        }

        if (! $item->canBeListened()) {
            throw ValidationException::withMessages(['item' => ['هذا الجزء مقفل — أكمل الأجزاء المفتوحة قبله أولاً']]);
        }

        $sessionId = $this->resolveListeningSession($plan, $quranReviewSessionId);

        $item->update([
            'status' => QuranListeningItemStatus::Listened,
            'listened_at' => now(),
            'listened_by' => $actor->id,
            'quran_review_session_id' => $sessionId ?? $item->quran_review_session_id,
        ]);

        $this->audit->logModel('quran_listening.item_listened', $item, actor: $actor);
    }

    /** التحقق أن جلسة «الاستماع مع المعلم» تخص طالب الخطة. */
    private function resolveListeningSession(QuranListeningPlan $plan, ?string $sessionId, string $errorKey = 'quran_review_session_id'): ?string
    {
        if ($sessionId === null || $sessionId === '') {
            return null;
        }

        $belongs = QuranReviewSession::query()
            ->whereKey($sessionId)
            ->where('student_id', $plan->student_id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                $errorKey => ['جلسة الاستماع المحددة لا تخص هذا الطالب'],
            ]);
        }

        return $sessionId;
    }

    /** تتبع اختياري لثواني التشغيل المتراكمة للجزء. */
    public function recordProgress(QuranListeningPlanItem $item, int $seconds): void
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
     * تسجيل اختبار دفعة: نتيجة لكل جزء مُستمع (ناجح/يحتاج إعادة)، ثم تُفتح
     * الدفعة التالية عند نجاح الجميع، وتُغلق الخطة عند نجاح كل الأجزاء.
     *
     * @param  array<string, array{result?: string, notes?: ?string, quran_review_session_id?: ?string}>  $results  item_id => نتيجة
     */
    public function recordTest(QuranListeningPlan $plan, array $results, User $actor, ?string $notes = null): QuranListeningTest
    {
        if (! $plan->isActive()) {
            throw ValidationException::withMessages(['results' => ['لا يمكن تسجيل اختبار لخطة غير نشطة']]);
        }

        $planItems = $plan->items()->with('khamsaReviewItem')->get()->keyBy('id');
        $submitted = [];

        foreach ($results as $itemId => $row) {
            $item = $planItems->get((string) $itemId);

            if (! $item) {
                throw ValidationException::withMessages(['results' => ['أحد الأجزاء المحددة لا يتبع هذه الخطة']]);
            }

            if (! $item->isListened()) {
                throw ValidationException::withMessages([
                    'results' => ['الجزء '.$item->juz.' غير جاهز للاختبار — سجّل الاستماع أولاً'],
                ]);
            }

            $result = QuranListeningTestResult::tryFrom((string) ($row['result'] ?? ''));

            if (! $result) {
                throw ValidationException::withMessages(['results' => ['نتيجة غير صحيحة للجزء '.$item->juz]]);
            }

            $sessionId = $this->resolveListeningSession($plan, $row['quran_review_session_id'] ?? null, 'results');

            $submitted[$item->id] = [
                'item' => $item,
                'result' => $result,
                'notes' => $row['notes'] ?? null,
                'quran_review_session_id' => $sessionId,
            ];
        }

        if ($submitted === []) {
            throw ValidationException::withMessages(['results' => ['لا توجد أجزاء جاهزة للاختبار — يجب تسجيل الاستماع أولاً']]);
        }

        $batch = $this->gating->batchForPlan($plan);

        if ($batch) {
            $listenedIds = $planItems
                ->filter(fn (QuranListeningPlanItem $item) => $item->isListened())
                ->keys()
                ->map(fn ($id) => (string) $id)
                ->all();

            if (array_diff($listenedIds, array_map('strval', array_keys($submitted))) !== []) {
                throw ValidationException::withMessages([
                    'results' => ['يجب تسجيل نتيجة لكل عنصر مُستمع قبل إنهاء اختبار الدفعة'],
                ]);
            }
        }

        $passedCount = collect($submitted)
            ->filter(fn (array $row) => $row['result'] === QuranListeningTestResult::Pass)
            ->count();

        $score = round($passedCount / max(1, count($submitted)) * 100, 2);
        $passingPercentage = $batch ? $this->settings->minimumPassingPercentage() : null;

        $overall = $batch
            ? ($score >= $passingPercentage ? QuranListeningTestResult::Pass : QuranListeningTestResult::Fail)
            : (collect($submitted)->every(fn (array $row) => $row['result'] === QuranListeningTestResult::Pass)
                ? QuranListeningTestResult::Pass
                : QuranListeningTestResult::Fail);

        return DB::transaction(function () use ($plan, $submitted, $overall, $actor, $notes, $batch, $score, $passingPercentage) {
            $test = QuranListeningTest::create([
                'plan_id' => $plan->id,
                'batch_id' => $batch?->id,
                'student_id' => $plan->student_id,
                'tested_by' => $actor->id,
                'tested_at' => now(),
                'result' => $overall,
                'score' => $batch ? $score : null,
                'passing_percentage' => $passingPercentage,
                'notes' => $notes,
            ]);

            foreach ($submitted as $row) {
                /** @var QuranListeningPlanItem $item */
                $item = $row['item'];
                $passed = $row['result'] === QuranListeningTestResult::Pass;

                QuranListeningTestItem::create([
                    'test_id' => $test->id,
                    'plan_item_id' => $item->id,
                    'juz' => $item->juz,
                    'from_page' => $item->from_page,
                    'to_page' => $item->to_page,
                    'result' => $row['result'],
                    'needs_repeat' => ! $passed,
                    'quran_review_session_id' => $row['quran_review_session_id'],
                    'notes' => $row['notes'],
                ]);

                $item->update([
                    'status' => $passed ? QuranListeningItemStatus::Passed : QuranListeningItemStatus::NeedsRepeat,
                    'last_result' => $row['result'],
                    'attempts' => $item->attempts + 1,
                    'passed_at' => $passed ? now() : null,
                    'passed_by' => $passed ? $actor->id : null,
                ]);

                if ($passed) {
                    $this->completeLinkedKhamsa($item, $row, $actor);
                }
            }

            $this->recomputeGating($plan);

            $this->audit->logModel('quran_listening.test.recorded', $test, actor: $actor);

            if ($batch) {
                $this->gating->recordBatchOutcome($batch, $test, $actor);
            } else {
                $this->notifyTestResult($plan, $test, $submitted);

                $this->maybeComplete($plan, $actor);
            }

            return $test->load('items');
        });
    }

    public function cancelPlan(QuranListeningPlan $plan, ?User $actor = null): void
    {
        if ($plan->isCompleted()) {
            throw ValidationException::withMessages(['plan' => ['لا يمكن إلغاء خطة مكتملة']]);
        }

        if ($plan->isCancelled()) {
            return;
        }

        $plan->update(['status' => QuranListeningPlanStatus::Cancelled]);

        $review = $plan->khamsaReview;

        if ($review && ! $review->isCompleted() && ! $review->isCancelled()) {
            $this->khamsa->cancelReview($review, $actor);
        }

        $this->audit->logModel('quran_listening.plan.cancelled', $plan, actor: $actor);
    }

    /** نجاح عنصر «مراجعة 5» في اختبار الخطة يُنهي الخمسة المرتبطة تلقائياً. */
    private function completeLinkedKhamsa(QuranListeningPlanItem $item, array $row, User $actor): void
    {
        if (! $item->isReview() || ! $item->khamsa_review_item_id) {
            return;
        }

        $khamsaItem = $item->khamsaReviewItem;

        if (! $khamsaItem || $khamsaItem->isCompleted()) {
            return;
        }

        $this->khamsa->completeItem($khamsaItem, [
            'result' => null,
            'notes' => 'ناجح في اختبار خطة الاستماع',
            'quran_review_session_id' => $row['quran_review_session_id'] ?? null,
        ], $actor);
    }

    /**
     * كل الأجزاء وأجزاء المصحف الثلاثين مع نطاق صفحاتها — لنموذج البناء.
     *
     * @return array<int, array{juz: int, from_page: int, to_page: int, pages: int}>
     */
    public function juzOptions(): array
    {
        return collect(range(1, QuranJuzMap::TOTAL_JUZ))
            ->map(function (int $juz) {
                $range = QuranJuzMap::pageRange($juz);

                return [
                    'juz' => $juz,
                    'from_page' => $range['from'],
                    'to_page' => $range['to'],
                    'pages' => $range['to'] - $range['from'] + 1,
                ];
            })
            ->all();
    }

    /**
     * بيانات نموذج البناء للطالب: الأجزاء الثلاثون مع نطاق صفحاتها وعلامة
     * «محفوظ»، وخمسات «مراجعة 5» المتاحة (المفتوحة وغير المخصّصة).
     *
     * @return array{juzOptions: array<int, array{juz: int, from_page: int, to_page: int, pages: int, memorized: bool}>, khamsat: array<int, array<string, mixed>>, memorizedJuz: array<int, int>}
     */
    public function planBuilderData(Student $student): array
    {
        $memorized = $this->khamsa->memorizedJuzNumbers($student);

        $juzOptions = collect($this->juzOptions())
            ->map(fn (array $row) => [...$row, 'memorized' => in_array($row['juz'], $memorized, true)])
            ->all();

        return [
            'juzOptions' => $juzOptions,
            'khamsat' => $this->khamsa->availableKhamsat($student),
            'memorizedJuz' => $memorized,
        ];
    }

    /** @return array<int, int> أرقام الأجزاء المحفوظة للطالب. */
    public function memorizedJuzNumbers(Student $student): array
    {
        return $this->khamsa->memorizedJuzNumbers($student);
    }

    /**
     * تنظيف عناصر الخطة والتحقق من نطاق الصفحات داخل كل جزء:
     * - «جديد»: نطاق صفحات يحدده المستخدم داخل حدود الجزء.
     * - «مراجعة 5»: نطاق الخمسة يُحسب تلقائياً من خريطة الأجزاء.
     *
     * @param  array<int|string, array{type?: string, juz?: int|string, khamsa?: int|string, from_page?: int|string, to_page?: int|string}>  $items
     * @return array<int, array{type: QuranListeningItemType, juz: int, khamsa: int, from_page: int, to_page: int}>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $key => $item) {
            $type = QuranListeningItemType::tryFrom((string) ($item['type'] ?? '')) ?? QuranListeningItemType::New;
            $juz = (int) ($item['juz'] ?? (is_string($key) ? $key : 0));

            if ($juz < 1 || $juz > QuranJuzMap::TOTAL_JUZ) {
                throw ValidationException::withMessages(['items' => ['رقم الجزء غير صحيح: '.$juz]]);
            }

            if ($type === QuranListeningItemType::Review) {
                $khamsa = (int) ($item['khamsa'] ?? 0);

                if ($khamsa < 1 || $khamsa > QuranJuzMap::KHAMSAT_PER_JUZ) {
                    throw ValidationException::withMessages([
                        'items' => ['رقم الخمسة غير صحيح في الجزء '.$juz.' — اختر خمسة من ١ إلى '.QuranJuzMap::KHAMSAT_PER_JUZ],
                    ]);
                }

                $range = QuranJuzMap::khamsaRange($juz, $khamsa);
                $normalizedKey = $this->itemKey($type, $juz, $khamsa);

                if (isset($normalized[$normalizedKey])) {
                    throw ValidationException::withMessages(['items' => ['الخمسة '.$khamsa.' من الجزء '.$juz.' مكررة في الخطة']]);
                }

                $normalized[$normalizedKey] = [
                    'type' => $type,
                    'juz' => $juz,
                    'khamsa' => $khamsa,
                    'from_page' => $range['from'],
                    'to_page' => $range['to'],
                ];

                continue;
            }

            $from = (int) ($item['from_page'] ?? 0);
            $to = (int) ($item['to_page'] ?? 0);

            $range = QuranJuzMap::pageRange($juz);

            if ($from < $range['from'] || $from > $range['to']) {
                throw ValidationException::withMessages([
                    'items' => ["صفحة البداية {$from} خارج نطاق الجزء {$juz} (صفحات {$range['from']}–{$range['to']})"],
                ]);
            }

            if ($to < $range['from'] || $to > $range['to']) {
                throw ValidationException::withMessages([
                    'items' => ["صفحة النهاية {$to} خارج نطاق الجزء {$juz} (صفحات {$range['from']}–{$range['to']})"],
                ]);
            }

            if ($from > $to) {
                throw ValidationException::withMessages([
                    'items' => ["صفحة البداية يجب ألا تتجاوز صفحة النهاية في الجزء {$juz}"],
                ]);
            }

            $normalizedKey = $this->itemKey($type, $juz, 0);

            if (isset($normalized[$normalizedKey])) {
                throw ValidationException::withMessages(['items' => ["الجزء {$juz} مكرر في الخطة"]]);
            }

            $normalized[$normalizedKey] = [
                'type' => $type,
                'juz' => $juz,
                'khamsa' => 0,
                'from_page' => $from,
                'to_page' => $to,
            ];
        }

        uasort($normalized, fn (array $a, array $b) => [$a['juz'], $a['type']->value, $a['khamsa']] <=> [$b['juz'], $b['type']->value, $b['khamsa']]);

        return array_values($normalized);
    }

    private function normalizeGateSize(int|string|null $value, int $itemsCount): int
    {
        $gate = max(1, min((int) ($value ?? 1), self::MAX_GATE_SIZE));

        return max(1, min($gate, $itemsCount));
    }

    /** @param array<string, array{item: QuranListeningPlanItem, result: QuranListeningTestResult}> $submitted */
    private function notifyTestResult(QuranListeningPlan $plan, QuranListeningTest $test, array $submitted): void
    {
        $student = $plan->student;

        if (! $student) {
            return;
        }

        $passed = collect($submitted)
            ->filter(fn (array $row) => $row['result'] === QuranListeningTestResult::Pass)
            ->map(fn (array $row) => $row['item']->label())
            ->implode('، ');

        $failed = collect($submitted)
            ->filter(fn (array $row) => $row['result'] === QuranListeningTestResult::Fail)
            ->map(fn (array $row) => $row['item']->label())
            ->implode('، ');

        $body = $failed === ''
            ? 'نتيجة اختبار الاستماع: نجحت '.$passed.'.'
            : 'نتيجة اختبار الاستماع: '.($passed === '' ? 'لم ينجح أي جزء' : 'نجحت '.$passed)
                .' — وتحتاج إعادة: '.$failed.'.';

        $this->notifications->notifyStudentCircle(
            $student,
            $test->isPass() ? 'نجاح في اختبار الاستماع' : 'اختبار الاستماع — يحتاج إعادة',
            $body,
            route('student.quran-profile')
        );
    }

    private function maybeComplete(QuranListeningPlan $plan, User $actor): void
    {
        $remaining = $plan->items()
            ->where('status', '!=', QuranListeningItemStatus::Passed)
            ->exists();

        if ($remaining) {
            return;
        }

        $plan->update([
            'status' => QuranListeningPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->audit->logModel('quran_listening.plan.completed', $plan, actor: $actor);

        if ($plan->student) {
            $this->notifications->notifyStudentCircle(
                $plan->student,
                'اكتملت خطة الاستماع',
                'ما شاء الله! أتممت خطة الاستماع ونجحت في جميع الأجزاء.',
                route('student.quran-profile')
            );
        }
    }
}
