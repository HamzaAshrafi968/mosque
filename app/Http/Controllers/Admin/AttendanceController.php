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
use App\Models\Student;
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

    /** Per-student summary: attendance, absence and lateness for a date range. */
    public function summary(Request $request, AttendanceMetricService $metrics): View
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $sectionId = $request->input('section_id');

        $students = Student::query()
            ->with(['classroom:id,name', 'section:id,name,classroom_id'])
            ->active()
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $rows = $metrics->studentStats($students->getCollection(), $from, $to)->values();

        // Range-wide totals in a single aggregate query (page-independent),
        // mirroring the same filters as studentStats: active students in the
        // selected section and records of sessions between the two dates.
        $totals = AttendanceRecord::query()
            ->whereHas('student', fn ($q) => $q->active()
                ->when($sectionId, fn ($s) => $s->where('section_id', $sectionId)))
            ->whereHas('session', fn ($s) => $s->whereBetween('date', [$from, $to]))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) $totals->get('present', 0);
        $late = (int) $totals->get('late', 0);
        $absent = (int) $totals->get('absent', 0);
        $excused = (int) $totals->get('excused', 0);

        $totals = [
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused,
            'total' => $present + $late + $absent,
            'attended' => $present + $late,
        ];

        $totals['percentage'] = $totals['total'] > 0
            ? round(($totals['attended'] / $totals['total']) * 100, 1)
            : null;

        $paginator = $students->setCollection($rows);

        return view('admin.attendance.summary', [
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
            'rows' => $paginator,
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
        ]);
    }

    /**
     * "حضور اليوم": شجرة الصفوف ← الشعب ← الطلاب مع عدّادات كل مستوى.
     * تُعرض كل صفوف الجامع (كل الدوامات) مثل نسبة لوحة القيادة.
     */
    public function today(Request $request): View
    {
        $date = $request->input('date', now()->toDateString());
        $status = $request->input('status', 'attended');

        // Today's session-based records keyed by student (one status per day).
        $records = AttendanceRecord::query()
            ->with(['session:id,section_id,date,starts_at,created_by', 'session.createdBy:id,name'])
            ->whereHas('session', fn ($q) => $q->whereDate('date', $date))
            ->get()
            ->keyBy('student_id');

        // Active students grouped by section, across all shifts.
        $studentsBySection = Student::query()
            ->withoutGlobalScope('study_session')
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'classroom_id', 'section_id'])
            ->groupBy('section_id');

        $classrooms = Classroom::query()
            ->withoutGlobalScope('study_session')
            ->with(['sections' => fn ($q) => $q->withoutGlobalScope('study_session')->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        $tree = $classrooms->map(function (Classroom $classroom) use ($studentsBySection, $records, $status): array {
            $sections = $classroom->sections->map(function (Section $section) use ($studentsBySection, $records, $status): array {
                $students = $studentsBySection->get($section->id) ?? collect();

                return [
                    'section' => $section,
                    'counts' => $this->statusCounts($students, $records),
                    'students' => $this->filteredRows($students, $records, $status),
                ];
            })->values();

            return [
                'classroom' => $classroom,
                'counts' => $this->mergeCounts($sections->pluck('counts')->all()),
                'sections' => $sections,
            ];
        })->values();

        // Students without a section stay visible in a trailing group.
        $unassigned = $studentsBySection->get('');

        if ($unassigned?->isNotEmpty()) {
            $counts = $this->statusCounts($unassigned, $records);

            $tree->push([
                'classroom' => null,
                'counts' => $counts,
                'sections' => collect([[
                    'section' => null,
                    'counts' => $counts,
                    'students' => $this->filteredRows($unassigned, $records, $status),
                ]]),
            ]);
        }

        $present = $records->filter(fn (AttendanceRecord $record) => $record->status === AttendanceStatus::Present)->count();
        $late = $records->filter(fn (AttendanceRecord $record) => $record->status === AttendanceStatus::Late)->count();
        $absent = $records->filter(fn (AttendanceRecord $record) => $record->status === AttendanceStatus::Absent)->count();
        $excused = $records->filter(fn (AttendanceRecord $record) => $record->status === AttendanceStatus::Excused)->count();
        $attended = $present + $late;

        // The rate is measured against every active student of the mosque,
        // not only the recorded ones.
        $studentsCount = $tree->sum(fn (array $node) => $node['counts']['total']);

        $summary = [
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused,
            'attended' => $attended,
            'total' => $studentsCount,
            'percentage' => $studentsCount > 0
                ? round(($attended / $studentsCount) * 100, 1)
                : null,
        ];

        return view('admin.attendance.today', [
            'tree' => $tree,
            'summary' => $summary,
            'studentsCount' => $studentsCount,
            'date' => $date,
            'status' => $status,
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

    /**
     * Per-status counts for one group of students against today's records.
     *
     * @param  Collection<int, Student>  $students
     * @param  Collection<string, AttendanceRecord>  $records
     * @return array{total:int, present:int, late:int, absent:int, excused:int, unrecorded:int}
     */
    private function statusCounts(Collection $students, Collection $records): array
    {
        $counts = [
            'total' => $students->count(),
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'excused' => 0,
            'unrecorded' => 0,
        ];

        foreach ($students as $student) {
            $status = $records->get($student->id)?->status?->value;

            if ($status !== null && array_key_exists($status, $counts)) {
                $counts[$status]++;
            } else {
                $counts['unrecorded']++;
            }
        }

        return $counts;
    }

    /**
     * Sum the counts of several groups (sections → classroom totals).
     *
     * @param  array<int, array<string, int>>  $counts
     * @return array{total:int, present:int, late:int, absent:int, excused:int, unrecorded:int}
     */
    private function mergeCounts(array $counts): array
    {
        $merged = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'unrecorded' => 0];

        foreach ($counts as $group) {
            foreach ($group as $key => $value) {
                $merged[$key] += $value;
            }
        }

        return $merged;
    }

    /**
     * Student rows of one group, filtered by the active status tab.
     *
     * @param  Collection<int, Student>  $students
     * @param  Collection<string, AttendanceRecord>  $records
     * @return Collection<int, array{student: Student, record: ?AttendanceRecord}>
     */
    private function filteredRows(Collection $students, Collection $records, string $status): Collection
    {
        return $students
            ->map(fn (Student $student): array => [
                'student' => $student,
                'record' => $records->get($student->id),
            ])
            ->filter(fn (array $row): bool => $this->matchesStatusFilter($row['record']?->status, $status))
            ->values();
    }

    private function matchesStatusFilter(?AttendanceStatus $status, string $filter): bool
    {
        return match ($filter) {
            'present', 'late', 'absent', 'excused' => $status?->value === $filter,
            'attended' => in_array($status?->value, ['present', 'late'], true),
            default => true,
        };
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
