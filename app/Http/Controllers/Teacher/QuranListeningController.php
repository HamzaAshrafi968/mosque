<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\Quran\BuildListeningPlanItemsAction;
use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningTestResult;
use App\Models\QuranListeningPlan;
use App\Models\QuranListeningPlanItem;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Models\StudySession;
use App\Services\QuranAudioService;
use App\Services\QuranListeningService;
use App\Services\QuranScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «خطة الاستماع والاختبار» — لوحة الأستاذ: ينشئ الخطط لطلابه ضمن نطاقه،
 * يتابع الاستماع، ويسجّل اختبار الدفعة الذي يفتح ما بعدها.
 */
class QuranListeningController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranListeningService $listening,
        private readonly QuranAudioService $audio,
        private readonly QuranScopeService $scope,
        private readonly BuildListeningPlanItemsAction $planItems,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        // دُمجت «خطة الاستماع» في مركز «دفعات الحفظ».
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

        $builder = $student
            ? $this->listening->planBuilderData($student)
            : ['juzOptions' => $this->listening->juzOptions(), 'khamsat' => [], 'memorizedJuz' => []];

        return view('teacher.quran.listening.create', [
            'students' => $this->scope->studentsFor($teacher),
            'teachers' => collect([$teacher]),
            'sessions' => StudySession::query()
                ->whereIn('id', $sessionIds->unique())
                ->orderBy('name')
                ->get(['id', 'name']),
            'selectedStudent' => $student,
            'juzOptions' => $builder['juzOptions'],
            'khamsat' => $builder['khamsat'],
            'memorizedJuz' => $builder['memorizedJuz'],
            'currentSessionId' => config('app.current_study_session_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $data = $this->validated($request);
        $items = $this->planItems->selected($data['items'] ?? []);

        $student = Student::query()->findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);

        $plan = $this->listening->createPlan([
            'student_id' => $data['student_id'],
            'teacher_id' => $teacher->id,
            'study_session_id' => $data['study_session_id'],
            'title' => $data['title'] ?? null,
            'gate_size' => $data['gate_size'] ?? 1,
            'notes' => $data['notes'] ?? null,
            'items' => $items,
        ], $request->user());

        return redirect()
            ->route('teacher.quran.listening.show', $plan)
            ->with('success', 'تم إنشاء خطة الاستماع بنجاح ('.count($items).' عنصراً)');
    }

    public function show(Request $request, QuranListeningPlan $plan): View
    {
        $teacher = $this->currentTeacher($request);
        $this->assertOwnsPlan($plan, $teacher);

        return view('teacher.quran.listening.show', $this->showData($plan));
    }

    public function listen(Request $request, QuranListeningPlanItem $item): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertOwnsItemPlan($item, $teacher);

        $data = $request->validate([
            'quran_review_session_id' => ['nullable', 'uuid', 'exists:quran_review_sessions,id'],
        ]);

        $this->listening->markListened($item, $request->user(), $data['quran_review_session_id'] ?? null);

        return back()->with('success', 'تم تسجيل الاستماع لـ'.$item->label());
    }

    public function test(Request $request, QuranListeningPlan $plan): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertOwnsPlan($plan, $teacher);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'results' => ['required', 'array', 'min:1'],
            'results.*.result' => ['required', Rule::enum(QuranListeningTestResult::class)],
            'results.*.notes' => ['nullable', 'string', 'max:1000'],
            'results.*.quran_review_session_id' => ['nullable', 'uuid', 'exists:quran_review_sessions,id'],
        ]);

        $test = $this->listening->recordTest($plan, $data['results'], $request->user(), $data['notes'] ?? null);

        return back()->with('success', $test->isPass()
            ? 'تم تسجيل الاختبار: نجاح — وفُتحت الدفعة التالية إن وُجدت'
            : 'تم تسجيل الاختبار: بعض الأجزاء تحتاج إعادة');
    }

    public function cancel(Request $request, QuranListeningPlan $plan): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertOwnsPlan($plan, $teacher);

        $this->listening->cancelPlan($plan, $request->user());

        return back()->with('success', 'تم إلغاء خطة الاستماع');
    }

    public function audio(Request $request, QuranListeningPlanItem $item): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertOwnsItemPlan($item, $teacher);

        abort_if($item->isLocked(), 403, 'هذا الجزء مقفل — يُفتح بعد نجاح الأجزاء السابقة');

        return response()->json($this->audio->playlistForItem($item, $request->query('reciter')));
    }

    public function progress(Request $request, QuranListeningPlanItem $item): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertOwnsItemPlan($item, $teacher);

        $data = $request->validate(['seconds' => ['required', 'integer', 'min:0', 'max:3600']]);

        $this->listening->recordProgress($item, (int) $data['seconds']);

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function showData(QuranListeningPlan $plan): array
    {
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

        return [
            'plan' => $plan,
            'memorizedJuz' => $plan->student ? $this->listening->memorizedJuzNumbers($plan->student) : [],
            'listeningItems' => $plan->items->where('status', QuranListeningItemStatus::Listened),
            'listeningSessions' => QuranReviewSession::query()
                ->where('student_id', $plan->student_id)
                ->orderByDesc('date')
                ->limit(10)
                ->get(['id', 'date', 'from_page', 'to_page', 'mastery_percentage']),
            'reciters' => $this->audio->reciters(),
            'canTest' => ! QuranMemorizationBatch::query()->where('plan_id', $plan->id)->exists(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id');
        $items = $request->input('items');
        $items = is_array($items) ? $items : [];

        $rules = [
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'study_session_id' => ['required', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $tenantId)],
            'title' => ['nullable', 'string', 'max:150'],
            'gate_size' => ['nullable', 'integer', 'min:1', 'max:'.QuranListeningService::MAX_GATE_SIZE],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
        ];

        return $request->validate($rules + $this->planItems->rules($items));
    }

    private function assertOwnsPlan(QuranListeningPlan $plan, $teacher): void
    {
        abort_unless(
            (string) $plan->teacher_id === (string) $teacher->id,
            403,
            'لا تملك صلاحية الوصول لهذه الخطة'
        );
    }

    private function assertOwnsItemPlan(QuranListeningPlanItem $item, $teacher): void
    {
        $plan = $item->plan;

        abort_unless(
            $plan && (string) $plan->teacher_id === (string) $teacher->id,
            403,
            'هذا الجزء ليس ضمن خططك'
        );
    }
}
