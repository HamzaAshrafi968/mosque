<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function index(): View
    {
        $exams = Exam::query()
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount('grades')
            ->latest('exam_date')
            ->paginate(20);

        return view('admin.exams.index', ['exams' => $exams]);
    }

    public function create(): View
    {
        return view('admin.exams.create', [
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'exam_date' => ['required', 'date'],
            'total_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_marks' => ['nullable', 'integer', 'min:0', 'lte:total_marks'],
        ]);

        Exam::create($data);

        return redirect()->route('admin.exams.index')->with('success', 'تم إنشاء الاختبار');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $exam->delete();

        return redirect()->route('admin.exams.index')->with('success', 'تم حذف الاختبار');
    }
}
