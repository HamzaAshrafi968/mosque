<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Enums\SectionTeacherRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreClassroomRequest;
use App\Http\Requests\Api\V1\Admin\StoreSectionRequest;
use App\Http\Resources\Api\V1\ClassroomResource;
use App\Http\Resources\Api\V1\SectionResource;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassroomController extends BaseApiController
{
    public function __construct(
        private readonly ClassroomRepositoryInterface $classroomRepository,
        private readonly EnrollmentService $enrollment,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success([
            'classrooms' => ClassroomResource::collection(
                $this->classroomRepository->allWithSectionsAndCounts()
            ),
        ]);
    }

    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $classroom = $this->classroomRepository->create($request->validated());

        return $this->created(
            ClassroomResource::make($classroom),
            'تم إنشاء الصف'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $classroom = $this->classroomRepository->findOrFail($id);

        if ($classroom->sections()->whereHas('sectionStudents', fn ($q) => $q->where('status', 'active'))->exists()) {
            return $this->error('لا يمكن حذف صف يحتوي شعباً بها طلاب نشطون', 422);
        }

        $this->classroomRepository->delete($id);

        return $this->success(message: 'تم حذف الصف');
    }

    public function storeSection(StoreSectionRequest $request, string $classroomId): JsonResponse
    {
        $section = $this->classroomRepository->createSection(
            $classroomId,
            [...$request->validated(), 'tenant_id' => config('app.current_tenant_id')]
        );

        return $this->created(
            SectionResource::make($section),
            'تم إنشاء الشعبة'
        );
    }

    /** Section detail with active roster, teachers and memberships (spec §8.2). */
    public function showSection(string $sectionId): JsonResponse
    {
        $section = Section::with([
            'classroom:id,name',
            'teacherAssignments.teacher:id,name,phone,is_active',
        ])->find($sectionId);

        if (! $section) {
            return $this->notFound('الشعبة غير موجودة');
        }

        $students = $section->students()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'gender', 'guardian_name', 'status']);

        return $this->success([
            'section' => SectionResource::make($section)->additional([]),
            'classroom' => ['id' => $section->classroom->id, 'name' => $section->classroom->name],
            'students' => $students,
            'teachers' => $section->teacherAssignments->map(fn ($assignment) => [
                'id' => $assignment->teacher->id,
                'name' => $assignment->teacher->name,
                'phone' => $assignment->teacher->phone,
                'role' => $assignment->role->value,
                'status' => $assignment->status,
                'assignment_id' => $assignment->id,
            ]),
        ]);
    }

    public function updateSection(Request $request, string $sectionId): JsonResponse
    {
        $section = Section::findOrFail($sectionId);

        $section->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]));

        return $this->success(SectionResource::make($section), 'تم تحديث الشعبة');
    }

    public function destroySection(string $sectionId): JsonResponse
    {
        $section = Section::findOrFail($sectionId);

        if ($section->sectionStudents()->where('status', 'active')->exists()) {
            return $this->error('لا يمكن حذف شعبة بها طلاب نشطون', 422);
        }

        $this->classroomRepository->deleteSection($sectionId);

        return $this->success(message: 'تم حذف الشعبة');
    }

    /** Enroll an existing student into the section. */
    public function enrollStudent(Request $request, string $sectionId): JsonResponse
    {
        $section = Section::findOrFail($sectionId);

        $data = $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')],
        ]);

        try {
            $this->enrollment->enroll(Student::findOrFail($data['student_id']), $section);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->created(message: 'تم تسجيل الطالب في الشعبة');
    }

    public function removeStudent(string $sectionId, string $studentId): JsonResponse
    {
        $section = Section::findOrFail($sectionId);
        $student = Student::findOrFail($studentId);

        $membership = $this->enrollment->currentMembership($student);

        if (! $membership || $membership->section_id !== $section->id) {
            return $this->error('الطالب غير مسجل في هذه الشعبة', 422);
        }

        $this->enrollment->removeFromSection($student);

        return $this->success(message: 'تم إخراج الطالب من الشعبة مع حفظ سجل العضوية');
    }

    public function assignTeacher(Request $request, string $sectionId): JsonResponse
    {
        $section = Section::findOrFail($sectionId);

        $data = $request->validate([
            'teacher_id' => ['required', 'uuid', Rule::exists('teachers', 'id')],
            'role' => ['nullable', 'in:lead,assistant'],
        ]);

        try {
            $assignment = $this->enrollment->assignTeacher(
                $section,
                Teacher::findOrFail($data['teacher_id']),
                SectionTeacherRole::tryFrom($data['role'] ?? 'lead') ?? SectionTeacherRole::Lead
            );
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->created(message: 'تم تكليف المعلم بالشعبة', data: ['assignment_id' => $assignment->id]);
    }

    public function removeTeacher(string $sectionId, string $teacherId): JsonResponse
    {
        $section = Section::findOrFail($sectionId);
        $teacher = Teacher::findOrFail($teacherId);

        $this->enrollment->removeTeacher($section, $teacher);

        return $this->success(message: 'تم إنهاء تكليف المعلم بالشعبة');
    }
}
