<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuranTasmeeResult;
use App\Http\Controllers\Controller;
use App\Models\QuranKhamsaReview;
use App\Models\QuranKhamsaReviewItem;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Services\QuranKhamsaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «مراجعة 5» (الخمسات) — لوحة مدير الجامع.
 */
class QuranKhamsaController extends Controller
{
    public function __construct(private readonly QuranKhamsaService $khamsa) {}

    public function index(Request $request): View
    {
        $reviews = QuranKhamsaReview::query()
            ->with(['student:id,name', 'teacher:id,name', 'studySession:id,name', 'items'])
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->input('student_id')))
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('teacher_id', $request->input('teacher_id')))
            ->when($request->filled('study_session_id'), fn ($query) => $query->where('study_session_id', $request->input('study_session_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('assigned_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.quran.khamsa.index', [
            'reviews' => $reviews,
            'students' => Student::query()->active()->orderBy('name')->get(['id', 'name']),
            'teachers' => Teacher::query()->orderBy('name')->get(['id', 'name']),
            'sessions' => StudySession::orderBy('name')->get(['id', 'name']),
        ]);
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

        return view('admin.quran.khamsa.show', [
            'review' => $review,
            'memorizedJuz' => $this->khamsa->memorizedJuzNumbers($review->student),
            'listeningSessions' => QuranReviewSession::query()
                ->where('student_id', $review->student_id)
                ->orderByDesc('date')
                ->limit(10)
                ->get(['id', 'date', 'from_page', 'to_page', 'mastery_percentage']),
            'results' => QuranTasmeeResult::cases(),
        ]);
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
