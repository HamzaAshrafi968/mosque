<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\SubjectRepositoryInterface;
use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreSubjectRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSubjectRequest;
use App\Http\Resources\Api\V1\SubjectResource;
use Illuminate\Http\JsonResponse;

class SubjectController extends BaseApiController
{
    public function __construct(
        private readonly SubjectRepositoryInterface $subjectRepository,
        private readonly TeacherRepositoryInterface $teacherRepository,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success([
            'subjects' => SubjectResource::collection($this->subjectRepository->allWithTeacher()),
            'teachers' => $this->teacherRepository->activeTeachers(),
        ]);
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjectRepository->create($request->validated());

        return $this->created(
            SubjectResource::make($subject),
            'تمت إضافة المادة'
        );
    }

    public function update(UpdateSubjectRequest $request, string $id): JsonResponse
    {
        $subject = $this->subjectRepository->update($id, $request->validated());

        return $this->success(
            SubjectResource::make($subject),
            'تم تحديث المادة'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->subjectRepository->delete($id);

        return $this->success(message: 'تم حذف المادة');
    }
}
