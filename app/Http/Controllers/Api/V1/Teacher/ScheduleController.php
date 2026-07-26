<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\ScheduleRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends BaseTeacherController
{
    public function index(Request $request, ScheduleRepositoryInterface $scheduleRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $schedules = $scheduleRepository->getForTeacher($teacher->id);

        return $this->success([
            'schedules' => $schedules,
        ]);
    }
}
