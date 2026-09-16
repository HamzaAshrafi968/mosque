<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\QuranTasmeeResult;
use App\Models\QuranKhamsaReview;
use App\Models\QuranKhamsaReviewItem;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Models\StudySession;
use App\Services\QuranKhamsaService;
use App\Services\QuranMemorizationGatingService;
use App\Services\QuranScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «مراجعة 5» (الخمسات) — لوحة الأستاذ: يخصص لطلابه ضمن نطاقه وينهي خمساته.
 */
class QuranKhamsaController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranKhamsaService $khamsa,
        private readonly QuranScopeService $scope,
        private readonly QuranMemorizationGatingService $gating,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        // دُمجت «مراجعة 5» في مركز «دفعات الحفظ».
        return redirect()->route('teacher.quran.batches.index');
    }

    public function create(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $student = null;

        if ($request->filled('student_id')) {
            $student = Student::query()->find($request->input('student_id'));

            if ($student) {
                $this->scope->assertCanManageStudent($teacher, $student);
            }
        }

        $sessionIds = $teacher->studySessions()->pluck('study_sessions.id');

        if ($teacher->study_session_id) {
            $sessionIds->push($teacher->study_session_id);
        }

        return view('teacher.quran.khamsa.create', [
            'students' => $this->scope->studentsFor($teacher),
            'teachers' => collect([$teacher]),
            'sessions' => StudySession::query()
                ->whereIn('id', $sessionIds->unique())
                ->orderBy('name')
                ->get(['id', 'name']),
            'selectedStudent' => $student,
            'khamsat' => $student ? $this->khamsa->availableKhamsat($student) : [],
            'memorizedJuz' => $student ? $this->khamsa->memorizedJuzNumbers($student) : [],
            'currentSessionId' => config('app.current_study_session_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $data = $this->validated($request);

        $student = Student::query()->findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);

        $review = $this->khamsa->createReview([
            'student_id' => $data['student_id'],
            'teacher_id' => $teacher->id,
            'study_session_id' => $data['study_session_id'],
            'assigned_at' => $data['assigned_at'],
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'items' => $this->khamsaItems($data['items']),
        ], $request->user());

        return redirect()
            ->route('teacher.quran.khamsa.show', $review)
            ->with('success', 'تم تخصيص مراجعة 5 بنجاح ('.count($data['items']).' خمسة)');
    }

    public function show(Request $request, QuranKhamsaReview $review): View
    {
        $teacher = $this->currentTeacher($request);

        abort_unless((string) $review->teacher_id === (string) $teacher->id, 403, 'لا تملك صلاحية الوصول لهذه المراجعة');

        $review->load([
            'student:id,name',
            'teacher:id,name',
            'studySession:id,name',
            'assignedBy:id,name',
            'items' => fn ($query) => $query->orderBy('juz')->orderBy('khamsa'),
            'items.completedBy:id,name',
            'items.quranReviewSession:id,date,from_page,to_page,mastery_percentage',
        ]);

        return view('teacher.quran.khamsa.show', [
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
        $teacher = $this->currentTeacher($request);

        abort_unless((string) $item->teacher_id === (string) $teacher->id, 403, 'هذه الخمسة ليست ضمن مراجعاتك');

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
        $teacher = $this->currentTeacher($request);

        abort_unless((string) $review->teacher_id === (string) $teacher->id, 403, 'لا تملك صلاحية الوصول لهذه المراجعة');

        $this->khamsa->cancelReview($review, $request->user());

        return back()->with('success', 'تم إلغاء المراجعة');
    }

    public function storeMemorization(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'juz' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);

        $this->khamsa->recordMemorization($student, (int) $data['juz'], $request->user());
        $this->gating->sync($student, $request->user());

        return back()->with('success', 'تم تسجيل حفظ الجزء '.$data['juz']);
    }

    public function destroyMemorization(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'juz' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);

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
