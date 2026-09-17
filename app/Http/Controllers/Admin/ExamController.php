<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ManagesExamEngine;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Subject;
use App\Services\ExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    use ManagesExamEngine;

    public function __construct(private readonly ExamService $exams) {}

    protected function examRoutePrefix(): string
    {
        return 'admin';
    }

    protected function assertExamAccess(Request $request, Exam $exam): void
    {
        // مدير الجامع يصل لكل امتحانات جامعته (العزل العام مطبَّق مسبقاً).
    }

    public function index(): View
    {
        $exams = Exam::query()
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount(['grades', 'questions', 'attempts'])
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
        $exam = $this->exams->create($this->validatedExamData($request));

        return redirect()
            ->route('admin.exams.show', $exam)
            ->with('success', 'تم إنشاء الامتحان — أضف الأسئلة ثم انشره');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $exam->delete();

        return redirect()->route('admin.exams.index')->with('success', 'تم حذف الاختبار');
    }
}
