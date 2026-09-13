<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranEvaluationResult;
use App\Http\Controllers\Controller;
use App\Models\IjazahMonthlyEvaluation;
use App\Models\IjazahWeeklyEvaluation;
use App\Models\ProgramEnrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\QuranProgramService;
use App\Support\QuranProgramSettings;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IjazahController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QuranProgramService $programs,
    ) {}

    public function index(Request $request): View
    {
        $enrollments = ProgramEnrollment::query()
            ->with(['student:id,name,classroom_id', 'student.classroom:id,name'])
            ->where('program_type', ProgramType::Ijazah)
            ->when($request->input('status', 'active') === 'completed',
                fn ($q) => $q->where('status', ProgramEnrollmentStatus::Completed),
                fn ($q) => $q->where('status', ProgramEnrollmentStatus::Active))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.quran.ijazah.index', [
            'enrollments' => $enrollments,
            'status' => $request->input('status', 'active'),
            'students' => Student::query()
                ->whereHas('programEnrollments', fn ($q) => $q
                    ->where('program_type', ProgramType::Ijazah)
                    ->where('status', ProgramEnrollmentStatus::Active))
                ->orderBy('name')
                ->get(['id', 'name']),
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    public function create(Request $request): View
    {
        $month = $request->input('month');
        $defaultMonth = is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? $month
            : QuranProgramSettings::monthOf(now());

        return view('admin.quran.ijazah.evaluation', [
            'studentId' => $request->input('student_id'),
            'students' => Student::query()
                ->whereHas('programEnrollments', fn ($q) => $q
                    ->where('program_type', ProgramType::Ijazah)
                    ->where('status', ProgramEnrollmentStatus::Active))
                ->orderBy('name')
                ->get(['id', 'name']),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'defaultMonth' => $defaultMonth,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $this->assertInProgram($data['student_id']);

        $exists = IjazahMonthlyEvaluation::query()
            ->where('student_id', $data['student_id'])
            ->where('month', $data['month'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['month' => ['يوجد تقييم مسجل لهذا الشهر — الأيام السابقة لا تُستبدل']]);
        }

        $evaluation = IjazahMonthlyEvaluation::create([
            'student_id' => $data['student_id'],
            'month' => $data['month'],
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'result' => $data['result'],
            'evaluated_by' => $data['evaluated_by'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('ijazah.monthly_recorded', $evaluation, actor: $request->user());

        return redirect()
            ->route('admin.quran.ijazah.index')
            ->with('success', 'تم تسجيل التقييم الشهري لبرنامج الإجازة');
    }

    /** إنهاء برنامج الإجازة (اكتمال الرحلة القرآنية). */
    public function complete(Request $request, ProgramEnrollment $enrollment): RedirectResponse
    {
        try {
            $this->programs->completeIjazah($enrollment, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم إنهاء برنامج الإجازة — اكتملت الرحلة القرآنية للطالب');
    }

    /** صفحة شهر الطالب: 4 أسابيع + الملخص الشهري. */
    public function month(Request $request, Student $student, string $month): View
    {
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

        return view('admin.quran.ijazah.month', [
            'student' => $student,
            'month' => $month,
            'evaluations' => $evaluations,
            'monthly' => $monthly,
            'passedWeeks' => $evaluations->filter(fn (IjazahWeeklyEvaluation $evaluation) => $evaluation->result === QuranEvaluationResult::Passed)->count(),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'results' => QuranEvaluationResult::cases(),
            'weekDefaults' => collect(range(1, 4))->mapWithKeys(fn (int $week) => [$week => $this->weekDates($month, $week)])->all(),
            'previousMonth' => QuranProgramSettings::previousMonth($month),
            'nextMonth' => QuranProgramSettings::nextMonth($month),
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    public function storeWeekly(Request $request): RedirectResponse
    {
        $data = $this->weeklyValidated($request, withIdentity: true);

        $this->assertInProgram($data['student_id']);
        $this->assertWeekIsFree($data['student_id'], $data['month'], (int) $data['week']);

        [$defaultStart, $defaultEnd] = $this->weekDates($data['month'], (int) $data['week']);

        $evaluation = IjazahWeeklyEvaluation::create([
            'student_id' => $data['student_id'],
            'month' => $data['month'],
            'week' => $data['week'],
            'week_start' => $data['week_start'] ?? $defaultStart,
            'week_end' => $data['week_end'] ?? $defaultEnd,
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'result' => $data['result'],
            'evaluated_by' => $data['evaluated_by'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('ijazah.weekly_recorded', $evaluation, actor: $request->user());

        return redirect()
            ->route('admin.quran.ijazah.month', [$data['student_id'], $data['month']])
            ->with('success', 'تم تسجيل تقييم الأسبوع');
    }

    public function updateWeekly(Request $request, IjazahWeeklyEvaluation $evaluation): RedirectResponse
    {
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
            'evaluated_by' => $data['evaluated_by'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('ijazah.weekly_updated', $evaluation, $before, actor: $request->user());

        return redirect()
            ->route('admin.quran.ijazah.month', [$evaluation->student_id, $evaluation->month])
            ->with('success', 'تم تحديث تقييم الأسبوع');
    }

    public function destroyWeekly(Request $request, IjazahWeeklyEvaluation $evaluation): RedirectResponse
    {
        $studentId = $evaluation->student_id;
        $month = $evaluation->month;

        $this->audit->logModel('ijazah.weekly_deleted', $evaluation, actor: $request->user());
        $evaluation->delete();

        return redirect()
            ->route('admin.quran.ijazah.month', [$studentId, $month])
            ->with('success', 'تم حذف تقييم الأسبوع');
    }

    private function assertInProgram(string $studentId): void
    {
        $in = ProgramEnrollment::query()
            ->where('student_id', $studentId)
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
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        $rules = [
            'week' => ['required', 'integer', 'between:1,4'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['required', Rule::in(['passed', 'needs_review', 'failed'])],
            'week_start' => ['nullable', 'date'],
            'week_end' => ['nullable', 'date', 'after_or_equal:week_start'],
            'evaluated_by' => ['nullable', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($withIdentity) {
            $rules['student_id'] = ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)];
            $rules['month'] = ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'];
        }

        return $request->validate($rules);
    }

    private function validated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        return $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['required', Rule::in(['passed', 'needs_review', 'failed'])],
            'evaluated_by' => ['required', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
