<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Quran\StartKhamsaReviewSessionAction;
use App\Enums\QuranTasmeeResult;
use App\Http\Controllers\Controller;
use App\Models\QuranKhamsaReview;
use App\Models\QuranKhamsaReviewItem;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Services\QuranKhamsaService;
use App\Services\QuranMemorizationGatingService;
use App\Services\QuranPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «مراجعة 5» (الخمسات) — لوحة مدير الجامع.
 */
class QuranKhamsaController extends Controller
{
    public function __construct(
        private readonly QuranKhamsaService $khamsa,
        private readonly QuranMemorizationGatingService $gating,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        // دُمجت «مراجعة 5» في مركز «دفعات الحفظ».
        return redirect()->route('admin.quran.batches.index');
    }

    public function create(Request $request): View
    {
        $student = $request->filled('student_id')
            ? Student::query()->find($request->input('student_id'))
            : null;

        return view('admin.quran.khamsa.create', [
            'students' => Student::query()->active()->orderBy('name')->get(['id', 'name', 'study_session_id']),
            'teachers' => Teacher::query()->orderBy('name')->get(['id', 'name', 'study_session_id']),
            'sessions' => StudySession::orderBy('name')->get(['id', 'name']),
            'selectedStudent' => $student,
            'khamsat' => $student ? $this->khamsa->availableKhamsat($student) : [],
            'memorizedJuz' => $student ? $this->khamsa->memorizedJuzNumbers($student) : [],
            'currentSessionId' => config('app.current_study_session_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $review = $this->khamsa->createReview([
            'student_id' => $data['student_id'],
            'teacher_id' => $data['teacher_id'],
            'study_session_id' => $data['study_session_id'],
            'assigned_at' => $data['assigned_at'],
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'items' => $this->khamsaItems($data['items']),
        ], $request->user());

        return redirect()
            ->route('admin.quran.khamsa.show', $review)
            ->with('success', 'تم تخصيص مراجعة 5 بنجاح ('.count($data['items']).' خمسة)');
    }

    public function show(QuranKhamsaReview $review): View
    {
        $review->load([
            'student:id,name',
            'teacher:id,name',
            'studySession:id,name',
            'assignedBy:id,name',
            'items' => fn ($query) => $query->orderBy('juz')->orderBy('khamsa'),
            'items.completedBy:id,name',
            'items.quranReviewSession:id,date,from_page,to_page,mastery_percentage',
        ]);

        $batch = QuranMemorizationBatch::query()
            ->where(function ($query) use ($review) {
                $query->where('review_5_id', $review->id)->orWhere('retake_review_id', $review->id);
            })
            ->first();

        return view('admin.quran.khamsa.show', [
            'review' => $review,
            'memorizedJuz' => $this->khamsa->memorizedJuzNumbers($review->student),
            'listeningSessions' => QuranReviewSession::query()
                ->where('student_id', $review->student_id)
                ->orderByDesc('date')
                ->limit(10)
                ->get(['id', 'date', 'from_page', 'to_page', 'mastery_percentage']),
            'results' => QuranTasmeeResult::cases(),
            'reviewRoute' => fn (QuranKhamsaReview $row) => route('admin.quran.khamsa.review', $row),
            'testUrl' => $batch?->isReadyForTest()
                ? route('admin.quran.batches.index', ['student_id' => $review->student_id]).'#batch-test'
                : null,
        ]);
    }

    /** شاشة مراجعة الخمسات المحددة: فتح القرآن عليها وتسجيل الأخطاء كلمة بكلمة. */
    public function review(Request $request, QuranKhamsaReview $review, QuranPageService $pages): View
    {
        $items = $this->khamsa->pendingSelection($review, (array) $request->input('items', []));
        $range = $this->khamsa->selectionPageRange($items);
        $this->khamsa->assertSelectionPageLimit($range);

        return view('quran.khamsa.review', [
            'review' => $review->load(['student:id,name', 'teacher:id,name']),
            'selectedItems' => $items,
            'pages' => $pages->pagesForRange($range['from'], $range['to']),
            'statuses' => [],
            'fromPage' => $range['from'],
            'toPage' => $range['to'],
            'date' => now()->toDateString(),
            'results' => QuranTasmeeResult::cases(),
            'storeRoute' => route('admin.quran.khamsa.review.store', $review),
            'backRoute' => route('admin.quran.khamsa.show', $review),
        ]);
    }

    /** حفظ المراجعة: جلسة «استماع مع المعلم» واحدة تُنهي كل الخمسات المحددة. */
    public function storeReview(Request $request, QuranKhamsaReview $review, StartKhamsaReviewSessionAction $action): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string', 'uuid'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'result' => ['nullable', Rule::enum(QuranTasmeeResult::class)],
            'word_statuses' => ['nullable', 'array', 'max:5000'],
            'word_statuses.*' => ['string', Rule::in(['correct', 'incorrect', 'hesitation', 'tajweed_error', 'added', 'forgotten', 'unreviewed'])],
        ]);

        $result = $action->execute($review, $data['items'], $data, $request->user(), $request);

        $message = 'تم إنهاء '.$result['completed'].' خمسة بجلسة استماع (إتقان '.$result['mastery_percentage'].'%)';

        $linkedToBatch = QuranMemorizationBatch::query()
            ->where(function ($query) use ($review) {
                $query->where('review_5_id', $review->id)->orWhere('retake_review_id', $review->id);
            })
            ->exists();

        if ($linkedToBatch && $review->refresh()->isCompleted()) {
            return redirect()
                ->to(route('admin.quran.batches.index', ['student_id' => $review->student_id]).'#batch-test')
                ->with('success', $message.' — سجّل نتيجة الاختبار');
        }

        return redirect()
            ->route('admin.quran.khamsa.show', $review)
            ->with('success', $message);
    }

    public function complete(Request $request, QuranKhamsaReviewItem $item): RedirectResponse
    {
        $data = $request->validate([
            'result' => ['nullable', Rule::enum(QuranTasmeeResult::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'quran_review_session_id' => ['nullable', 'uuid', 'exists:quran_review_sessions,id'],
        ]);

        $this->khamsa->completeItem($item, $data, $request->user());

        return back()->with('success', 'تم إنهاء الخمسة بنجاح');
    }

    public function cancel(Request $request, QuranKhamsaReview $review): RedirectResponse
    {
        $this->khamsa->cancelReview($review, $request->user());

        return back()->with('success', 'تم إلغاء المراجعة');
    }

    public function storeMemorization(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', config('app.current_tenant_id'))],
            'juz' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);

        $this->khamsa->recordMemorization($student, (int) $data['juz'], $request->user());
        $this->gating->sync($student, $request->user());

        return back()->with('success', 'تم تسجيل حفظ الجزء '.$data['juz']);
    }

    public function destroyMemorization(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', config('app.current_tenant_id'))],
            'juz' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);

        $this->khamsa->removeMemorization($student, (int) $data['juz'], $request->user());
        $this->gating->sync($student, $request->user());

        return back()->with('success', 'تم إلغاء تسجيل حفظ الجزء '.$data['juz']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id');

        return $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'teacher_id' => ['required', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'study_session_id' => ['required', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $tenantId)],
            'assigned_at' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:assigned_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string', 'regex:/^\d{1,2}:\d{1,2}$/'],
        ]);
    }

    /**
     * @param  array<int, string>  $keys  «جزء:خمسة»
     * @return array<int, array{juz: int, khamsa: int}>
     */
    private function khamsaItems(array $keys): array
    {
        return array_map(function (string $key) {
            [$juz, $khamsa] = explode(':', $key);

            return ['juz' => (int) $juz, 'khamsa' => (int) $khamsa];
        }, $keys);
    }
}
