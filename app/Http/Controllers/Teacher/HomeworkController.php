<?php

namespace App\Http\Controllers\Teacher;

use App\Models\Classroom;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeworkController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $homeworks = Homework::query()
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount([
                'submissions',
                'submissions as pending_submissions_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->where('teacher_id', $teacher->id)
            ->latest('due_date')
            ->paginate(15);

        return view('teacher.homeworks.index', ['homeworks' => $homeworks]);
    }

    public function create(Request $request): View
    {
        return view('teacher.homeworks.create', [
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('homeworks', 'public');
        }

        unset($data['attachment']);

        $homework = Homework::create([...$data, 'teacher_id' => $teacher->id]);

        $studentIds = Student::query()
            ->active()
            ->where('classroom_id', $homework->classroom_id)
            ->when($homework->section_id, fn ($q) => $q->where('section_id', $homework->section_id))
            ->pluck('id');

        $homework->submissions()->createMany(
            $studentIds->map(fn ($id) => [
                'tenant_id' => $homework->tenant_id,
                'student_id' => $id,
            ])->all()
        );

        return redirect()->route('teacher.homeworks.index')->with('success', 'تم إنشاء الواجب');
    }

    public function submissions(Request $request, Homework $homework): View
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);

        $homework->load(['subject:id,name', 'classroom:id,name']);

        $submissions = $homework->submissions()
            ->with('student:id,name')
            ->get()
            ->sortBy(fn ($s) => $s->student->name ?? '');

        return view('teacher.homeworks.submissions', [
            'homework' => $homework,
            'submissions' => $submissions,
        ]);
    }

    public function updateSubmission(Request $request, HomeworkSubmission $submission): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($submission->homework()->value('teacher_id') === $teacher->id, 403);

        $data = $request->validate([
            'grade' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'feedback' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,graded'],
        ]);

        $submission->update([...$data, 'submitted_at' => $submission->submitted_at ?? now()]);

        return back()->with('success', 'تم حفظ التصحيح');
    }

    public function destroy(Request $request, Homework $homework): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);

        $homework->delete();

        return redirect()->route('teacher.homeworks.index')->with('success', 'تم حذف الواجب');
    }
}
