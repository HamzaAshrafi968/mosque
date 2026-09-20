<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\Schedule\SaveScheduleSlotsAction;
use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Contracts\Repositories\ScheduleRepositoryInterface;
use App\Contracts\Repositories\SubjectRepositoryInterface;
use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
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

    /**
     * النموذج الموحّد: `days[]` + `duration` (يوم/أسبوع/شهر/حتى انتهاء الدورة/
     * مفتوحة). يبقى `day_of_week` مدعوماً للتوافق الخلفي ويعيد حصة واحدة.
     */
    public function store(ScheduleRequest $request, SaveScheduleSlotsAction $save): JsonResponse
    {
        $data = $request->validated();
        $batch = array_key_exists('days', $data);

        $result = $save->execute($data, skipDuplicates: $batch);

        if ($batch) {
            return $this->created([
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'schedules' => ScheduleResource::collection(
                    $result['schedules']->load(['program', 'programPeriod', 'studySession'])
                ),
            ], "تمت إضافة {$result['created']} حصة");
        }

        $schedule = $result['schedules']->firstOrFail();

        return $this->created(
            ScheduleResource::make($schedule->load(['program', 'programPeriod', 'studySession'])),
            'تمت إضافة الحصة'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->scheduleRepository->delete($id);

        return $this->success(message: 'تم حذف الحصة');
    }
}
