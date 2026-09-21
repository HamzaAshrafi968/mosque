<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\QuestionType;
use App\Http\Controllers\Concerns\ParsesQuestionRows;
use App\Models\Classroom;
use App\Models\Homework;
use App\Models\HomeworkQuestion;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use App\Models\Subject;
use App\Services\NotificationService;
use App\Support\DocumentUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HomeworkController extends BaseTeacherController
{
    use ParsesQuestionRows;

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $homeworks = Homework::query()
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount([
                'submissions',
                'submissions as pending_submissions_count' => fn ($q) => $q->where('status', 'pending'),
                'submissions as graded_submissions_count' => fn ($q) => $q->where(fn ($query) => $query->where('status', 'graded')->orWhereNotNull('grade')),
                'questions',
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
            'questionTypes' => QuestionType::cases(),
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
            'pass_marks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'total_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'attachment' => ['nullable', ...DocumentUpload::rules(10240)],
        ]);

        [$defaultType, $rows] = $this->validatedQuestionsData($request);

        if ($rows !== []) {
            $this->assertMarksMatchQuestions($data, $rows);
        }

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('homeworks', 'public');
        }

        unset($data['attachment']);

        $homework = DB::transaction(function () use ($data, $teacher, $defaultType, $rows) {
            $homework = Homework::create([...$data, 'teacher_id' => $teacher->id]);

            if ($rows !== []) {
                $this->saveQuestions($homework, $defaultType, $rows);
            }

            return $homework;
        });

        $roster = Student::query()
            ->active()
            ->where('classroom_id', $homework->classroom_id)
            ->when($homework->section_id, fn ($q) => $q->where('section_id', $homework->section_id))
            ->get(['id', 'name', 'tenant_id', 'user_id']);

        $homework->submissions()->createMany(
            $roster->map(fn ($student) => [
                'tenant_id' => $homework->tenant_id,
                'student_id' => $student->id,
            ])->all()
        );

        app(NotificationService::class)->notifyRoster(
            $roster,
            'واجب جديد',
            "تم نشر واجب «{$homework->title}» — مادة ".($homework->subject?->name ?? 'عام').'، استحقاق '.$homework->due_date->format('Y-m-d'),
            route('student.homeworks', [], false)
        );

        return redirect()->route('teacher.homeworks.index')->with('success', 'تم إنشاء الواجب');
    }

    public function edit(Request $request, Homework $homework): View|RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);

        if (! $homework->canEdit()) {
            return redirect()
                ->route('teacher.homeworks.submissions', $homework)
                ->with('error', 'لا يمكن تعديل الواجب بعد تصحيح أي تسليم');
        }

        return view('teacher.homeworks.edit', [
            'homework' => $homework->load('questions'),
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
            'questionTypes' => QuestionType::cases(),
        ]);
    }

    public function update(Request $request, Homework $homework): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);

        if (! $homework->canEdit()) {
            return redirect()
                ->route('teacher.homeworks.submissions', $homework)
                ->with('error', 'لا يمكن تعديل الواجب بعد تصحيح أي تسليم');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'due_date' => ['required', 'date'],
            'pass_marks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'total_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'attachment' => ['nullable', ...DocumentUpload::rules(10240)],
        ]);

        [$defaultType, $rows] = $this->validatedQuestionsData($request);

        // عند وجود أسئلة يجب أن تطابق الدرجة الكلية مجموعها (تُعاد المزامنة بعد الحفظ).
        if ($rows === [] && $homework->hasQuestions()) {
            $existingTotal = $homework->questionsTotalMarks();
            $declared = (float) ($data['total_marks'] ?? 0);

            if (abs($existingTotal - $declared) > 0.01) {
                throw ValidationException::withMessages([
                    'total_marks' => 'مجموع علامات الأسئلة ('.$existingTotal.') لا يطابق الدرجة الكلية ('.$declared.')',
                ]);
            }
        }

        if ($request->hasFile('attachment')) {
            if ($homework->attachment_path) {
                Storage::disk('public')->delete($homework->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('homeworks', 'public');
        }

        unset($data['attachment']);

        DB::transaction(function () use ($homework, $data, $defaultType, $rows) {
            $homework->update($data);

            if ($rows !== []) {
                $this->saveQuestions($homework, $defaultType, $rows);
                $this->syncTotalMarks($homework->fresh());
            }
        });

        return redirect()
            ->route('teacher.homeworks.edit', $homework)
            ->with('success', 'تم حفظ الواجب');
    }

    public function storeQuestions(Request $request, Homework $homework): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);
        $this->assertHomeworkEditable($homework);

        [$defaultType, $rows] = $this->validatedQuestionsData($request);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'questions' => 'أضف سؤالاً واحداً على الأقل بنص السؤال',
            ]);
        }

        DB::transaction(function () use ($homework, $defaultType, $rows) {
            $this->saveQuestions($homework, $defaultType, $rows);
            $this->syncTotalMarks($homework->fresh());
        });

        return back()->with('success', 'تم حفظ '.count($rows).' سؤالاً');
    }

    public function updateQuestion(Request $request, HomeworkQuestion $question): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $homework = $question->homework()->firstOrFail();
        abort_unless($homework->teacher_id === $teacher->id, 403);
        $this->assertHomeworkEditable($homework);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'marks' => ['required', 'numeric', 'min:0', 'max:1000'],
            'options' => ['nullable', 'array', 'max:10'],
            'options.*' => ['nullable', 'string', 'max:500'],
            'correct_answer' => ['nullable'],
            'correct_options' => ['nullable', 'array', 'max:10'],
            'correct_options.*' => ['nullable'],
        ]);

        [$options, $correctAnswer] = $this->normalizeQuestion($question->type, $data, 0);

        DB::transaction(function () use ($question, $data, $options, $correctAnswer) {
            $question->update([
                'text' => $data['text'],
                'marks' => $data['marks'],
                'options' => $options,
                'correct_answer' => $correctAnswer,
            ]);

            $this->syncTotalMarks($question->homework()->firstOrFail());
        });

        return back()->with('success', 'تم تحديث السؤال');
    }

    public function destroyQuestion(Request $request, HomeworkQuestion $question): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $homework = $question->homework()->firstOrFail();
        abort_unless($homework->teacher_id === $teacher->id, 403);
        $this->assertHomeworkEditable($homework);

        DB::transaction(function () use ($question, $homework) {
            $question->delete();
            $this->syncTotalMarks($homework);
        });

        return back()->with('success', 'تم حذف السؤال');
    }

    public function submissions(Request $request, Homework $homework): View
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);

        $homework->load(['subject:id,name', 'classroom:id,name', 'questions']);

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

    /**
     * يحفظ صفوف أسئلة الواجب ويعيد عددها.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function saveQuestions(Homework $homework, ?QuestionType $defaultType, array $rows): int
    {
        $sortStart = (int) $homework->questions()->max('sort_order');

        foreach (array_values($rows) as $index => $row) {
            $type = isset($row['type']) && $row['type'] !== ''
                ? QuestionType::from($row['type'])
                : $defaultType;

            if ($type === null) {
                throw ValidationException::withMessages([
                    'questions' => 'حدد نوع كل سؤال',
                ]);
            }

            [$options, $correctAnswer] = $this->normalizeQuestion($type, $row, $index);

            HomeworkQuestion::create([
                'tenant_id' => $homework->tenant_id,
                'homework_id' => $homework->id,
                'type' => $type->value,
                'text' => $row['text'],
                'marks' => $row['marks'],
                'options' => $options,
                'correct_answer' => $correctAnswer,
                'sort_order' => $sortStart + $index + 1,
            ]);
        }

        return count($rows);
    }

    /** @param array<string, mixed> $data @param array<int, array<string, mixed>> $rows */
    private function assertMarksMatchQuestions(array $data, array $rows): void
    {
        $total = round(array_sum(array_column($rows, 'marks')), 2);
        $declared = (float) ($data['total_marks'] ?? 0);

        if (abs($total - $declared) > 0.01) {
            throw ValidationException::withMessages([
                'total_marks' => 'مجموع علامات الأسئلة ('.$total.') لا يطابق الدرجة الكلية ('.$declared.')',
            ]);
        }

        if (($data['pass_marks'] ?? null) !== null && (int) $data['pass_marks'] > (int) $declared) {
            throw ValidationException::withMessages([
                'pass_marks' => 'علامة النجاح أكبر من الدرجة الكلية',
            ]);
        }
    }

    /** يعيد ضبط الدرجة الكلية بعد أي تغيير على الأسئلة. */
    private function syncTotalMarks(Homework $homework): void
    {
        $total = $homework->questionsTotalMarks();
        $data = ['total_marks' => $total > 0 ? $total : null];

        if ($total > 0 && $homework->pass_marks !== null && (int) $homework->pass_marks > (int) $total) {
            $data['pass_marks'] = (int) $total;
        }

        $homework->update($data);
    }

    /** @throws ValidationException */
    private function assertHomeworkEditable(Homework $homework): void
    {
        if (! $homework->canEdit()) {
            throw ValidationException::withMessages([
                'homework' => 'لا يمكن تعديل الواجب أو أسئلته بعد تصحيح أي تسليم',
            ]);
        }
    }
}
