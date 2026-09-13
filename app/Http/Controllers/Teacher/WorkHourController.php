<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\WorkDay;
use App\Models\TeacherWorkHour;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkHourController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $hours = $teacher->workHours()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return view('teacher.work-hours.index', [
            'teacher' => $teacher,
            'hours' => $hours,
            'days' => WorkDay::cases(),
            'weeklyTotal' => TeacherWorkHour::weeklyTotalHours($teacher->id),
            'todayHours' => $hours->get(now()->dayOfWeek, collect()),
        ]);
    }
}
