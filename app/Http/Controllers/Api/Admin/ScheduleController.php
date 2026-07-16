<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schedules = Schedule::query()
            ->with(['classroom:id,name', 'section:id,name', 'subject:id,name', 'teacher:id,name'])
            ->when($request->filled('classroom_id'), fn ($q) => $q->where('classroom_id', $request->input('classroom_id')))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->input('teacher_id')))
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'schedules' => $schedules,
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'teachers' => Teacher::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ]);

        $schedule = Schedule::create($data);

        return response()->json([
            'message' => 'تمت إضافة الحصة',
            'data' => $schedule,
        ], 201);
    }

    public function destroy(Schedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json(['message' => 'تم حذف الحصة']);
    }
}
