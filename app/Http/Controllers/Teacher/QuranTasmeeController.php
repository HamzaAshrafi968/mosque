<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\QuranTasmeeResult;
use App\Enums\QuranTasmeeType;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\QuranScopeService;
use App\Support\TasmeePageInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuranTasmeeController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranScopeService $scope,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $sessions = QuranRecitationSession::query()
            ->with(['student:id,name', 'teacher:id,name'])
            ->where('teacher_id', $teacher->id)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->input('student_id')))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('teacher.quran.tasmee.index', [
            'sessions' => $sessions,
            'students' => $this->scope->studentsFor($teacher),
            'types' => QuranTasmeeType::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        return view('teacher.quran.tasmee.create', [
            'students' => $this->scope->studentsFor($teacher),
            'types' => QuranTasmeeType::cases(),
            'results' => QuranTasmeeResult::cases(),
            'presetStudentId' => $request->input('student_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = TasmeePageInput::normalize($request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'type' => ['required', Rule::in(['new', 'revision'])],
            'date' => ['required', 'date'],
            'amount' => ['nullable', 'required_without:from_page', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', Rule::in(['excellent', 'very_good', 'good', 'needs_review'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...TasmeePageInput::rules(),
        ]));

        $student = Student::findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);

        $session = QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => $data['type'],
            'date' => $data['date'],
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'from_page' => $data['from_page'] ?? null,
            'to_page' => $data['to_page'] ?? null,
            'result' => $data['result'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('quran.tasmee.created', $session, actor: $request->user());

        return redirect()
            ->route('teacher.quran.tasmee.index', ['type' => $session->type->value])
            ->with('success', 'تم تسجيل التسميع بنجاح');
    }

    public function edit(Request $request, QuranRecitationSession $session): View
    {
        $teacher = $this->currentTeacher($request);

        abort_unless($session->teacher_id === $teacher->id, 403, 'لا تملك صلاحية تعديل هذا التسميع');

        return view('teacher.quran.tasmee.edit', [
            'session' => $session,
            'types' => QuranTasmeeType::cases(),
            'results' => QuranTasmeeResult::cases(),
        ]);
    }

    public function update(Request $request, QuranRecitationSession $session): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        abort_unless($session->teacher_id === $teacher->id, 403, 'لا تملك صلاحية تعديل هذا التسميع');

        $data = TasmeePageInput::normalize($request->validate([
            'type' => ['required', Rule::in(['new', 'revision'])],
            'date' => ['required', 'date'],
            'amount' => ['nullable', 'required_without:from_page', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', Rule::in(['excellent', 'very_good', 'good', 'needs_review'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...TasmeePageInput::rules(),
        ]));

        $before = $session->getAttributes();
        $oldResult = $session->result?->value;

        $session->update([
            'type' => $data['type'],
            'date' => $data['date'],
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'from_page' => $data['from_page'] ?? null,
            'to_page' => $data['to_page'] ?? null,
            'result' => $data['result'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('quran.tasmee.updated', $session, $before, actor: $request->user());

        if ($oldResult !== ($data['result'] ?? null)) {
            $this->audit->log(
                'quran.tasmee.result_changed',
                'quran_recitation_session',
                $session->id,
                $session->tenant_id,
                before: ['result' => $oldResult],
                after: ['result' => $session->result?->value ?? null],
                actor: $request->user()
            );
        }

        return redirect()
            ->route('teacher.quran.tasmee.index', ['type' => $session->type->value])
            ->with('success', 'تم تحديث التسميع');
    }
}
