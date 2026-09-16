<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Quran\BuildListeningPlanItemsAction;
use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningTestResult;
use App\Http\Controllers\Controller;
use App\Models\QuranListeningPlan;
use App\Models\QuranListeningPlanItem;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Services\QuranAudioService;
use App\Services\QuranListeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «خطة الاستماع والاختبار» — لوحة مدير الجامع: يحدد العناصر (جديد بنطاق
 * صفحات أو مراجعة 5 مدموجة)، يتابع الاستماع، ويسجّل اختبار الدفعة الذي يفتح
 * ما بعدها.
 */
class QuranListeningController extends Controller
{
    public function __construct(
        private readonly QuranListeningService $listening,
        private readonly QuranAudioService $audio,
        private readonly BuildListeningPlanItemsAction $planItems,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        // دُمجت «خطة الاستماع» في مركز «دفعات الحفظ».
        return redirect()->route('admin.quran.batches.index');
    }

    public function create(Request $request): View
    {
        $student = $request->filled('student_id')
            ? Student::query()->find($request->input('student_id'))
            : null;

        $builder = $student
            ? $this->listening->planBuilderData($student)
            : ['juzOptions' => $this->listening->juzOptions(), 'khamsat' => [], 'memorizedJuz' => []];

        return view('admin.quran.listening.create', [
            'students' => Student::query()->active()->orderBy('name')->get(['id', 'name', 'study_session_id']),
            'teachers' => Teacher::query()->orderBy('name')->get(['id', 'name', 'study_session_id']),
            'sessions' => StudySession::orderBy('name')->get(['id', 'name']),
            'selectedStudent' => $student,
            'juzOptions' => $builder['juzOptions'],
            'khamsat' => $builder['khamsat'],
            'memorizedJuz' => $builder['memorizedJuz'],
            'currentSessionId' => config('app.current_study_session_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $items = $this->planItems->selected($data['items'] ?? []);

        $plan = $this->listening->createPlan([
            'student_id' => $data['student_id'],
            'teacher_id' => $data['teacher_id'],
            'study_session_id' => $data['study_session_id'],
            'title' => $data['title'] ?? null,
            'gate_size' => $data['gate_size'] ?? 1,
            'notes' => $data['notes'] ?? null,
            'items' => $items,
        ], $request->user());

        return redirect()
            ->route('admin.quran.listening.show', $plan)
            ->with('success', 'تم إنشاء خطة الاستماع بنجاح ('.count($items).' عنصراً)');
    }

    public function show(QuranListeningPlan $plan): View
    {
        return view('admin.quran.listening.show', $this->showData($plan));
    }

    public function listen(Request $request, QuranListeningPlanItem $item): RedirectResponse
    {
        $data = $request->validate([
            'quran_review_session_id' => ['nullable', 'uuid', 'exists:quran_review_sessions,id'],
        ]);

        $this->listening->markListened($item, $request->user(), $data['quran_review_session_id'] ?? null);

        return back()->with('success', 'تم تسجيل الاستماع لـ'.$item->label());
    }

    public function test(Request $request, QuranListeningPlan $plan): RedirectResponse
    {
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
        $this->listening->cancelPlan($plan, $request->user());

        return back()->with('success', 'تم إلغاء خطة الاستماع');
    }

    public function audio(Request $request, QuranListeningPlanItem $item): JsonResponse
    {
        abort_if($item->isLocked(), 403, 'هذا الجزء مقفل — يُفتح بعد نجاح الأجزاء السابقة');

        return response()->json($this->audio->playlistForItem($item, $request->query('reciter')));
    }

    public function progress(Request $request, QuranListeningPlanItem $item): JsonResponse
    {
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
            'tests.testedBy:id,name',
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
            'teacher_id' => ['required', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'study_session_id' => ['required', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $tenantId)],
            'title' => ['nullable', 'string', 'max:150'],
            'gate_size' => ['nullable', 'integer', 'min:1', 'max:'.QuranListeningService::MAX_GATE_SIZE],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
        ];

        return $request->validate($rules + $this->planItems->rules($items));
    }
}
