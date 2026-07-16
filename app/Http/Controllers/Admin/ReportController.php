<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
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

            'attendance' => Attendance::query()
                ->with(['student:id,name', 'teacher:id,name'])
                ->whereBetween('date', [$from, $to])
                ->orderByDesc('date')
                ->lazy(500),

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
}
