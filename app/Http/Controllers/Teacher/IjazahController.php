<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Models\IjazahMonthlyEvaluation;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\QuranScopeService;
use App\Support\QuranProgramSettings;
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

        return view('teacher.quran.ijazah.create', [
            'students' => $this->ijazahStudents($teacher),
            'studentId' => $request->input('student_id'),
            'defaultMonth' => QuranProgramSettings::monthOf(now()),
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
