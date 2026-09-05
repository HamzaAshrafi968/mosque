<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\Teacher\Attendance\SaveAttendanceAction;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Teacher;
use App\Services\AttendanceMetricService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceController extends BaseTeacherController
{
    /** Roster picker + take/mark attendance screen. */
    public function create(Request $request): View
    {
        $date = $request->input('date', now()->toDateString());
        $sectionId = $request->input('section_id');

        $students = collect();
        $existing = collect();

        $section = $sectionId ? Section::find($sectionId) : null;

        if ($section) {
            $this->assertCanManageSection($request, $section);
            $students = $this->roster($section);
            $existing = $this->existingForDate($date, $students);
        }

        return view('teacher.attendance.create', [
            'sections' => $this->manageableSections($request),
            'students' => $students,
            'existing' => $existing,
            'section' => $section,
            'date' => $date,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function store(Request $request, SaveAttendanceAction $action): RedirectResponse
    {
        $data = $this->validated($request);

        $action->execute($data, $request->user());

        return back()->with('success', 'تم حفظ الحضور بنجاح');
    }

    /** Edit a previously saved session for one of the teacher's sections. */
    public function edit(Request $request, AttendanceSession $session): View
    {
        $this->assertCanManageSection($request, $session->section);
        $session->load(['section.classroom', 'records.student']);

        $records = $session->records->keyBy('student_id');

        return view('teacher.attendance.edit', [
            'session' => $session,
            'students' => $this->roster($session->section),
            'records' => $records,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function update(Request $request, AttendanceSession $session, SaveAttendanceAction $action): RedirectResponse
    {
        $this->assertCanManageSection($request, $session->section);

        $action->execute($this->validated($request), $request->user());

        return redirect()
            ->route('teacher.attendance.edit', $session)
            ->with('success', 'تم تحديث الحضور');
    }

    /** Attendance history table (spec §11). */
    public function history(Request $request): View
    {
        $date = $request->input('date', now()->toDateString());
        $sectionId = $request->input('section_id');

        $sections = $this->manageableSections($request);
        $section = $sectionId ? $sections->firstWhere('id', $sectionId) : $sections->first();

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $grid = $section
            ? app(AttendanceMetricService::class)->grid($section, $from, $to)
            : ['sessions' => collect(), 'rows' => []];

        return view('teacher.attendance.history', [
            'sections' => $sections,
            'section' => $section,
            'from' => $from,
            'to' => $to,
            'sessions' => $grid['sessions'],
            'rows' => $grid['rows'],
            'date' => $date,
        ]);
    }

    private function roster(Section $section): Collection
    {
        return $section->students()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'gender']);
    }

    private function existingForDate(string $date, $students): Collection
    {
        return AttendanceRecord::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereHas('session', fn ($q) => $q->whereDate('date', $date))
            ->pluck('status', 'student_id');
    }

    private function validated(Request $request): array
    {
        $allowed = implode(',', collect(AttendanceStatus::cases())->pluck('value')->all());

        $data = $request->validate([
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ["in:{$allowed}"],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:500'],
        ]);

        if ($sectionId = $request->input('section_id')) {
            $section = Section::find($sectionId);

            if ($section) {
                $this->assertCanManageSection($request, $section);
            }
        }

        return $data;
    }
}
