<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'students');
        $from = $request->input('from', now()->subMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $rows = match ($type) {
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

        return response()->json([
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ]);
    }
}
