<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\Schedule\GenerateWeeklySchedulesAction;
use App\Actions\Admin\Schedule\ResolveScheduleProgramAction;
use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Contracts\Repositories\ScheduleRepositoryInterface;
use App\Contracts\Repositories\SubjectRepositoryInterface;
use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\GenerateScheduleRequest;
use App\Http\Requests\Api\V1\Admin\ScheduleRequest;
use App\Http\Resources\Api\V1\ProgramResource;
use App\Http\Resources\Api\V1\ScheduleResource;
use App\Services\ProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends BaseApiController
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $scheduleRepository,
        private readonly ClassroomRepositoryInterface $classroomRepository,
        private readonly SubjectRepositoryInterface $subjectRepository,
        private readonly TeacherRepositoryInterface $teacherRepository,
        private readonly ProgramService $programService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $schedules = $this->scheduleRepository->getWithFilters([
            'classroom_id' => $request->input('classroom_id'),
            'teacher_id' => $request->input('teacher_id'),
            'program_id' => $request->input('program_id'),
            'study_session_id' => $request->input('study_session_id'),
        ]);

        // البرامج المتاحة للدوام المطلوب (أو الدوام النشط): برامج الدوام
        // المحددة فقط، وإن لم تُحدد له برامج تظهر كل البرامج.
        $sessionId = $request->input('study_session_id') ?: config('app.current_study_session_id');

        $programs = $this->programService->availablePrograms($sessionId, ['periods']);

        return $this->success([
            'schedules' => ScheduleResource::collection($schedules),
            'classrooms' => $this->classroomRepository->allWithSectionsAndCounts(),
            'subjects' => $this->subjectRepository->all()->sortBy('name')->values(),
            'teachers' => $this->teacherRepository->activeTeachers(),
            'programs' => ProgramResource::collection($programs),
        ]);
    }

    public function store(ScheduleRequest $request, ResolveScheduleProgramAction $resolveProgram): JsonResponse
    {
        $schedule = $this->scheduleRepository->create(
            $resolveProgram->execute($request->validated())
        );

        return $this->created(
            ScheduleResource::make($schedule->load(['program', 'programPeriod', 'studySession'])),
            'تمت إضافة الحصة'
        );
    }

    /** توليد جدول أسبوعي لبرنامج/فترة عبر عدة أيام (تخطي الموجود، رفض التعارض). */
    public function generate(GenerateScheduleRequest $request, GenerateWeeklySchedulesAction $generate): JsonResponse
    {
        $result = $generate->execute($request->validated());

        return $this->created(
            $result,
            "تم توليد {$result['created']} حصة أسبوعية"
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->scheduleRepository->delete($id);

        return $this->success(message: 'تم حذف الحصة');
    }
}
