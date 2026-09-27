<?php

namespace App\Services;

use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranMemorizationBatchStatus;
use App\Models\QuranKhamsaReview;
use App\Models\QuranListeningPlan;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Models\StudentJuzMemorization;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * تجميع بيانات «دورة الدفعة الحالية» لطالب واحد: الحالات، الدفعة، مراجعة 5،
 * خطة الاستماع، تقدّم الحفظ، والسجل الزمني — مشتركة بين مركز «دفعات الحفظ»
 * وملف الطالب حتى لا يتفرّق منطق التحضير بين المتحكمات.
 */
class QuranBatchPanelService
{
    public function __construct(
        private readonly QuranMemorizationGatingService $gating,
        private readonly QuranTeacherTimelineService $timeline,
    ) {}

    /**
     * @param  callable(QuranReviewSession): string|null  $reviewShowUrl  رابط عرض جلسة الاستماع
     * @return array{
     *     states: Collection<int, array{batch_number: int, from_juz: int, to_juz: int, status: QuranMemorizationBatchStatus, batch: ?QuranMemorizationBatch}>,
     *     currentBatch: ?QuranMemorizationBatch,
     *     plan: ?QuranListeningPlan,
     *     review: ?QuranKhamsaReview,
     *     retakeReview: ?QuranKhamsaReview,
     *     failedJuz: array<int, int>,
     *     testScopeJuz: array<int, int>,
     *     listeningItems: Collection<int, mixed>,
     *     memorizedJuz: array<int, int>,
     *     listeningSessions: Collection<int, QuranReviewSession>,
     *     timeline: Collection<int, mixed>,
     *     memorizationProgress: ?array,
     *     placementTestAllowed: bool,
     *     placementTestScope: array<int, array{batch_number: int, from_juz: int, to_juz: int, batch: ?QuranMemorizationBatch}>,
     *     placementTestJuz: array<int, int>,
     *     cycleBlockedReason: ?string
     * }
     */
    public function forStudent(
        Student $student,
        ?User $actor = null,
        ?string $teacherId = null,
        ?callable $reviewShowUrl = null,
    ): array {
        $states = $this->gating->sync($student, $actor);
        $currentBatch = $this->currentBatchFromStates($states);
        $memorizedJuz = $this->gating->memorizedJuzNumbers($student);

        $timeline = $this->timeline->forStudent(
            student: $student,
            filter: QuranTeacherTimelineService::FILTER_LISTENING,
            teacherId: $teacherId,
            reviewShowUrl: $reviewShowUrl,
        );

        $plan = null;
        $review = null;
        $retakeReview = null;
        $failedJuz = [];
        $testScopeJuz = [];
        $listeningItems = collect();
        $listeningSessions = collect();
        $memorizationProgress = null;
        $placementTestAllowed = false;
        $placementTestScope = [];
        $placementTestJuz = [];
        $cycleBlockedReason = null;

        if ($currentBatch) {
            $currentBatch->load(['plan', 'review5', 'retakeReview5', 'lastTest']);
            $plan = $currentBatch->plan;
            $review = $currentBatch->review5;
            $retakeReview = $currentBatch->retakeReview5;
            $failedJuz = $this->gating->failedJuzNumbers($currentBatch);
            $testScopeJuz = $this->gating->testScopeJuzNumbers($currentBatch);
            $memorizationProgress = $this->gating->batchMemorizationProgress($currentBatch);
            $placementTestAllowed = $this->gating->placementTestAllowed($currentBatch);

            if ($placementTestAllowed) {
                $placementTestScope = $this->gating->placementTestScope($currentBatch);
                $placementTestJuz = collect($placementTestScope)
                    ->flatMap(fn (array $entry) => [$entry['from_juz'], $entry['to_juz']])
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            }

            if ($currentBatch->status === QuranMemorizationBatchStatus::PendingReview5 && ! $review && ! $plan) {
                if (! $student->study_session_id) {
                    $cycleBlockedReason = 'الطالب غير مسجّل في دوام — عيّن له دواماً ثم أعد المحاولة.';
                } elseif (! $this->gating->hasTeacherInSession($student)) {
                    $cycleBlockedReason = 'لا يوجد أستاذ نشط في دوام الطالب — عيّن أستاذاً للدوام ثم أعد المحاولة.';
                }
            }

            if ($plan) {
                $plan->load([
                    'student:id,name',
                    'teacher:id,name',
                    'studySession:id,name',
                    'createdBy:id,name',
                    'khamsaReview:id,student_id,status',
                    'items',
                    'items.passedBy:id,name',
                    'items.khamsaReviewItem',
                    'items.listeningSession:id,date,from_page,to_page',
                    'tests.items',
                    'tests.examiner:id,name',
                ]);

                $listeningItems = $plan->items->where('status', QuranListeningItemStatus::Listened);
            }

            if ($review) {
                $review->load([
                    'student:id,name',
                    'teacher:id,name',
                    'studySession:id,name',
                    'assignedBy:id,name',
                    'items' => fn ($query) => $query->orderBy('juz')->orderBy('khamsa'),
                    'items.completedBy:id,name',
                    'items.quranReviewSession:id,date,from_page,to_page,mastery_percentage',
                ]);
            }

            if ($retakeReview) {
                $retakeReview->load([
                    'student:id,name',
                    'teacher:id,name',
                    'studySession:id,name',
                    'assignedBy:id,name',
                    'items' => fn ($query) => $query->orderBy('juz')->orderBy('khamsa'),
                    'items.completedBy:id,name',
                    'items.quranReviewSession:id,date,from_page,to_page,mastery_percentage',
                ]);
            }

            $listeningSessions = QuranReviewSession::query()
                ->where('student_id', $student->id)
                ->when($teacherId !== null, fn ($query) => $query->where('teacher_id', $teacherId))
                ->orderByDesc('date')
                ->limit(10)
                ->get(['id', 'date', 'from_page', 'to_page', 'mastery_percentage']);
        }

        return [
            'states' => $states,
            'currentBatch' => $currentBatch,
            'plan' => $plan,
            'review' => $review,
            'retakeReview' => $retakeReview,
            'failedJuz' => $failedJuz,
            'testScopeJuz' => $testScopeJuz,
            'listeningItems' => $listeningItems,
            'memorizedJuz' => $memorizedJuz,
            'listeningSessions' => $listeningSessions,
            'timeline' => $timeline,
            'memorizationProgress' => $memorizationProgress,
            'placementTestAllowed' => $placementTestAllowed,
            'placementTestScope' => $placementTestScope,
            'placementTestJuz' => $placementTestJuz,
            'cycleBlockedReason' => $cycleBlockedReason,
        ];
    }

