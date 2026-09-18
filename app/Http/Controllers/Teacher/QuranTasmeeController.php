<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\QuranTasmeeResult;
use App\Enums\QuranTasmeeType;
use App\Models\QuranListeningProgramBatch;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\QuranMemorizationGatingService;
use App\Services\QuranPageService;
use App\Services\QuranProgramBatchService;
use App\Services\QuranScopeService;
use App\Support\QuranJuzMap;
use App\Support\TasmeePageInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuranTasmeeController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranScopeService $scope,
        private readonly AuditLogger $audit,
        private readonly QuranMemorizationGatingService $gating,
        private readonly AuthorizationService $authorization,
        private readonly QuranProgramBatchService $programBatches,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $target = $this->authorization->can($request->user(), 'quran_batch.view')
            ? 'teacher.quran.batches.index'
            : 'teacher.quran.index';

        return redirect()->route($target, array_filter([
            'student_id' => $request->input('student_id'),
        ]));
    }

    public function create(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $presetStudentId = $request->input('student_id');
        $presetType = in_array($request->input('type'), [QuranTasmeeType::New->value, QuranTasmeeType::Revision->value], true)
            ? $request->input('type')
            : null;
        $batch = null;
        $progress = null;
        $suggested = null;

        if ($presetStudentId) {
            $student = Student::query()->find($presetStudentId);

            if ($student) {
                $this->scope->assertCanManageStudent($teacher, $student);
                $batch = $this->gating->currentBatch($student);

                if ($batch) {
                    $progress = $this->gating->batchMemorizationProgress($batch);
                    $suggested = $this->suggestedRange($batch, $progress);
                }
            }
        }

        return view('teacher.quran.tasmee.create', [
            'students' => $this->scope->studentsFor($teacher),
            'types' => QuranTasmeeType::cases(),
            'results' => QuranTasmeeResult::cases(),
            'presetStudentId' => $presetStudentId,
            'presetType' => $presetType,
            'batch' => $batch,
            'progress' => $progress,
            'suggestedFrom' => $suggested['from'] ?? null,
            'suggestedTo' => $suggested['to'] ?? null,
        ]);
    }

    public function review(Request $request, QuranPageService $pages, ?QuranRecitationSession $session = null): View|RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        if ($session) {
            abort_unless($session->teacher_id === $teacher->id, 403, 'لا تملك صلاحية تعديل هذا التسميع');
        }

        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'type' => ['required', Rule::in(['new', 'revision'])],
            'date' => ['required', 'date'],
            'amount' => ['nullable', 'required_without:from_page', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', Rule::in(['excellent', 'very_good', 'good', 'needs_review'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...TasmeePageInput::rules(),
        ]);

        $student = Student::findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);

        $from = (int) ($data['from_page'] ?? 0);
        $to = (int) ($data['to_page'] ?? 0);

        if ($from < 1 || $to < $from || ($to - $from + 1) > QuranPageService::MAX_REVIEW_PAGES) {
            return redirect()
                ->route($session ? 'teacher.quran.tasmee.edit' : 'teacher.quran.tasmee.create', $session ? [$session] : [])
                ->withInput()
                ->withErrors(['from_page' => 'حدد نطاق صفحات صحيحاً (حتى '.QuranPageService::MAX_REVIEW_PAGES.' صفحات) لفتح صفحة التسميع.']);
        }

        if ($data['type'] === QuranTasmeeType::New->value) {
            $this->assertNewTasmeeWithinBatch($student->id, $data['type'], $from, $to);
        }

        return view('teacher.quran.tasmee.review', [
            'session' => $session,
            'student' => $student,
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
            ...TasmeePageInput::wordStatusRules(),
        ]));

        $student = Student::findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);

        if ($data['type'] === QuranTasmeeType::New->value) {
            $this->assertNewTasmeeWithinBatch(
                $student->id,
                $data['type'],
                isset($data['from_page']) ? (int) $data['from_page'] : null,
                isset($data['to_page']) ? (int) $data['to_page'] : null,
            );
        }

        $session = QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'batch_id' => $this->batchIdFor($student, $data),
            'program_batch_id' => $this->programBatchIdFor($student, $data),
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

        $this->programBatches->linkTasmeeSession($session, $request->user());

        if ($session->program_batch_id) {
            return redirect()
                ->route('teacher.quran.programs.show', $session->programBatch?->program_id)
                ->with('success', 'تم تسجيل التسميع ضمن دورة البرنامج بنجاح');
        }

        return redirect()
            ->route('teacher.quran.batches.index', ['student_id' => $student->id])
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
            ...TasmeePageInput::wordStatusRules(),
        ]));

        $before = $session->getAttributes();
        $oldResult = $session->result?->value;
        $student = Student::query()->find($session->student_id);

        if ($data['type'] === QuranTasmeeType::New->value && $student) {
            $this->assertNewTasmeeWithinBatch(
                $student->id,
                $data['type'],
                isset($data['from_page']) ? (int) $data['from_page'] : null,
                isset($data['to_page']) ? (int) $data['to_page'] : null,
            );
        }

        $session->update([
            'type' => $data['type'],
            'batch_id' => $student ? $this->batchIdFor($student, $data) : null,
            'program_batch_id' => $student ? $this->programBatchIdFor($student, $data) : null,
            'date' => $data['date'],
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'from_page' => $data['from_page'] ?? null,
            'to_page' => $data['to_page'] ?? null,
            'result' => $data['result'] ?? null,
            'notes' => $data['notes'] ?? null,
            'word_statuses' => TasmeePageInput::errorStatuses($data['word_statuses'] ?? null),
        ]);

        $this->programBatches->linkTasmeeSession($session, $request->user());

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
            ->route('teacher.quran.batches.index', ['student_id' => $session->student_id])
            ->with('success', 'تم تحديث التسميع');
    }

    /** دفعة تسميع «جديد»: تُشتق من نطاق الصفحات وتُترك فارغة للمراجعة. */
    private function batchIdFor(Student $student, array $data): ?string
    {
        if ($data['type'] !== QuranTasmeeType::New->value) {
            return null;
        }

        return $this->gating->batchForPageRange(
            $student,
            isset($data['from_page']) ? (int) $data['from_page'] : null,
            isset($data['to_page']) ? (int) $data['to_page'] : null,
        )?->id;
    }

    /** دفعة دورة التأهيلي/الإجازة التي يقع نطاق التسميع داخلها (إن وُجدت دورة نشطة). */
    private function programBatchIdFor(Student $student, array $data): ?string
    {
        if ($data['type'] !== QuranTasmeeType::New->value) {
            return null;
        }

        $from = isset($data['from_page']) ? (int) $data['from_page'] : null;
        $to = isset($data['to_page']) ? (int) $data['to_page'] : null;

        if ($from === null || $to === null || $from < 1 || $to < $from) {
            return null;
        }

        $cycle = $this->programBatches->activeCycle($student);

        if (! $cycle) {
            return null;
        }

        $first = QuranListeningProgramBatch::numberForJuz(QuranJuzMap::juzForPage($from));
        $last = QuranListeningProgramBatch::numberForJuz(QuranJuzMap::juzForPage($to));

        if ($first !== $last) {
            return null;
        }

        return $cycle->batches()->where('batch_number', $first)->value('id');
    }

    /** تسميع «جديد» مسموح داخل دفعة الحفظ الحالية أو دفعة دورة البرنامج الحالية. */
    private function assertNewTasmeeWithinBatch(string $studentId, string $type, ?int $fromPage, ?int $toPage): void
    {
        if ($type !== QuranTasmeeType::New->value) {
            return;
        }

        $student = Student::query()->find($studentId);

        if (! $student) {
            return;
        }

        $cycle = $this->programBatches->activeCycle($student);

        if ($cycle) {
            $current = $this->programBatches->currentBatch($cycle);

            if (! $current) {
                throw ValidationException::withMessages([
                    'from_page' => ['لا يمكن تسجيل تسميع «جديد» — لا توجد دفعة مفتوحة في دورة البرنامج'],
                ]);
            }

            $range = $current->pagesRange();

            if ($fromPage === null || $toPage === null || $fromPage < $range['from'] || $toPage > $range['to']) {
                throw ValidationException::withMessages([
                    'from_page' => [
                        "تسميع «جديد» مسموح فقط داخل {$current->label()} (صفحات {$range['from']}–{$range['to']})",
                    ],
                ]);
            }

            return;
        }

        $this->gating->assertNewTasmeeAllowed($student, $fromPage, $toPage);
    }

    /**
     * نطاق مقترح لتسميع «جديد»: من أول صفحة غير مغطاة داخل الدفعة،
     * بمقدار ٥ صفحات كحد أقصى (أو حتى نهاية الدفعة).
     *
     * @param  array{next_page: ?int}  $progress
     * @return array{from: int, to: int}|null
     */
    private function suggestedRange(QuranMemorizationBatch $batch, array $progress): ?array
    {
        $from = $progress['next_page'];

        if ($from === null) {
            return null;
        }

        $range = $batch->pagesRange();

        return ['from' => $from, 'to' => min($from + 4, $range['to'])];
    }
}
