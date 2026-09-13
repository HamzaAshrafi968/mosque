<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuranTasmeeResult;
use App\Enums\QuranTasmeeType;
use App\Http\Controllers\Controller;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\QuranPageService;
use App\Support\TasmeePageInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuranTasmeeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $sessions = QuranRecitationSession::query()
            ->with(['student:id,name', 'teacher:id,name'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->input('student_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('date', '<=', $request->input('date_to')))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.quran.tasmee.index', [
            'sessions' => $sessions,
            'students' => Student::query()->orderBy('name')->get(['id', 'name']),
            'types' => QuranTasmeeType::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.quran.tasmee.create', [
            'students' => Student::query()->active()->orderBy('name')->get(['id', 'name', 'classroom_id']),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'types' => QuranTasmeeType::cases(),
            'results' => QuranTasmeeResult::cases(),
            'presetStudentId' => $request->input('student_id'),
        ]);
    }

    public function review(Request $request, QuranPageService $pages, ?QuranRecitationSession $session = null): View|RedirectResponse
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        $data = $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'teacher_id' => ['required', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'type' => ['required', Rule::in(['new', 'revision'])],
            'date' => ['required', 'date'],
            'amount' => ['nullable', 'required_without:from_page', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', Rule::in(['excellent', 'very_good', 'good', 'needs_review'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...TasmeePageInput::rules(),
        ]);

        $from = (int) ($data['from_page'] ?? 0);
        $to = (int) ($data['to_page'] ?? 0);

        if ($from < 1 || $to < $from || ($to - $from + 1) > QuranPageService::MAX_REVIEW_PAGES) {
            return redirect()
                ->route($session ? 'admin.quran.tasmee.edit' : 'admin.quran.tasmee.create', $session ? [$session] : [])
                ->withInput()
                ->withErrors(['from_page' => 'حدد نطاق صفحات صحيحاً (حتى '.QuranPageService::MAX_REVIEW_PAGES.' صفحات) لفتح صفحة التسميع.']);
        }

        return view('admin.quran.tasmee.review', [
            'session' => $session,
            'student' => Student::findOrFail($data['student_id']),
            'teacher' => Teacher::findOrFail($data['teacher_id']),
            'type' => QuranTasmeeType::from($data['type']),
            'date' => $data['date'],
            'amount' => $data['amount'] ?? ($to - $from + 1),
            'recitedPortion' => $data['recited_portion'] ?? "من الصفحة {$from} إلى الصفحة {$to}",
            'notes' => $data['notes'] ?? null,
            'resultValue' => $data['result'] ?? null,
            'results' => QuranTasmeeResult::cases(),
            'pages' => $pages->pagesForRange($from, $to),
            'fromPage' => $from,
            'toPage' => $to,
            'statuses' => $session?->word_statuses ?? [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = TasmeePageInput::normalize($this->validated($request));

        $session = QuranRecitationSession::create([
            'student_id' => $data['student_id'],
            'teacher_id' => $data['teacher_id'],
            'type' => $data['type'],
            'date' => $data['date'],
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'from_page' => $data['from_page'] ?? null,
            'to_page' => $data['to_page'] ?? null,
            'result' => $data['result'] ?? null,
            'notes' => $data['notes'] ?? null,
            'word_statuses' => TasmeePageInput::errorStatuses($data['word_statuses'] ?? null),
        ]);

        $this->audit->logModel('quran.tasmee.created', $session, actor: $request->user());

        return redirect()
            ->route('admin.quran.tasmee.index', ['type' => $data['type']])
            ->with('success', 'تم تسجيل التسميع بنجاح');
    }

    public function edit(QuranRecitationSession $session): View
    {
        return view('admin.quran.tasmee.edit', [
            'session' => $session,
            'students' => Student::query()->orderBy('name')->get(['id', 'name']),
            'teachers' => Teacher::query()->orderBy('name')->get(['id', 'name']),
            'types' => QuranTasmeeType::cases(),
            'results' => QuranTasmeeResult::cases(),
        ]);
    }

    public function update(Request $request, QuranRecitationSession $session): RedirectResponse
    {
        $data = TasmeePageInput::normalize($this->validated($request));
        $before = $session->getAttributes();

        $session->update([
            'student_id' => $data['student_id'],
            'teacher_id' => $data['teacher_id'],
            'type' => $data['type'],
            'date' => $data['date'],
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'from_page' => $data['from_page'] ?? null,
            'to_page' => $data['to_page'] ?? null,
            'result' => $data['result'] ?? null,
            'notes' => $data['notes'] ?? null,
            'word_statuses' => TasmeePageInput::errorStatuses($data['word_statuses'] ?? null),
        ]);

        $this->audit->logModel('quran.tasmee.updated', $session, $before, actor: $request->user());

        if (($before['result'] ?? null) !== ($data['result'] ?? null)) {
            $this->audit->log(
                'quran.tasmee.result_changed',
                'quran_recitation_session',
                $session->id,
                $session->tenant_id,
                before: ['result' => $before['result'] ?? null],
                after: ['result' => $session->result?->value ?? null],
                actor: $request->user()
            );
        }

        return redirect()
            ->route('admin.quran.tasmee.index', ['type' => $session->type->value])
            ->with('success', 'تم تحديث التسميع');
    }

    private function validated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        return $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'teacher_id' => ['required', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'type' => ['required', Rule::in(['new', 'revision'])],
            'date' => ['required', 'date'],
            'amount' => ['nullable', 'required_without:from_page', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', Rule::in(['excellent', 'very_good', 'good', 'needs_review'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...TasmeePageInput::rules(),
            ...TasmeePageInput::wordStatusRules(),
        ]);
    }
}
