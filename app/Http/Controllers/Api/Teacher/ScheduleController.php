<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends BaseTeacherController
{
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $schedules = Schedule::query()
            ->with(['classroom:id,name', 'section:id,name', 'subject:id,name'])
            ->where('teacher_id', $teacher->id)
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('day_of_week');

        return response()->json(['schedules' => $schedules]);
    }
}
