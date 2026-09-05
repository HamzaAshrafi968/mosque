<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\Teacher\Grade\SaveGradesAction;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeController extends BaseTeacherController
{
    public function edit(Request $request, Exam $exam): View
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

        $grades = Grade::where('exam_id', $exam->id)->get()->keyBy('student_id');

        return view('teacher.grades.edit', [
            'exam' => $exam,
            'students' => $students,
            'grades' => $grades,
        ]);
    }

    public function store(Request $request, Exam $exam, SaveGradesAction $action): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($exam->teacher_id === $teacher->id, 403);

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0', 'max:'.$exam->total_marks],
            'action' => ['required', 'in:save,submit'],
        ]);

        $status = $action->execute($data, $exam);

        return back()->with('success', $status === 'submitted'
            ? 'تم إرسال الدرجات للإدارة لاعتمادها'
            : 'تم حفظ الدرجات');
    }
}
