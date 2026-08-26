<?php

namespace App\Http\Controllers\Teacher;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $exams = Exam::query()
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount('grades')
            ->where('teacher_id', $teacher->id)
            ->latest('exam_date')
            ->paginate(15);

        return view('teacher.exams.index', ['exams' => $exams]);
    }

    public function create(): View
    {
        return view('teacher.exams.create', [
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'exam_date' => ['required', 'date'],
            'total_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_marks' => ['nullable', 'integer', 'min:0', 'lte:total_marks'],
        ]);

        Exam::create([...$data, 'teacher_id' => $teacher->id]);

        return redirect()->route('teacher.exams.index')->with('success', 'تم إنشاء الاختبار');
    }
}
