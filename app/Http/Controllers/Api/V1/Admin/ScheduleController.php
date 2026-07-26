<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Contracts\Repositories\ScheduleRepositoryInterface;
use App\Contracts\Repositories\SubjectRepositoryInterface;
use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\ScheduleRequest;
use App\Http\Resources\Api\V1\ScheduleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends BaseApiController
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $scheduleRepository,
        private readonly ClassroomRepositoryInterface $classroomRepository,
        private readonly SubjectRepositoryInterface $subjectRepository,
        private readonly TeacherRepositoryInterface $teacherRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $schedules = $this->scheduleRepository->getWithFilters([
            'classroom_id' => $request->input('classroom_id'),
            'teacher_id' => $request->input('teacher_id'),
        ]);

        return $this->success([
            'schedules' => ScheduleResource::collection($schedules),
            'classrooms' => $this->classroomRepository->allWithSectionsAndCounts(),
            'subjects' => $this->subjectRepository->all()->sortBy('name')->values(),
            'teachers' => $this->teacherRepository->activeTeachers(),
        ]);
    }

    public function store(ScheduleRequest $request): JsonResponse
    {
        $schedule = $this->scheduleRepository->create($request->validated());

        return $this->created(
            ScheduleResource::make($schedule),
            'تمت إضافة الحصة'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->scheduleRepository->delete($id);

        return $this->success(message: 'تم حذف الحصة');
    }
}
