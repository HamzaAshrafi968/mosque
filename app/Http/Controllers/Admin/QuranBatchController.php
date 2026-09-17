<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningTestResult;
use App\Enums\QuranMemorizationBatchStatus;
use App\Enums\QuranTasmeeResult;
use App\Http\Controllers\Controller;
use App\Models\QuranKhamsaReview;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Services\QuranAudioService;
use App\Services\QuranMemorizationGatingService;
use App\Services\QuranSettingsService;
use App\Services\QuranTeacherSessionService;
use App\Services\QuranTeacherTimelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «دفعات الحفظ» — مركز القرآن الموحّد لمدير الجامع:
 * دورة كل طالب (جزآن → مراجعة 5 → اختبار بحد نجاح الجامع → الدفعة التالية)،
 * مع تضمين «مراجعة 5» و«خطة الاستماع» و«الاستماع مع المعلم» في صفحة واحدة.
 */
class QuranBatchController extends Controller
{
    public function __construct(
        private readonly QuranMemorizationGatingService $gating,
        private readonly QuranSettingsService $settings,
        private readonly QuranAudioService $audio,
        private readonly QuranTeacherTimelineService $timeline,
        private readonly QuranTeacherSessionService $sessions,
    ) {}

    public function index(Request $request): View
    {
        $selected = $request->filled('student_id')
            ? Student::query()->find($request->input('student_id'))
            : null;

        $batches = QuranMemorizationBatch::query()
            ->with(['student:id,name', 'plan:id,title,status', 'review5:id,status', 'lastTest:id,result,score,passing_percentage'])
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->input('student_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when(config('app.current_study_session_id'), fn ($query) => $query->whereHas(
                'student',
                fn ($student) => $student->where('study_session_id', config('app.current_study_session_id'))
            ))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $states = collect();
        $currentBatch = null;
        $plan = null;
        $review = null;
        $retakeReview = null;
        $failedJuz = [];
        $testScopeJuz = [];
        $listeningItems = collect();
        $memorizedJuz = [];
        $listeningSessions = collect();
        $timelineItems = collect();
        $memorizationProgress = null;

        if ($selected) {
            $states = $this->gating->sync($selected, $request->user());
            $currentBatch = $this->currentBatchFromStates($states);
            $memorizedJuz = $this->gating->memorizedJuzNumbers($selected);

            $timelineItems = $this->timeline->forStudent(
                student: $selected,
                filter: QuranTeacherTimelineService::FILTER_LISTENING,
                reviewShowUrl: fn (QuranReviewSession $session) => route('admin.quran-review.show', $session->id),
            );

            if ($currentBatch) {
                $currentBatch->load(['plan', 'review5', 'retakeReview5', 'lastTest']);
                $plan = $currentBatch->plan;
                $review = $currentBatch->review5;
                $retakeReview = $currentBatch->retakeReview5;
                $failedJuz = $this->gating->failedJuzNumbers($currentBatch);
                $testScopeJuz = $this->gating->testScopeJuzNumbers($currentBatch);
                $memorizationProgress = $this->gating->batchMemorizationProgress($currentBatch);

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
                    ->where('student_id', $selected->id)
                    ->orderByDesc('date')
                    ->limit(10)
                    ->get(['id', 'date', 'from_page', 'to_page', 'mastery_percentage']);
            }
        }

        return view('admin.quran.batches.index', [
            'batches' => $batches,
            'selectedStudent' => $selected,
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
            'timeline' => $timelineItems,
            'memorizationProgress' => $memorizationProgress,
            'reciters' => $this->audio->reciters(),
            'khamsaReviewRoute' => fn (QuranKhamsaReview $review) => route('admin.quran.khamsa.review', $review),
            'tasmeeResults' => QuranTasmeeResult::cases(),
            'students' => Student::query()->active()->orderBy('name')->get(['id', 'name']),
            'statuses' => QuranMemorizationBatchStatus::cases(),
            'minimumPassingPercentage' => $this->settings->minimumPassingPercentage(),
        ]);
    }

    /** بوابة «التسميع مع المعلم» الموحدة: اختيار نوع الجلسة قبل فتح الـWorkflow المناسب. */
    public function sessionStart(Request $request): View
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);

        return view('quran.batches.session-type', [
            'student' => $student,
            'currentBatch' => $this->gating->currentBatch($student),
            'options' => $this->sessions->typesFor($request->user(), $student, 'admin.'),
            'backUrl' => route('admin.quran.batches.index', ['student_id' => $student->id]),
        ]);
    }

    public function repeat(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $this->gating->regenerateReview($batch, $request->user());

        return back()->with('success', 'تم إنشاء مراجعة 5 جديدة لـ'.$batch->label());
    }

    /** تسجيل نتيجة الاختبار التراكمي: نتيجة لكل جزء (ناجح/يحتاج إعادة). */
    public function test(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*' => ['required', Rule::enum(QuranListeningTestResult::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $test = $this->gating->recordCumulativeTest($batch, $data['results'], $request->user(), $data['notes'] ?? null);

        $score = $this->formatPercent((float) $test->score);

        if ($test->isPass()) {
            return back()->with('success', 'ما شاء الله — نجاح في الاختبار التراكمي بنسبة '.$score.'%. تم تثبيت الدفعة وفتح الدفعة التالية إن وُجدت.');
        }

        $failed = $test->items()
            ->where('result', QuranListeningTestResult::Fail)
            ->orderBy('juz')
            ->pluck('juz')
            ->implode('، ');

        return back()->with('success', 'نتيجة الاختبار التراكمي: '.$score.'% — رسب في الأجزاء: '.$failed.' — تم إنشاء خمسات إعادة للأجزاء الراسبة.');
    }

    /** خيارا ما بعد الرسوب: خمسات إعادة للأجزاء الراسبة فقط أو إعادة كامل النطاق. */
    public function retake(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['failed', 'full'])],
        ]);

        $juz = $data['mode'] === 'full'
            ? $this->gating->cumulativeJuzNumbers($batch)
            : $this->gating->failedJuzNumbers($batch);

        if ($juz === []) {
            return back()->with('success', 'لا توجد أجزاء راسبة — لا حاجة لخمسات إعادة.');
        }

        $this->gating->createRetakeReview($batch, $juz, $request->user());

        return back()->with('success', $data['mode'] === 'full'
            ? 'تم تخصيص خمسات إعادة للأجزاء كاملة (1–'.$batch->to_juz.')'
            : 'تم تخصيص خمسات إعادة للأجزاء الراسبة: '.implode('، ', $juz));
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /** @param Collection<int, array{batch_number: int, status: QuranMemorizationBatchStatus, batch: ?QuranMemorizationBatch}> $states */
    private function currentBatchFromStates(Collection $states): ?QuranMemorizationBatch
    {
        foreach ($states as $state) {
            if ($state['status'] !== QuranMemorizationBatchStatus::Locked
                && $state['status'] !== QuranMemorizationBatchStatus::Passed) {
                return $state['batch'];
            }
        }

        return null;
    }
}
