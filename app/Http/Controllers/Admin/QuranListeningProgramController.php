<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramType;
use App\Enums\QuranListeningTestResult;
use App\Enums\QuranReading;
use App\Http\Controllers\Controller;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\QuranListeningProgramItem;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\QuranListeningProgramService;
use App\Services\QuranPageService;
use App\Services\QuranProgramBatchService;
use App\Services\QuranProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * «برامج الاستماع» — مركز مدير الجامع: الإجازة/التأهيلي.
 *
 * دورة دفعات 5 أجزاء — تسميع ← اختبار تراكمي من الجزء 1
 * ← إعادة الأجزاء الراسبة فقط (QuranProgramBatchService).
 */
class QuranListeningProgramController extends Controller
{
    public function __construct(
        private readonly QuranListeningProgramService $programs,
        private readonly QuranProgramBatchService $batches,
        private readonly QuranProgramService $programEnrollments,
        private readonly QuranPageService $pages,
        private readonly AuthorizationService $authorization,
    ) {}

    public function index(Request $request): View
    {
        $type = ProgramType::tryFrom((string) $request->input('type', ProgramType::Qualifying->value))
            ?? ProgramType::Qualifying;

        $selectedStudent = $request->filled('student_id')
            ? Student::query()->find($request->input('student_id'))
            : null;

        $programs = QuranListeningProgram::query()
            ->with(['student:id,name', 'enrollment:id,program_type,status'])
            ->when($selectedStudent, fn ($query) => $query->where('student_id', $selectedStudent->id))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $type))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $selected = null;

        $studentPrograms = $selectedStudent
            ? $this->programs->programsForStudent($selectedStudent)
            : collect();

        if ($request->filled('program_id')) {
            $selected = QuranListeningProgram::query()->find($request->input('program_id'));
        } elseif ($selectedStudent) {
            $selected = $studentPrograms->first(fn (QuranListeningProgram $program) => $program->type === $type && $program->isActive())
                ?? $studentPrograms->first(fn (QuranListeningProgram $program) => $program->type === $type);
        }

        $students = Student::query()->active()->orderBy('name')->get(['id', 'name']);
        $eligibleIds = $this->programEnrollments->completedIjazahStudentIds($students->pluck('id'));
        $eligibleStudents = $students->whereIn('id', $eligibleIds)->values();

        return view('admin.quran.programs.index', array_merge($this->panelFor($selected), [
            'programs' => $programs,
            'selectedStudent' => $selectedStudent,
            'selectedType' => $type,
            'students' => $students,
            'eligibleStudents' => $eligibleStudents,
            'types' => ProgramType::cases(),
            'readings' => QuranReading::cases(),
            'canTest' => $this->authorization->can($request->user(), 'quran_training.test'),
            'canCancel' => $this->authorization->can($request->user(), 'quran_training.update'),
            'canEnroll' => $this->authorization->can($request->user(), 'quran_training.update'),
            'actions' => $this->actions($request->user()),
        ]));
    }

    /** تسجيل طالب في برنامج القراءات لقراءة محددة من القراءات العشر. */
    public function enroll(Request $request): RedirectResponse
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        $data = $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'reading' => ['required', Rule::enum(QuranReading::class)],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);

        $program = $this->programs->enrollReadings(
            $student,
            QuranReading::from($data['reading']),
            $request->user(),
        );

        return redirect()
            ->to($this->programUrl($program))
            ->with('success', 'تم تسجيل '.$student->name.' في '.$program->displayLabel());
    }

    /** الرابط المساري القديم يحوّل إلى الرابط القانوني بالاستعلام (?type&program_id). */
    public function show(QuranListeningProgram $program): RedirectResponse
    {
        return redirect()->route('admin.quran.programs.index', [
            'type' => $program->type->value,
            'program_id' => $program->id,
        ]);
    }

    /** شاشة تسميع جزء كامل مع تسجيل الأخطاء كلمة بكلمة. */
    public function tasmee(Request $request, QuranListeningProgramItem $item): View|RedirectResponse
    {
        $program = $item->program()->first();

        if (! $program) {
            abort(404);
        }

        try {
            $this->batches->assertTasmeeAllowed($item);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $student = $program->student()->first();

        if (! $student) {
            abort(404);
        }

        $teacherId = $this->teacherIdFor($student, $request);

        if (! $teacherId) {
            return back()->withErrors(['teacher_id' => 'لا يوجد أستاذ نشط في دوام الطالب — عيّن أستاذاً ثم أعد المحاولة']);
        }

        return view('quran.programs.tasmee', [
            'program' => $program,
            'item' => $item,
            'student' => $student,
            'teacherId' => $teacherId,
            'pages' => $this->pages->pagesForRange($item->from_page, $item->to_page),
            'statuses' => [],
            'date' => now()->toDateString(),
            'storeRoute' => route('admin.quran.programs.items.tasmee.store', $item),
            'backRoute' => $this->programUrl($program),
        ]);
    }

    /** حفظ التسميع: جلسة «جديد» مرتبطة بالجزء والدفعة. */
    public function storeTasmee(Request $request, QuranListeningProgramItem $item): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'word_statuses' => ['nullable', 'array', 'max:5000'],
            'word_statuses.*' => ['string', Rule::in(['correct', 'incorrect', 'hesitation', 'tajweed_error', 'added', 'forgotten', 'unreviewed'])],
        ]);

        $program = $item->program()->first();

        if (! $program) {
            abort(404);
        }

        $student = $program->student()->first();

        if (! $student) {
            abort(404);
        }

        $teacherId = $this->teacherIdFor($student, $request);

        if (! $teacherId) {
            return back()->withErrors(['teacher_id' => 'لا يوجد أستاذ نشط في دوام الطالب — عيّن أستاذاً ثم أعد المحاولة']);
        }

        $session = $this->batches->recordTasmee($item, $data, $request->user(), $teacherId);

        return redirect()
            ->to($this->programUrl($program))
            ->with('success', 'تم تسجيل تسميع '.$item->label().' — التقدير: '.($session->result?->label() ?? '—'));
    }

    /** تسجيل استماع جزئي لصفحات من جزء مع بقاء صفحات أخرى (دون إنهاء الجزء). */
    public function storePartial(Request $request, QuranListeningProgramItem $item): RedirectResponse
    {
        $data = $request->validate([
            'from_page' => ['required', 'integer', 'min:1'],
            'to_page' => ['required', 'integer', 'min:1', 'gte:from_page'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $program = $item->program()->first();

        if (! $program) {
            abort(404);
        }

        $student = $program->student()->first();

        if (! $student) {
            abort(404);
        }

        $teacherId = $this->teacherIdFor($student, $request);

        if (! $teacherId) {
            return back()->withErrors(['teacher_id' => 'لا يوجد أستاذ نشط في دوام الطالب — عيّن أستاذاً ثم أعد المحاولة']);
        }

        $session = $this->batches->recordPartialListening($item, $data, $request->user(), $teacherId);

        $full = (int) $session->from_page === (int) $item->from_page && (int) $session->to_page === (int) $item->to_page;

        return redirect()
            ->to($this->programUrl($program))
            ->with('success', $full
                ? 'تم تسجيل تسميع '.$item->label().' كاملاً'
                : 'تم تسجيل استماع صفحات '.$session->from_page.'–'.$session->to_page.' من '.$item->label().' — المتبقي '.$this->batches->remainingPages($item->fresh()).' صفحة');
    }

    /** تسجيل اختبار الدفعة التراكمي (من الجزء 1 إلى آخر جزء في الدفعة). */
    public function test(Request $request, QuranListeningProgramBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*' => ['required', Rule::enum(QuranListeningTestResult::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'scope' => ['nullable', Rule::in([QuranProgramBatchService::RETAKE_SCOPE_FULL, 'failed'])],
        ]);

        $test = $this->batches->recordBatchTest($batch, $data['results'], $request->user(), $data['notes'] ?? null, $data['scope'] ?? null);

        $score = $this->formatPercent((float) $test->score);

        if ($test->isPass()) {
            return back()->with('success', 'ما شاء الله — نجاح في اختبار '.$batch->label().' بنسبة '.$score.'%');
        }

        $failed = $test->items()
            ->where('result', QuranListeningTestResult::Fail)
            ->orderBy('juz')
            ->pluck('juz')
            ->implode('، ');

        return back()->with('success', 'نتيجة اختبار '.$batch->label().': '.$score.'% — رسب في الأجزاء: '.$failed.' — أعد تسميعها ثم أعد اختبارها.');
    }

    /** الاختبار المباشر للأجزاء المحفوظة مسبقاً (التأهيلي/الإجازة). */
    public function placementTest(Request $request, QuranListeningProgramBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*' => ['required', Rule::enum(QuranListeningTestResult::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tests = $this->batches->recordPlacementTest($batch, $data['results'], $request->user(), $data['notes'] ?? null);
        $summary = $this->batches->placementTestSummary($tests);

        if ($summary['passed']) {
            return back()->with('success', 'ما شاء الله — نجاح في الاختبار المباشر للأجزاء '.$summary['scope_label'].' ('.$summary['passed_batches'].' دفعة)');
        }

        return back()->with('success', 'نتيجة الاختبار المباشر للأجزاء '.$summary['scope_label'].': '.$this->formatPercent($summary['score']).' — رسب في الأجزاء: '.($summary['failed_juz'] === [] ? '—' : implode('، ', $summary['failed_juz'])));
    }

    public function cancel(Request $request, QuranListeningProgram $program): RedirectResponse
    {
        $this->programs->cancelProgram($program, $request->user());

        return back()->with('success', 'تم إلغاء '.$program->label());
    }

    /** @return array<string, mixed> */
    private function panelFor(?QuranListeningProgram $program): array
    {
        if (! $program) {
            return QuranListeningProgramService::emptyPanel();
        }

        return $this->batches->panelData($program);
    }

    /** أستاذ التسميع: أستاذ المستخدم إن وُجد، وإلا أستاذ من دوام الطالب. */
    private function teacherIdFor(Student $student, Request $request): ?string
    {
        $teacher = Teacher::query()->where('user_id', $request->user()->id)->first();

        if ($teacher) {
            return $teacher->id;
        }

        return $this->batches->resolveTeacher($student)?->id;
    }

    /** @return array<string, callable|null> */
    private function actions(User $user): array
    {
        $can = fn (string $permission) => $this->authorization->can($user, $permission);

        return [
            'index' => route('admin.quran.programs.index'),
            'show' => fn (QuranListeningProgram $program) => $this->programUrl($program),
            'tasmee' => $can('quran_training.listen')
                ? fn (QuranListeningProgramItem $item) => route('admin.quran.programs.items.tasmee', $item)
                : null,
            'partial' => $can('quran_training.listen')
                ? fn (QuranListeningProgramItem $item) => route('admin.quran.programs.items.partial', $item)
                : null,
            'test' => $can('quran_training.test')
                ? fn (QuranListeningProgramBatch $batch) => route('admin.quran.programs.batches.test', $batch)
                : null,
            'placement' => $can('quran_training.test')
                ? fn (QuranListeningProgramBatch $batch) => route('admin.quran.programs.batches.placement-test', $batch)
                : null,
            'cancel' => $can('quran_training.update')
                ? fn (QuranListeningProgram $program) => route('admin.quran.programs.cancel', $program)
                : null,
        ];
    }

    /** الرابط القانوني لدورة البرنامج: الفهرس مع نوعها ومعرّفها. */
    private function programUrl(QuranListeningProgram $program): string
    {
        return route('admin.quran.programs.index', [
            'type' => $program->type->value,
            'program_id' => $program->id,
        ]);
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
