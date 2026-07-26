<?php

namespace App\Actions\Admin\Report;

use App\Models\Attendance;
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

            'attendance' => Attendance::query()
                ->with(['student:id,name', 'teacher:id,name'])
                ->whereBetween('date', [$from, $to])
                ->orderByDesc('date')
                ->orderBy('id')
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
