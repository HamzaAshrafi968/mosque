<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProgramType;
use App\Enums\QuranListeningTestResult;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\QuranListeningProgramItem;
use App\Models\Student;
use App\Services\QuranListeningProgramService;
use App\Services\QuranPageService;
use App\Services\QuranProgramBatchService;
use App\Services\QuranScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * «برامج الاستماع» — مركز الأستاذ (الإجازة/التأهيلي) لطلابه ضمن نطاقه.
 *
 * دورة دفعات 5 أجزاء + اختبار تراكمي من الجزء 1 + إعادة الراسب فقط.
 */
class QuranListeningProgramController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranListeningProgramService $programs,
        private readonly QuranProgramBatchService $batches,
        private readonly QuranScopeService $scope,
        private readonly QuranPageService $pages,
    ) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $studentIds = $this->scope->studentIdsFor($teacher);

        $type = ProgramType::tryFrom((string) $request->input('type', ProgramType::Qualifying->value))
            ?? ProgramType::Qualifying;

        $selectedStudent = null;

        if ($request->filled('student_id')) {
            $selectedStudent = Student::query()->find($request->input('student_id'));

            if ($selectedStudent) {
                $this->scope->assertCanManageStudent($teacher, $selectedStudent);
            }
        }

        $programs = QuranListeningProgram::query()
            ->with(['student:id,name', 'enrollment:id,program_type,status'])
            ->whereIn('student_id', $studentIds)
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

            if ($selected) {
                $this->assertCanManageProgram($selected);
            }
        } elseif ($selectedStudent) {
            $selected = $studentPrograms->first(fn (QuranListeningProgram $program) => $program->type === $type && $program->isActive())
                ?? $studentPrograms->first(fn (QuranListeningProgram $program) => $program->type === $type);
        }

        return view('teacher.quran.programs.index', array_merge($this->panelFor($selected), [
            'programs' => $programs,
            'selectedStudent' => $selectedStudent,
            'selectedType' => $type,
            'students' => $this->scope->studentsFor($teacher),
            'types' => ProgramType::cases(),
            'canTest' => true,
            'canCancel' => true,
            'actions' => $this->actions(),
        ]));
    }

    /** الرابط المساري القديم يحوّل إلى الرابط القانوني بالاستعلام (?type&program_id). */
    public function show(QuranListeningProgram $program): RedirectResponse
    {
        $this->assertCanManageProgram($program);

        return redirect()->route('teacher.quran.programs.index', [
            'type' => $program->type->value,
            'program_id' => $program->id,
        ]);
    }

    /** شاشة تسميع جزء كامل مع تسجيل الأخطاء كلمة بكلمة. */
    public function tasmee(Request $request, QuranListeningProgramItem $item): View|RedirectResponse
    {
        $this->assertCanManageItem($item);

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

        return view('quran.programs.tasmee', [
            'program' => $program,
            'item' => $item,
            'student' => $student,
            'teacherId' => $this->currentTeacher($request)->id,
            'pages' => $this->pages->pagesForRange($item->from_page, $item->to_page),
            'statuses' => [],
            'date' => now()->toDateString(),
            'storeRoute' => route('teacher.quran.programs.items.tasmee.store', $item),
            'backRoute' => $this->programUrl($program),
        ]);
    }

    /** حفظ التسميع: جلسة «جديد» مرتبطة بالجزء والدفعة. */
    public function storeTasmee(Request $request, QuranListeningProgramItem $item): RedirectResponse
    {
        $this->assertCanManageItem($item);

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

        $teacherId = $this->currentTeacher($request)->id;

        $session = $this->batches->recordTasmee($item, $data, $request->user(), $teacherId);

        return redirect()
            ->to($this->programUrl($program))
            ->with('success', 'تم تسجيل تسميع '.$item->label().' — التقدير: '.($session->result?->label() ?? '—'));
    }

    public function test(Request $request, QuranListeningProgramBatch $batch): RedirectResponse
    {
        $this->assertCanManageStudentId($batch->student_id);

        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*' => ['required', Rule::enum(QuranListeningTestResult::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $test = $this->batches->recordBatchTest($batch, $data['results'], $request->user(), $data['notes'] ?? null);

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
        $this->assertCanManageStudentId($batch->student_id);

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
        $this->assertCanManageProgram($program);

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

    private function assertCanManageProgram(QuranListeningProgram $program): void
    {
        $this->assertCanManageStudentId($program->student_id);
    }

    private function assertCanManageItem(QuranListeningProgramItem $item): void
    {
        $this->assertCanManageStudentId($item->program()->first()?->student_id);
    }

    private function assertCanManageStudentId(?string $studentId): void
    {
        if (! $studentId) {
            abort(403, 'لا تملك صلاحية الوصول لهذا البرنامج');
        }

        $student = Student::query()->find($studentId);

        if (! $student) {
            abort(404);
        }

        $this->scope->assertCanManageStudent($this->currentTeacher(request()), $student);
    }

    /** @return array<string, callable> */
    private function actions(): array
    {
        return [
            'index' => route('teacher.quran.programs.index'),
            'show' => fn (QuranListeningProgram $program) => $this->programUrl($program),
            'tasmee' => fn (QuranListeningProgramItem $item) => route('teacher.quran.programs.items.tasmee', $item),
            'test' => fn (QuranListeningProgramBatch $batch) => route('teacher.quran.programs.batches.test', $batch),
            'placement' => fn (QuranListeningProgramBatch $batch) => route('teacher.quran.programs.batches.placement-test', $batch),
            'cancel' => fn (QuranListeningProgram $program) => route('teacher.quran.programs.cancel', $program),
        ];
    }

    /** الرابط القانوني لدورة البرنامج: الفهرس مع نوعها ومعرّفها. */
    private function programUrl(QuranListeningProgram $program): string
    {
        return route('teacher.quran.programs.index', [
            'type' => $program->type->value,
            'program_id' => $program->id,
        ]);
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
