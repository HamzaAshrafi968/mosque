<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Http\Controllers\Controller;
use App\Models\ProgramEnrollment;
use App\Models\QualifyingWeeklyEvaluation;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\QuranProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QualifyingController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QuranProgramService $programs,
    ) {}

    public function index(Request $request): View
    {
        $enrollments = ProgramEnrollment::query()
            ->with(['student:id,name,classroom_id', 'student.classroom:id,name'])
            ->where('program_type', ProgramType::Qualifying)
            ->when($request->input('status', 'active') === 'completed',
                fn ($q) => $q->where('status', ProgramEnrollmentStatus::Completed),
                fn ($q) => $q->where('status', ProgramEnrollmentStatus::Active))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.quran.qualifying.index', [
            'enrollments' => $enrollments,
            'status' => $request->input('status', 'active'),
            'students' => Student::query()
                ->whereHas('programEnrollments', fn ($q) => $q
                    ->where('program_type', ProgramType::Qualifying)
                    ->where('status', ProgramEnrollmentStatus::Active))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $studentId = $request->input('student_id');

        return view('admin.quran.qualifying.evaluation', [
            'studentId' => $studentId,
            'students' => Student::query()
                ->whereHas('programEnrollments', fn ($q) => $q
                    ->where('program_type', ProgramType::Qualifying)
                    ->where('status', ProgramEnrollmentStatus::Active))
                ->orderBy('name')
                ->get(['id', 'name']),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'weekStart' => Carbon::now()->startOfWeek()->format('Y-m-d'),
            'weekEnd' => Carbon::now()->startOfWeek()->addDays(6)->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $this->assertInProgram($data['student_id']);
        $this->assertWeekIsFree($data['student_id'], $data['week_start']);

        $evaluation = QualifyingWeeklyEvaluation::create([
            'student_id' => $data['student_id'],
            'week_start' => $data['week_start'],
            'week_end' => $data['week_end'],
            'amount' => $data['amount'],
            'recited_portion' => $data['recited_portion'] ?? null,
            'result' => $data['result'],
            'evaluated_by' => $data['evaluated_by'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('qualifying.weekly_recorded', $evaluation, actor: $request->user());

        return redirect()
            ->route('admin.quran.qualifying.index')
            ->with('success', 'تم تسجيل تقييم الأسبوع بنجاح');
    }

    /** إنهاء البرنامج التأهيلي → التحاق تلقائي ببرنامج الإجازة. */
    public function complete(Request $request, ProgramEnrollment $enrollment): RedirectResponse
    {
        try {
            $this->programs->completeQualifying($enrollment, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم إنهاء البرنامج التأهيلي والتحق الطالب ببرنامج الإجازة تلقائياً');
    }

    private function assertInProgram(string $studentId): void
    {
        $in = ProgramEnrollment::query()
            ->where('student_id', $studentId)
            ->where('program_type', ProgramType::Qualifying)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->exists();

        if (! $in) {
            throw ValidationException::withMessages(['student_id' => ['الطالب غير ملتحق بالبرنامج التأهيلي']]);
        }
    }

    private function assertWeekIsFree(string $studentId, string $weekStart): void
    {
        $exists = QualifyingWeeklyEvaluation::query()
            ->where('student_id', $studentId)
            ->whereDate('week_start', $weekStart)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['week_start' => ['يوجد تقييم مسجل لهذا الأسبوع مسبقاً']]);
        }
    }

    private function validated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        return $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date', 'after_or_equal:week_start'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999'],
            'recited_portion' => ['nullable', 'string', 'max:255'],
            'result' => ['required', Rule::in(['passed', 'needs_review', 'failed'])],
            'evaluated_by' => ['required', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
