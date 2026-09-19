<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionType;
use App\Http\Controllers\Concerns\ManagesExamEngine;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Subject;
use App\Services\ExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'questionTypes' => QuestionType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedExamData($request);
        [$type, $rows] = $this->validatedQuestionsData($request);

        $exam = DB::transaction(function () use ($data, $type, $rows) {
            $exam = $this->exams->create($data);

            if ($rows !== []) {
                $this->saveQuestions($exam, $type, $rows);
            }

            return $exam;
        });

        $questionsCount = count($rows);

        $message = $questionsCount > 0
            ? "تم إنشاء الامتحان مع {$questionsCount} سؤالاً — راجع العلامات ثم انشره"
            : 'تم إنشاء الامتحان — أضف الأسئلة ثم انشره';

        return redirect()
            ->route('admin.exams.show', $exam)
            ->with('success', $message);
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $exam->delete();

        return redirect()->route('admin.exams.index')->with('success', 'تم حذف الاختبار');
    }
}
