<?php

namespace App\Http\Controllers\Teacher;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $schedules = Schedule::query()
            ->with(['classroom:id,name', 'section:id,name', 'subject:id,name'])
            ->where('teacher_id', $teacher->id)
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('day_of_week');

        return view('teacher.schedule', ['schedules' => $schedules]);
    }
}
