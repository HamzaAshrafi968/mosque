<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\Teacher\CreateTeacherAction;
use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreTeacherRequest;
use App\Http\Requests\Api\V1\Admin\UpdateTeacherRequest;
use App\Http\Resources\Api\V1\TeacherResource;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherController extends BaseApiController
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teacherRepository,
        private readonly CreateTeacherAction $createTeacher,
        private readonly CustomFieldService $customFields,
        private readonly AuditLogger $audit,
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
        $data = $request->validated();
        $custom = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        $this->customFields->validate(Teacher::CUSTOM_FIELD_ENTITY, $custom);

        $teacher = $this->createTeacher->execute(
            $this->teacherRepository,
            $data,
            $request
        );

        DB::transaction(fn () => $this->customFields->save(Teacher::CUSTOM_FIELD_ENTITY, $teacher->id, $custom));

        $this->audit->logModel('teacher.created', $teacher, actor: $request->user());

        return $this->created(
            TeacherResource::make($teacher),
            'تمت إضافة المعلم بنجاح'
        );
    }

    public function update(UpdateTeacherRequest $request, string $id): JsonResponse
    {
        $teacher = $this->teacherRepository->findOrFail($id);

        $data = $request->validated();
        $custom = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        // Merge already-stored values so partial updates do not lose data
        // and required-field rules keep passing for unchanged fields.
        $custom = array_merge($this->customFields->valuesFor(Teacher::CUSTOM_FIELD_ENTITY, $teacher->id), $custom);

        $this->customFields->validate(Teacher::CUSTOM_FIELD_ENTITY, $custom);

        $before = $teacher->getAttributes();
        $teacher = $this->teacherRepository->update($id, $data);

        DB::transaction(fn () => $this->customFields->save(Teacher::CUSTOM_FIELD_ENTITY, $teacher->id, $custom));

        $this->audit->logModel('teacher.updated', $teacher, $before, actor: $request->user());

        return $this->success(
            TeacherResource::make($teacher),
            'تم تحديث بيانات المعلم'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $teacher = $this->teacherRepository->findOrFail($id);
        $this->audit->logModel('teacher.deleted', $teacher, actor: $request->user());

        $this->teacherRepository->delete($id);

        return $this->success(message: 'تم حذف المعلم');
    }
}
