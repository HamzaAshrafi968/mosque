<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Http\Controllers\Controller;
use App\Models\IjazahMonthlyEvaluation;
use App\Models\ProgramEnrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\QuranProgramService;
use App\Support\QuranProgramSettings;
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
        return view('admin.quran.ijazah.evaluation', [
            'studentId' => $request->input('student_id'),
            'students' => Student::query()
                ->whereHas('programEnrollments', fn ($q) => $q
                    ->where('program_type', ProgramType::Ijazah)
                    ->where('status', ProgramEnrollmentStatus::Active))
                ->orderBy('name')
                ->get(['id', 'name']),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'defaultMonth' => QuranProgramSettings::monthOf(now()),
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

    private function validated(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;

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
