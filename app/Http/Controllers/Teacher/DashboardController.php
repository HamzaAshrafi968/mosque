<?php

namespace App\Http\Controllers\Teacher;

use App\Models\HomeworkSubmission;
use App\Models\Schedule;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends BaseTeacherController
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        $teacher = $this->currentTeacher($request);

        $todaySchedule = Schedule::query()
            ->with(['classroom:id,name', 'section:id,name', 'subject:id,name'])
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', now()->dayOfWeek)
            ->orderBy('starts_at')
            ->get();

        $pendingSubmissions = HomeworkSubmission::query()
            ->where('status', 'pending')
            ->whereHas('homework', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->count();

        return view('teacher.dashboard', [
            'teacher' => $teacher,
            'todaySchedule' => $todaySchedule,
            'pendingSubmissions' => $pendingSubmissions,
            'announcements' => $dashboard->latestAnnouncements($request->user()->tenant_id),
        ]);
    }
}
