<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\Teacher\CreateTeacherAction;
use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreTeacherRequest;
use App\Http\Requests\Api\V1\Admin\UpdateTeacherRequest;
use App\Http\Resources\Api\V1\TeacherResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends BaseApiController
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teacherRepository,
        private readonly CreateTeacherAction $createTeacher,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $teachers = $this->teacherRepository->paginateWithFilters([
            'q' => $request->input('q'),
            'gender' => $request->input('gender'),
        ]);

        return $this->success([
            'teachers' => TeacherResource::collection($teachers),
        ]);
    }

    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $teacher = $this->createTeacher->execute(
            $this->teacherRepository,
            $request->validated(),
            $request
        );

        return $this->created(
            TeacherResource::make($teacher),
            'تمت إضافة المعلم بنجاح'
        );
    }

    public function update(UpdateTeacherRequest $request, string $id): JsonResponse
    {
        $teacher = $this->teacherRepository->update($id, $request->validated());

        return $this->success(
            TeacherResource::make($teacher),
            'تم تحديث بيانات المعلم'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->teacherRepository->delete($id);

        return $this->success(message: 'تم حذف المعلم');
    }
}
