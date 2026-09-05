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
use App\Models\Section;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends BaseApiController
{
    public function __construct(
        private readonly StudentRepositoryInterface $studentRepository,
        private readonly ClassroomRepositoryInterface $classroomRepository,
        private readonly ListStudentsAction $listStudents,
        private readonly ShowStudentAction $showStudent,
        private readonly CustomFieldService $customFields,
        private readonly EnrollmentService $enrollment,
        private readonly AuditLogger $audit,
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
        $data = $request->validated();
        $custom = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        $this->customFields->validate(Student::CUSTOM_FIELD_ENTITY, $custom);

        $student = $this->studentRepository->create($data);

        DB::transaction(fn () => $this->customFields->save(Student::CUSTOM_FIELD_ENTITY, $student->id, $custom));

        if (! empty($data['section_id'])) {
            $this->enrollment->syncPlacement($student, $data['section_id']);
        }

        $this->audit->logModel('student.created', $student, actor: $request->user());

        return $this->created(
            StudentResource::make($student->load(['classroom:id,name', 'section:id,name'])),
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
        $student = $this->studentRepository->findOrFail($id);

        $data = $request->validated();
        $custom = $data['custom_fields'] ?? [];
        unset($data['custom_fields'], $data['section_id']);

        // Merge already-stored values so partial updates do not lose data
        // and required-field rules keep passing for unchanged fields.
        $custom = array_merge($this->customFields->valuesFor(Student::CUSTOM_FIELD_ENTITY, $student->id), $custom);

        $this->customFields->validate(Student::CUSTOM_FIELD_ENTITY, $custom);

        $before = $student->getAttributes();
        $student = $this->studentRepository->update($id, $data);

        DB::transaction(fn () => $this->customFields->save(Student::CUSTOM_FIELD_ENTITY, $student->id, $custom));

        if ($request->filled('section_id')) {
            $this->enrollment->syncPlacement($student, $request->input('section_id'));
        }

        $this->audit->logModel('student.updated', $student, $before, actor: $request->user());

        return $this->success(
            StudentResource::make($student->fresh(['classroom:id,name', 'section:id,name'])),
            'تم تحديث بيانات الطالب'
        );
    }

    /** Transfer to another section preserving membership history. */
    public function transfer(Request $request, string $id): JsonResponse
    {
        $student = $this->studentRepository->findOrFail($id);

        $data = $request->validate([
            'section_id' => ['required', 'uuid', Rule::exists('sections', 'id')],
        ]);

        $target = Section::find($data['section_id']);

        if (! $target) {
            return $this->notFound('الشعبة غير موجودة');
        }

        $this->enrollment->transfer($student, $target);

        return $this->success([
            'student' => StudentResource::make($student->fresh(['classroom:id,name', 'section:id,name'])),
        ], 'تم نقل الطالب مع حفظ سجل الشعب السابقة');
    }

    public function archive(string $id): JsonResponse
    {
        $student = $this->studentRepository->findOrFail($id);

        DB::transaction(function () use ($student, $request) {
            if ($student->status !== 'archived') {
                $this->enrollment->removeFromSection($student);
            }

            $this->studentRepository->toggleArchive($student->id);
            $this->audit->log('student.archived', 'student', $student->id, $student->tenant_id,
                after: ['status' => $student->status === 'archived' ? 'active' : 'archived'],
                actor: $request->user()
            );
        });

        return $this->success(message: 'تم تحديث حالة الطالب');
    }

    public function destroy(string $id): JsonResponse
    {
        $student = $this->studentRepository->findOrFail($id);
        $this->audit->logModel('student.deleted', $student, actor: $request->user());

        $this->studentRepository->delete($id);

        return $this->success(message: 'تم حذف الطالب');
    }
}
