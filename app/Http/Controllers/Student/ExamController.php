<?php

namespace App\Http\Controllers\Student;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Student;
use App\Services\ExamService;
use App\Services\StudentAcademicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * أداء الامتحانات الإلكترونية من بوابة الطالب: بدء المحاولة (محاولة واحدة)،
 * صفحة الامتحان بالمؤقت، التسليم، وورقة النتيجة.
 */
class ExamController extends BaseStudentController
{
    public function __construct(
        StudentAcademicService $academic,
        private readonly ExamService $exams,
    ) {
        parent::__construct($academic);
    }

    /** بدء/استئناف المحاولة ثم الانتقال لصفحة الامتحان. */
    public function start(Request $request, Exam $exam): RedirectResponse
    {
        $student = $this->currentStudent($request);
        $this->assertVisible($student, $exam);

        $this->exams->start($exam, $student);

        return redirect()->route('student.exams.take', $exam);
    }

    /** صفحة الامتحان: الأسئلة + المؤقت (التسليم التلقائي عند الصفر). */
    public function take(Request $request, Exam $exam): View|RedirectResponse
    {
        $student = $this->currentStudent($request);
        $this->assertVisible($student, $exam);

        $attempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        if ($attempt?->isFinished()) {
            return redirect()->route('student.exams.result', $exam);
        }

        $attempt = $this->exams->start($exam, $student);

        $exam->load(['subject:id,name', 'questions']);

        return view('student.exam-take', [
            'student' => $student,
            'exam' => $exam,
            'attempt' => $attempt,
            'questions' => $exam->questions,
            'remainingSeconds' => $exam->remainingSeconds($attempt),
        ]);
    }

    /** تسليم الإجابات (يدوي أو تلقائي عند انتهاء المؤقت). */
    public function submit(Request $request, Exam $exam): RedirectResponse
    {
        $student = $this->currentStudent($request);
        $this->assertVisible($student, $exam);

        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*.selected_options' => ['nullable', 'array'],
            'answers.*.selected_options.*' => ['nullable', 'string', 'max:1000'],
            'answers.*.answer_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $attempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->exams->submit($attempt, $data['answers'] ?? []);

        return redirect()
            ->route('student.exams.result', $exam)
            ->with('success', 'تم تسليم الامتحان');
    }

    /** ورقة النتيجة: الدرجة + مراجعة الإجابات ومفتاح التصحيح. */
    public function result(Request $request, Exam $exam): View
    {
        $student = $this->currentStudent($request);

        $attempt = ExamAttempt::query()
            ->with(['answers.question:id,text,type,marks,correct_answer,options'])
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        abort_if(! $attempt, 404, 'لا توجد محاولة لهذا الامتحان');

        $exam->load(['subject:id,name']);

        return view('student.exam-result', [
            'student' => $student,
            'exam' => $exam,
            'attempt' => $attempt,
            'questions' => $exam->questions()->get(),
        ]);
    }

    private function assertVisible(Student $student, Exam $exam): void
    {
        $visible = Exam::query()
            ->visibleForStudent($student)
            ->whereKey($exam->id)
            ->exists();

        abort_unless($visible, 403, 'هذا الامتحان غير متاح لك');
    }
}
