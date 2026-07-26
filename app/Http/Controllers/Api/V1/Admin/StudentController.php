<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\Student\ListStudentsAction;
use App\Actions\Admin\Student\ShowStudentAction;
use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Contracts\Repositories\StudentRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreStudentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateStudentRequest;
use App\Http\Resources\Api\V1\StudentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends BaseApiController
{
    public function __construct(
        private readonly StudentRepositoryInterface $studentRepository,
        private readonly ClassroomRepositoryInterface $classroomRepository,
        private readonly ListStudentsAction $listStudents,
        private readonly ShowStudentAction $showStudent,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $students = $this->listStudents->execute($this->studentRepository, [
            'q' => $request->string('q')->toString(),
            'classroom_id' => $request->input('classroom_id'),
            'gender' => $request->input('gender'),
            'status' => $request->input('status'),
        ]);

        return $this->success([
            'students' => StudentResource::collection($students),
            'classrooms' => $this->classroomRepository->sortedList(),
        ]);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->studentRepository->create($request->validated());

        return $this->created(
            StudentResource::make($student),
            'تمت إضافة الطالب بنجاح'
        );
    }

    public function show(string $id): JsonResponse
    {
        $student = $this->showStudent->execute($this->studentRepository, $id);

        if (! $student) {
            return $this->notFound('الطالب غير موجود');
        }

        return $this->success(new StudentResource($student));
    }

    public function update(UpdateStudentRequest $request, string $id): JsonResponse
    {
        $student = $this->studentRepository->update($id, $request->validated());

        return $this->success(
            StudentResource::make($student),
            'تم تحديث بيانات الطالب'
        );
    }

    public function archive(string $id): JsonResponse
    {
        $this->studentRepository->toggleArchive($id);

        return $this->success(message: 'تم تحديث حالة الطالب');
    }

    public function destroy(string $id): JsonResponse
    {
        $this->studentRepository->delete($id);

        return $this->success(message: 'تم حذف الطالب');
    }
}
