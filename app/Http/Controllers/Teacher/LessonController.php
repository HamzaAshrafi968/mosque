<?php

namespace App\Http\Controllers\Teacher;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LessonController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $lessons = Lesson::query()
            ->with(['subject:id,name', 'classroom:id,name'])
            ->where('teacher_id', $teacher->id)
            ->latest()
            ->paginate(15);

        return view('teacher.lessons.index', ['lessons' => $lessons]);
    }

    public function create(): View
    {
        return view('teacher.lessons.create', [
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'type' => ['required', 'in:file,video,link,presentation'],
            'file' => ['nullable', 'file', 'max:20480', 'required_if:type,file,presentation'],
            'url' => ['nullable', 'url', 'required_if:type,video,link'],
        ]);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('lessons', 'public');
        }

        unset($data['file']);

        Lesson::create([...$data, 'teacher_id' => $teacher->id]);

        return redirect()->route('teacher.lessons.index')->with('success', 'تمت إضافة الدرس');
    }

    public function destroy(Request $request, Lesson $lesson): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($lesson->teacher_id === $teacher->id, 403);

        $lesson->delete();

        return back()->with('success', 'تم حذف الدرس');
    }
}
