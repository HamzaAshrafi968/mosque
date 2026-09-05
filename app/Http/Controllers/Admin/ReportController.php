<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->input('type', 'students');
        $from = $request->input('from', now()->subMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $rows = match ($type) {
            'teachers' => Teacher::query()
                ->withCount('subjects')
                ->orderBy('name')
                ->lazy(200),

            'attendance' => $this->attendanceRows($from, $to),

            'grades' => Grade::query()
                ->with(['student:id,name', 'exam:id,title,total_marks,subject_id', 'exam.subject:id,name'])
                ->where('status', 'approved')
                ->orderByDesc('created_at')
                ->lazy(500),

            default => Student::query()
                ->with(['classroom:id,name', 'section:id,name'])
                ->orderBy('name')
                ->lazy(200),
        };

        return view('admin.reports.index', [
            'type' => $type,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Attendance report rows: session-based student records (source of truth)
     * merged with the legacy daily teacher attendance rows.
     */
    private function attendanceRows(string $from, string $to): Collection
    {
        $students = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->whereBetween('attendance_sessions.date', [$from, $to])
            ->get(['attendance_records.status', 'attendance_records.note', 'attendance_sessions.date', 'attendance_records.student_id']);

        $studentNames = Student::query()
            ->whereIn('id', $students->pluck('student_id'))
            ->pluck('name', 'id');

        $studentRows = $students->map(fn ($row) => (object) [
            'date' => Carbon::parse($row->date),
            'person_name' => $studentNames[$row->student_id] ?? '—',
            'kind' => 'student',
            'status' => $row->status,
            'note' => $row->note,
        ]);

        $teachers = Attendance::query()
            ->whereBetween('date', [$from, $to])
            ->get(['date', 'status', 'notes', 'teacher_id']);

        $teacherNames = Teacher::query()
            ->whereIn('id', $teachers->pluck('teacher_id'))
            ->pluck('name', 'id');

        $teacherRows = $teachers->map(fn ($row) => (object) [
            'date' => Carbon::parse($row->date),
            'person_name' => $teacherNames[$row->teacher_id] ?? '—',
            'kind' => 'teacher',
            'status' => $row->status,
            'note' => $row->notes,
        ]);

        return $studentRows->concat($teacherRows)->sortByDesc('date')->values();
    }
}
