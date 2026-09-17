<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\ManagesExamEngine;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Subject;
use App\Services\ExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends BaseTeacherController
{
    use ManagesExamEngine;

    public function __construct(private readonly ExamService $exams) {}

    protected function examRoutePrefix(): string
    {
        return 'teacher';
    }

    protected function assertExamAccess(Request $request, Exam $exam): void
    {
        abort_unless($exam->teacher_id === $this->currentTeacher($request)->id, 403);
    }

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $exams = Exam::query()
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount(['grades', 'questions', 'attempts'])
            ->where('teacher_id', $teacher->id)
            ->latest('exam_date')
            ->paginate(15);

        return view('teacher.exams.index', ['exams' => $exams]);
    }

    public function create(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        return view('teacher.exams.create', [
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
            'teacher' => $teacher,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $exam = $this->exams->create([
            ...$this->validatedExamData($request),
            'teacher_id' => $teacher->id,
        ]);

        return redirect()
            ->route('teacher.exams.show', $exam)
            ->with('success', 'تم إنشاء الامتحان — أضف الأسئلة ثم انشره');
    }
}
