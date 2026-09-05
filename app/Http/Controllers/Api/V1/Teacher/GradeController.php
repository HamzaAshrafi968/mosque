<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\Grade\SaveGradesAction;
use App\Contracts\Repositories\GradeRepositoryInterface;
use App\Http\Requests\Api\V1\Teacher\StoreGradesRequest;
use App\Models\Exam;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends BaseTeacherController
{
    public function show(Request $request, Exam $exam, GradeRepositoryInterface $gradeRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($exam->teacher_id === $teacher->id, 403);

        $exam->load(['subject:id,name', 'classroom:id,name', 'section:id,name']);

        $students = Student::query()
            ->active()
            ->where('classroom_id', $exam->classroom_id)
            ->when($exam->section_id, fn ($q) => $q->where('section_id', $exam->section_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $grades = $gradeRepository->getByExam($exam->id)->keyBy('student_id');

        return $this->success([
            'exam' => $exam,
            'students' => $students,
            'grades' => $grades,
        ]);
    }

    public function store(StoreGradesRequest $request, Exam $exam, SaveGradesAction $action): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($exam->teacher_id === $teacher->id, 403);

        $status = $action->execute($request->validated(), $exam);

        return $this->success(message: $status === 'submitted'
            ? 'تم إرسال الدرجات للإدارة لاعتمادها'
            : 'تم حفظ الدرجات');
    }
}
