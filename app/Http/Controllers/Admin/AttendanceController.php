<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Teacher\Attendance\SaveAttendanceAction;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Section;
use App\Models\Teacher;
use App\Services\AttendanceMetricService;
use App\Services\AuditLogger;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->input('date', now()->toDateString());
        $type = $request->input('type', 'students');

        $sessions = collect();
        $teacherRows = collect();

        if ($type === 'teachers') {
            $teacherRows = Attendance::query()
                ->with('teacher:id,name')
                ->whereDate('date', $date)
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->latest()
                ->get();
        } else {
            $sessions = AttendanceSession::query()
                ->with(['section:id,name,classroom_id', 'section.classroom:id,name', 'records', 'createdBy:id,name'])
                ->whereDate('date', $date)
                ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->input('section_id')))
                ->orderBy('date')
                ->get()
                ->map(fn (AttendanceSession $session) => [
                    'session' => $session,
                    ...$this->counts($session->records),
                ]);
        }

        $classrooms = Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get();
        $teachers = Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.attendance.index', [
            'classrooms' => $classrooms,
            'teachers' => $teachers,
            'sessions' => $sessions,
            'teacherRows' => $teacherRows,
            'date' => $date,
            'type' => $type,
        ]);
    }

    /** Quick roster form to record a section's attendance for a date. */
    public function create(Request $request): View
    {
        $date = $request->input('date', now()->toDateString());
        $sectionId = $request->input('section_id');

        $students = collect();
        $existing = collect();

        $section = $sectionId ? Section::find($sectionId) : null;

        if ($section) {
            $students = $this->roster($section);
            $existing = AttendanceRecord::query()
                ->whereIn('student_id', $students->pluck('id'))
                ->whereHas('session', fn ($q) => $q->whereDate('date', $date))
                ->pluck('status', 'student_id');
        }

        return view('admin.attendance.create', [
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
            'students' => $students,
            'existing' => $existing,
            'section' => $section,
            'date' => $date,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function storeStudents(Request $request, SaveAttendanceAction $action): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['in:present,absent,late,excused'],
        ]);

        $action->execute($data, $request->user());

        return redirect()->route('admin.attendance.index', ['date' => $data['date']])->with('success', 'تم حفظ الحضور بنجاح');
    }

    /** Edit one attendance session (correct marks). */
    public function edit(AttendanceSession $session): View
    {
        $session->load(['section.classroom', 'records.student']);

        return view('admin.attendance.edit', [
            'session' => $session,
            'students' => $this->roster($session->section),
            'records' => $session->records->keyBy('student_id'),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function update(Request $request, AttendanceSession $session, SaveAttendanceAction $action): RedirectResponse
    {
        $data = $request->validate([
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['in:present,absent,late,excused'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:500'],
        ]);

        $action->execute(['date' => $session->date->toDateString(), ...$data], $request->user());

        return back()->with('success', 'تم تحديث الحضور');
    }

    /** History matrix view (spec §11 grid). */
    public function history(Request $request): View
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $sections = Section::query()
            ->with('classroom:id,name')
            ->active()
            ->orderBy('name')
            ->get();

        $section = $request->filled('section_id')
            ? $sections->firstWhere('id', $request->input('section_id'))
            : $sections->first();

        $grid = $section
            ? app(AttendanceMetricService::class)->grid($section, $from, $to)
            : ['sessions' => collect(), 'rows' => []];

        return view('admin.attendance.history', [
            'sections' => $sections,
            'section' => $section,
            'from' => $from,
            'to' => $to,
            'sessions' => $grid['sessions'],
            'rows' => $grid['rows'],
        ]);
    }

    /** Record teacher attendance (legacy daily row, kept for the teachers view). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => [
                'required',
                'uuid',
                Rule::exists('teachers', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,late,excused'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        Attendance::upsert(
            [[
                'id' => (string) Str::uuid(),
                'tenant_id' => $request->user()->tenant_id,
                'teacher_id' => $data['teacher_id'],
                'student_id' => null,
                'recorded_by' => $request->user()->id,
                'date' => $data['date'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['tenant_id', 'teacher_id', 'date'],
            ['status', 'notes', 'recorded_by', 'updated_at']
        );

        app(AuditLogger::class)->log('attendance.teacher_marked', 'teacher', $data['teacher_id'], $request->user()->tenant_id, after: [
            'date' => $data['date'],
            'status' => $data['status'],
        ], actor: $request->user());

        DashboardService::flush($request->user()->tenant_id);

        return back()->with('success', 'تم تسجيل حضور المعلم بنجاح');
    }

    private function roster(Section $section): Collection
    {
        return $section->students()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'gender']);
    }

    /** @param  Collection<int, AttendanceRecord>  $records */
    private function counts($records): array
    {
        return [
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'total' => $records->count(),
        ];
    }
}