    /**
     * ملخص «الدفعة الحالية» لمجموعة طلاب لجدول المركز: قراءة فقط بلا أي كتابة،
     * حتى يظهر كل طالب مسجّل فوراً ولو لم يبدأ حفظه بعد. تُحدَّث الصفوف فعلياً
     * عند فتح دورة الطالب (sync).
     *
     * @param  Collection<int, Student>  $students
     * @return array<string, array{batch_number: ?int, label: string, status: QuranMemorizationBatchStatus, batch: ?QuranMemorizationBatch, last_test: mixed, passed: int, completed: bool}>
     */
    public function summariesFor(Collection $students): array
    {
        $ids = $students->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $memorized = StudentJuzMemorization::query()
            ->whereIn('student_id', $ids)
            ->get(['student_id', 'juz'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->pluck('juz')->map(fn ($juz) => (int) $juz)->all());

        $batches = QuranMemorizationBatch::query()
            ->whereIn('student_id', $ids)
            ->with('lastTest:id,result,score,passing_percentage')
            ->get()
            ->groupBy('student_id');

        $summaries = [];

        foreach ($students as $student) {
            $summaries[$student->id] = $this->summarize(
                $batches->get($student->id, collect())->keyBy('batch_number'),
                $memorized->get($student->id, []),
            );
        }

        return $summaries;
    }

    /**
     * @param  Collection<int, QuranMemorizationBatch>  $rows
     * @param  array<int, int>  $memorizedJuz
     * @return array{batch_number: ?int, label: string, status: QuranMemorizationBatchStatus, batch: ?QuranMemorizationBatch, last_test: mixed, passed: int, completed: bool}
     */
    private function summarize(Collection $rows, array $memorizedJuz): array
    {
        $passed = 0;

        for ($number = 1; $number <= QuranMemorizationBatch::TOTAL_BATCHES; $number++) {
            $row = $rows->get($number);

            if ($row?->isPassed()) {
                $passed++;

                continue;
            }

            $range = QuranMemorizationBatch::juzRange($number);
            $bothMemorized = in_array($range['from'], $memorizedJuz, true)
                && in_array($range['to'], $memorizedJuz, true);

            $status = match (true) {
                ! $bothMemorized => QuranMemorizationBatchStatus::PendingMemorization,
                $row === null || $row->isLocked() => QuranMemorizationBatchStatus::PendingReview5,
                default => $row->status,
            };

            return [
                'batch_number' => $number,
                'label' => 'الدفعة '.$number.' (الجزآن '.$range['from'].'–'.$range['to'].')',
                'status' => $status,
                'batch' => $row,
                'last_test' => $this->latestTestAmong($rows, $row),
                'passed' => $passed,
                'completed' => false,
            ];
        }

        return [
            'batch_number' => null,
            'label' => 'أكمل الحفظ',
            'status' => QuranMemorizationBatchStatus::Passed,
            'batch' => null,
            'last_test' => $rows->get(QuranMemorizationBatch::TOTAL_BATCHES)?->lastTest,
            'passed' => $passed,
            'completed' => true,
        ];
    }

    /**
     * آخر اختبار مسجَّل في صفوف دفعات الطالب (الحالي ثم الأحدث السابق)،
     * ليظهر في الجدول حتى أثناء حفظ دفعة جديدة.
     *
     * @param  Collection<int, QuranMemorizationBatch>  $rows
     */
    private function latestTestAmong(Collection $rows, ?QuranMemorizationBatch $current): mixed
    {
        if ($current?->lastTest) {
            return $current->lastTest;
        }

        foreach ($rows->sortKeysDesc() as $row) {
            if ($row->lastTest) {
                return $row->lastTest;
            }
        }

        return null;
    }

    /** @param Collection<int, array{batch_number: int, status: QuranMemorizationBatchStatus, batch: ?QuranMemorizationBatch}> $states */
    public function currentBatchFromStates(Collection $states): ?QuranMemorizationBatch
    {
        foreach ($states as $state) {
            if ($state['status'] !== QuranMemorizationBatchStatus::Locked
                && $state['status'] !== QuranMemorizationBatchStatus::Passed) {
                return $state['batch'];
            }
        }

        return null;
    }

    /**
     * بيانات فارغة عند عدم اختيار طالب (نفس مفاتيح forStudent) حتى تبقى
     * واجهات القوائم قادرة على تمرير المتغيرات دون شروط إضافية.
     *
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return [
            'states' => collect(),
            'currentBatch' => null,
            'plan' => null,
            'review' => null,
            'retakeReview' => null,
            'failedJuz' => [],
            'testScopeJuz' => [],
            'listeningItems' => collect(),
            'memorizedJuz' => [],
            'listeningSessions' => collect(),
            'timeline' => collect(),
            'memorizationProgress' => null,
            'placementTestAllowed' => false,
            'placementTestScope' => [],
            'placementTestJuz' => [],
            'cycleBlockedReason' => null,
        ];
    }
}
