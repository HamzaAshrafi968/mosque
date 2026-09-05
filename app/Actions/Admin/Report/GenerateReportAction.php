<?php

namespace App\Actions\Admin\Report;

use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Contracts\Pagination\CursorPaginator;

class GenerateReportAction
{
    public function execute(array $filters): CursorPaginator
    {
        $type = $filters['type'] ?? 'students';
        $from = $filters['from'] ?? now()->subMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return match ($type) {
            'teachers' => Teacher::query()
                ->withCount('subjects')
                ->orderBy('name')
                ->orderBy('id')
                ->cursorPaginate(100),

            'attendance' => AttendanceRecord::query()
                ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
                ->whereBetween('attendance_sessions.date', [$from, $to])
                ->with(['student:id,name,section_id', 'session.section:id,name,classroom_id', 'session.section.classroom:id,name'])
                ->select('attendance_records.*')
                ->orderByDesc('attendance_sessions.date')
                ->orderBy('attendance_records.id')
                ->cursorPaginate(100),

            'grades' => Grade::query()
                ->with(['student:id,name', 'exam:id,title,total_marks,subject_id', 'exam.subject:id,name'])
                ->where('status', 'approved')
                ->orderByDesc('created_at')
                ->orderBy('id')
                ->cursorPaginate(100),

            default => Student::query()
                ->with(['classroom:id,name', 'section:id,name'])
                ->orderBy('name')
                ->orderBy('id')
                ->cursorPaginate(100),
        };
    }
}
