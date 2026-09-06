<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Models\QualifyingWeeklyEvaluation;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\QuranScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QualifyingController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranScopeService $scope,
        private readonly AuditLogger $audit,
    ) {}

    /** تقييماتي الأسبوعية + قائمة طلاب البرنامج التأهيلي ضمن نطاقي. */
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $evaluations = QualifyingWeeklyEvaluation::query()
            ->with(['student:id,name'])
            ->where('evaluated_by', $teacher->id)
            ->orderByDesc('week_start')
            ->paginate(20)
            ->withQueryString();

        return view('teacher.quran.qualifying.index', [
            'evaluations' => $evaluations,
            'students' => $this->qualifyingStudents($teacher),
        ]);
    }

    public function create(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        return view('teacher.quran.qualifying.create', [
            'students' => $this->qualifyingStudents($teacher),
            'studentId' => $request->input('student_id'),
            'weekStart' => Carbon::now()->startOfWeek()->format('Y-m-d'),
            'weekEnd' => Carbon::now()->startOfWeek()->addDays(6)->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date', 'after_or_equal:week_start'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['required', Rule::in(['passed', 'needs_review', 'failed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);
        $this->assertInProgram($student);

        $exists = QualifyingWeeklyEvaluation::query()
            ->where('student_id', $student->id)
            ->whereDate('week_start', $data['week_start'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['week_start' => ['يوجد تقييم مسجل لهذا الأسبوع مسبقاً']]);
        }

        $evaluation = QualifyingWeeklyEvaluation::create([
            'student_id' => $student->id,
            'week_start' => $data['week_start'],
            'week_end' => $data['week_end'],
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'result' => $data['result'],
            'evaluated_by' => $teacher->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('qualifying.weekly_recorded', $evaluation, actor: $request->user());

        return redirect()
            ->route('teacher.quran.qualifying.index')
            ->with('success', 'تم تسجيل تقييم الأسبوع بنجاح');
    }

    private function qualifyingStudents($teacher)
    {
        $ids = $this->scope->studentIdsFor($teacher);

        return Student::query()
            ->whereIn('id', $ids)
            ->whereHas('programEnrollments', fn ($q) => $q
                ->where('program_type', ProgramType::Qualifying)
                ->where('status', ProgramEnrollmentStatus::Active))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function assertInProgram(Student $student): void
    {
        $in = $student->programEnrollments()
            ->where('program_type', ProgramType::Qualifying)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->exists();

        if (! $in) {
            throw ValidationException::withMessages(['student_id' => ['الطالب غير ملتحق بالبرنامج التأهيلي']]);
        }
    }
}
