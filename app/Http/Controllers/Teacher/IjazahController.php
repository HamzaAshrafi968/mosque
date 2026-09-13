<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranEvaluationResult;
use App\Models\IjazahMonthlyEvaluation;
use App\Models\IjazahWeeklyEvaluation;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\QuranScopeService;
use App\Support\QuranProgramSettings;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IjazahController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranScopeService $scope,
        private readonly AuditLogger $audit,
    ) {}

    /** تقييماتي الشهرية + طلاب برنامج الإجازة ضمن نطاقي. */
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $evaluations = IjazahMonthlyEvaluation::query()
            ->with(['student:id,name'])
            ->where('evaluated_by', $teacher->id)
            ->orderByDesc('month')
            ->paginate(20)
            ->withQueryString();

        return view('teacher.quran.ijazah.index', [
            'evaluations' => $evaluations,
            'students' => $this->ijazahStudents($teacher),
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    public function create(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $month = $request->input('month');
        $defaultMonth = is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? $month
            : QuranProgramSettings::monthOf(now());

        return view('teacher.quran.ijazah.create', [
            'students' => $this->ijazahStudents($teacher),
            'studentId' => $request->input('student_id'),
            'defaultMonth' => $defaultMonth,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['required', Rule::in(['passed', 'needs_review', 'failed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);

        $in = $student->programEnrollments()
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->exists();

        if (! $in) {
            throw ValidationException::withMessages(['student_id' => ['الطالب غير ملتحق ببرنامج الإجازة']]);
        }

        $exists = IjazahMonthlyEvaluation::query()
            ->where('student_id', $student->id)
            ->where('month', $data['month'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['month' => ['يوجد تقييم مسجل لهذا الشهر — الأيام السابقة لا تُستبدل']]);
        }

        $evaluation = IjazahMonthlyEvaluation::create([
            'student_id' => $student->id,
            'month' => $data['month'],
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'result' => $data['result'],
            'evaluated_by' => $teacher->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('ijazah.monthly_recorded', $evaluation, actor: $request->user());

        return redirect()
            ->route('teacher.quran.ijazah.index')
            ->with('success', 'تم تسجيل التقييم الشهري لبرنامج الإجازة');
    }

    /** صفحة شهر الطالب: 4 أسابيع + الملخص الشهري (ضمن نطاق المعلم). */
    public function month(Request $request, Student $student, string $month): View
    {
        $teacher = $this->currentTeacher($request);
        $this->scope->assertCanManageStudent($teacher, $student);

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            abort(404);
        }

        $evaluations = IjazahWeeklyEvaluation::query()
            ->with('evaluatedBy:id,name')
            ->where('student_id', $student->id)
            ->where('month', $month)
            ->get()
            ->keyBy('week');

        $monthly = IjazahMonthlyEvaluation::query()
            ->with('evaluatedBy:id,name')
            ->where('student_id', $student->id)
            ->where('month', $month)
            ->first();

        return view('teacher.quran.ijazah.month', [
            'student' => $student,
            'month' => $month,
            'evaluations' => $evaluations,
            'monthly' => $monthly,
            'passedWeeks' => $evaluations->filter(fn (IjazahWeeklyEvaluation $evaluation) => $evaluation->result === QuranEvaluationResult::Passed)->count(),
            'results' => QuranEvaluationResult::cases(),
            'weekDefaults' => collect(range(1, 4))->mapWithKeys(fn (int $week) => [$week => $this->weekDates($month, $week)])->all(),
            'previousMonth' => QuranProgramSettings::previousMonth($month),
            'nextMonth' => QuranProgramSettings::nextMonth($month),
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    public function storeWeekly(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $data = $this->weeklyValidated($request, withIdentity: true);

        $student = Student::findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);
        $this->assertStudentInIjazah($student);
        $this->assertWeekIsFree($student->id, $data['month'], (int) $data['week']);

        [$defaultStart, $defaultEnd] = $this->weekDates($data['month'], (int) $data['week']);

        $evaluation = IjazahWeeklyEvaluation::create([
            'student_id' => $student->id,
            'month' => $data['month'],
            'week' => $data['week'],
            'week_start' => $data['week_start'] ?? $defaultStart,
            'week_end' => $data['week_end'] ?? $defaultEnd,
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'result' => $data['result'],
            'evaluated_by' => $teacher->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('ijazah.weekly_recorded', $evaluation, actor: $request->user());

        return redirect()
            ->route('teacher.quran.ijazah.month', [$student->id, $data['month']])
            ->with('success', 'تم تسجيل تقييم الأسبوع');
    }

    public function updateWeekly(Request $request, IjazahWeeklyEvaluation $evaluation): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->scope->assertCanManageStudent($teacher, $evaluation->student);

        $data = $this->weeklyValidated($request);

        $this->assertWeekIsFree($evaluation->student_id, $evaluation->month, (int) $data['week'], $evaluation->id);

        [$defaultStart, $defaultEnd] = $this->weekDates($evaluation->month, (int) $data['week']);
        $before = $evaluation->getAttributes();

        $evaluation->update([
            'week' => $data['week'],
            'week_start' => $data['week_start'] ?? $defaultStart,
            'week_end' => $data['week_end'] ?? $defaultEnd,
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'result' => $data['result'],
            'evaluated_by' => $teacher->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('ijazah.weekly_updated', $evaluation, $before, actor: $request->user());

        return redirect()
            ->route('teacher.quran.ijazah.month', [$evaluation->student_id, $evaluation->month])
            ->with('success', 'تم تحديث تقييم الأسبوع');
    }

    private function assertStudentInIjazah(Student $student): void
    {
        $in = $student->programEnrollments()
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->exists();

        if (! $in) {
            throw ValidationException::withMessages(['student_id' => ['الطالب غير ملتحق ببرنامج الإجازة']]);
        }
    }

    private function assertWeekIsFree(string $studentId, string $month, int $week, ?string $ignoreId = null): void
    {
        $exists = IjazahWeeklyEvaluation::query()
            ->where('student_id', $studentId)
            ->where('month', $month)
            ->where('week', $week)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'week' => ['يوجد تقييم مسجل لهذا الأسبوع — عدّل التقييم القائم بدلاً من إضافة جديد'],
            ]);
        }
    }

    /** @return array{0: string, 1: string} */
    private function weekDates(string $month, int $week): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->addWeeks($week - 1);

        return [$start->toDateString(), $start->copy()->addDays(6)->toDateString()];
    }

    private function weeklyValidated(Request $request, bool $withIdentity = false): array
    {
        $rules = [
            'week' => ['required', 'integer', 'between:1,4'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['required', Rule::in(['passed', 'needs_review', 'failed'])],
            'week_start' => ['nullable', 'date'],
            'week_end' => ['nullable', 'date', 'after_or_equal:week_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($withIdentity) {
            $rules['student_id'] = ['required', 'uuid', 'exists:students,id'];
            $rules['month'] = ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'];
        }

        return $request->validate($rules);
    }

    private function ijazahStudents($teacher)
    {
        $ids = $this->scope->studentIdsFor($teacher);

        return Student::query()
            ->whereIn('id', $ids)
            ->whereHas('programEnrollments', fn ($q) => $q
                ->where('program_type', ProgramType::Ijazah)
                ->where('status', ProgramEnrollmentStatus::Active))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
