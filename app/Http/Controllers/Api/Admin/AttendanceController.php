<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        $type = $request->input('type', 'students');

        $attendances = Attendance::query()
            ->with(['student:id,name,classroom_id', 'student.classroom:id,name', 'teacher:id,name'])
            ->whereDate('date', $date)
            ->when($type === 'students', fn ($q) => $q->whereNotNull('student_id'), fn ($q) => $q->whereNotNull('teacher_id'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->paginate(30)
            ->withQueryString();

        return response()->json([
            'attendances' => $attendances,
            'date' => $date,
            'type' => $type,
        ]);
    }
}
