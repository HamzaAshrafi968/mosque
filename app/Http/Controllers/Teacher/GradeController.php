<?php

namespace App\Http\Controllers\Teacher;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function store(Request $request, Exam $exam): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($exam->teacher_id === $teacher->id, 403);

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0', 'max:'.$exam->total_marks],
            'action' => ['required', 'in:save,submit'],
        ]);

        $status = $data['action'] === 'submit' ? 'submitted' : 'draft';
        $now = now();

        $rows = collect($data['scores'])
            ->filter(fn ($score) => $score !== null && $score !== '')
            ->map(fn ($score, $studentId) => [
                'id' => (string) Str::uuid(),
                'tenant_id' => $exam->tenant_id,
                'exam_id' => $exam->id,
                'student_id' => $studentId,
                'score' => $score,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all();

        DB::transaction(function () use ($rows) {
            Grade::upsert(
                $rows,
                ['exam_id', 'student_id'],
                ['score', 'status', 'updated_at']
            );
        });

        return back()->with('success', $status === 'submitted'
            ? 'تم إرسال الدرجات للإدارة لاعتمادها'
            : 'تم حفظ الدرجات');
    }
}
