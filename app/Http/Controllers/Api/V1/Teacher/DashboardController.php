<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\ScheduleRepositoryInterface;
use App\Http\Resources\Api\V1\ScheduleResource;
use App\Models\HomeworkSubmission;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseTeacherController
{
    public function index(Request $request, DashboardService $dashboard, ScheduleRepositoryInterface $scheduleRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $todaySchedule = $scheduleRepository->getForTeacher($teacher->id)->get(now()->dayOfWeek, collect());

        $pendingSubmissions = HomeworkSubmission::query()
            ->where('status', 'pending')
            ->whereHas('homework', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->count();

        return $this->success([
            'teacher' => $teacher,
            'today_schedule' => ScheduleResource::collection($todaySchedule),
            'pending_submissions' => $pendingSubmissions,
            'announcements' => $dashboard->latestAnnouncements($request->user()->tenant_id),
        ]);
    }
}
