<?php

namespace App\Services;

use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * محرّك الاختبارات الإلكترونية: النشر، بدء المحاولة (محاولة واحدة لكل طالب)،
 * المؤقت من جهة السيرفر، التسليم، والتصحيح الآلي/اليدوي.
 *
 * - لا توجد علامات سالبة، والجواب الفارغ = صفر.
 * - عند التسليم يُنشأ/يُحدَّث صف Grade بحالة submitted حتى تعمل دورة
 *   الاعتماد الحالية (مسودة ← مُرسلة ← معتمدة) وواجهات الطالب كما هي.
 */
class ExamService
{
    /** سماحية التسليم بعد انتهاء المدة (ثوانٍ). */
    public const SUBMIT_GRACE_SECONDS = 60;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    // =====================================================================
    // الإنشاء والنشر
    // =====================================================================

    /** @param array<string, mixed> $data */
    public function create(array $data): Exam
    {
        // الامتحان يولد مسودة دائماً مهما أُرسل في المدخلات.
        $data['status'] = ExamStatus::Draft;

        return Exam::create($data);
    }

    /** @throws ValidationException */
    public function publish(Exam $exam): Exam
    {
        $errors = [];

        if (! $exam->hasQuestions() && ! $exam->hasAttachment()) {
            $errors['publish'][] = 'أضف سؤالاً واحداً على الأقل أو ارفع ملف الامتحان (PDF) قبل النشر';
        }

        if ($exam->hasQuestions()) {
            $total = $exam->questionsTotalMarks();

            if (abs($total - (float) $exam->total_marks) > 0.01) {
                $errors['publish'][] = 'مجموع علامات الأسئلة ('.$total.') لا يطابق العلامة الكلية ('.$exam->total_marks.')';
            }
        }

        if ($exam->pass_marks !== null && (int) $exam->pass_marks > (int) $exam->total_marks) {
            $errors['publish'][] = 'علامة النجاح أكبر من العلامة الكلية';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $exam->update([
            'status' => ExamStatus::Published,
            'published_at' => now(),
        ]);

        $this->audit->logModel('exam.published', $exam);
        $this->notifyStudents($exam);

        return $exam;
    }

    // =====================================================================
    // المحاولة
    // =====================================================================

    /**
     * بدء/استئناف محاولة الطالب (محاولة واحدة فقط لكل طالب).
     *
     * @throws ValidationException
     */
    public function start(Exam $exam, Student $student): ExamAttempt
    {
        if ($exam->status !== ExamStatus::Published) {
            throw ValidationException::withMessages(['exam' => 'الامتحان غير منشور بعد']);
        }

        if ($exam->exam_date !== null && $exam->exam_date->isFuture()) {
            throw ValidationException::withMessages(['exam' => 'لم يحن موعد الامتحان بعد']);
        }

        if (! $exam->hasQuestions()) {
            throw ValidationException::withMessages(['exam' => 'هذا الامتحان لا يحتوي أسئلة إلكترونية']);
        }

        $attempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        if ($attempt && $attempt->isFinished()) {
            throw ValidationException::withMessages(['exam' => 'أديت هذا الامتحان مسبقاً']);
        }

        if ($attempt) {
            if ($this->deadlinePassed($exam, $attempt)) {
                throw ValidationException::withMessages(['exam' => 'انتهى وقت هذه المحاولة']);
            }

            return $attempt;
        }

        return ExamAttempt::create([
            'tenant_id' => $exam->tenant_id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => now(),
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * تسليم المحاولة وتصحيحها آلياً.
     *
     * @param  array<string, array{selected_options?: array<int, string>, answer_text?: ?string}>  $answers  مفاتيحها معرّفات الأسئلة
     *
     * @throws ValidationException
     */
    public function submit(ExamAttempt $attempt, array $answers = []): ExamAttempt
    {
        if ($attempt->isFinished()) {
            return $attempt;
        }

        $exam = $attempt->exam()->with('questions')->firstOrFail();

        if ($this->deadlinePassed($exam, $attempt, grace: true)) {
            throw ValidationException::withMessages(['exam' => 'انتهى وقت الامتحان']);
        }

        return DB::transaction(function () use ($exam, $attempt, $answers) {
            $total = 0.0;
            $needsManualGrading = $exam->questions->isEmpty();

            foreach ($exam->questions as $question) {
                $submitted = $answers[$question->id] ?? [];
                $selected = array_values(array_map('strval', (array) ($submitted['selected_options'] ?? [])));
                $text = isset($submitted['answer_text']) ? (string) $submitted['answer_text'] : null;

                $result = $this->gradeQuestion($question, $selected, $text);

                ExamAnswer::updateOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $question->id],
                    [
                        'tenant_id' => $attempt->tenant_id,
                        'selected_options' => $selected === [] ? null : $selected,
                        'answer_text' => $text,
                        'is_correct' => $result['is_correct'],
                        'marks_awarded' => $result['marks'],
                    ]
                );

                if ($result['marks'] === null) {
                    $needsManualGrading = true;
                } else {
                    $total += (float) $result['marks'];
                }
            }

            $attempt->update([
                'submitted_at' => $attempt->submitted_at ?? now(),
                'score' => $needsManualGrading ? null : round($total, 2),
                'status' => $needsManualGrading ? ExamAttempt::STATUS_SUBMITTED : ExamAttempt::STATUS_GRADED,
            ]);

            $attempt = $attempt->fresh();

            if ($attempt->status === ExamAttempt::STATUS_GRADED) {
                $this->syncGrade($exam, $attempt);
            }

            $this->audit->logModel('exam.attempt.submitted', $attempt, after: [
                'exam_id' => $exam->id,
                'student_id' => $attempt->student_id,
                'status' => $attempt->status,
                'score' => $attempt->score,
            ]);

            return $attempt;
        });
    }

    /** تصحيح يدوي (للمقالي/الامتحانات الورقية) بعلامة نهائية. */
    public function manualGrade(ExamAttempt $attempt, float $score, ?User $actor = null): ExamAttempt
    {
        $attempt->loadMissing('exam');
        $exam = $attempt->exam;

        if ($score < 0 || $score > (float) $exam->total_marks) {
            throw ValidationException::withMessages([
                'score' => 'العلامة يجب أن تكون بين 0 و'.$exam->total_marks,
            ]);
        }

        $attempt->update([
            'score' => round($score, 2),
            'status' => ExamAttempt::STATUS_GRADED,
            'submitted_at' => $attempt->submitted_at ?? now(),
        ]);

        $this->syncGrade($exam, $attempt->fresh());

        $this->audit->logModel('exam.attempt.manual_graded', $attempt, actor: $actor);

        return $attempt->fresh();
    }

    // =====================================================================
    // التصحيح الآلي
    // =====================================================================

    /**
     * @param  array<int, string>  $selectedOptions
     * @return array{is_correct: ?bool, marks: ?float} marks = null يعني يحتاج تصحيحاً يدوياً
     */
    public function gradeQuestion(ExamQuestion $question, array $selectedOptions, ?string $answerText): array
    {
        return match ($question->type) {
            QuestionType::Mcq => $this->gradeSingleChoice($question, $selectedOptions, $answerText),
            QuestionType::Checkbox => $this->gradeCheckbox($question, $selectedOptions),
            QuestionType::TrueFalse => $this->gradeTrueFalse($question, $selectedOptions, $answerText),
            QuestionType::Short => $this->gradeShort($question, $answerText),
            QuestionType::Essay => ['is_correct' => null, 'marks' => null],
        };
    }

    /** @return array{is_correct: ?bool, marks: ?float} */
    private function gradeSingleChoice(ExamQuestion $question, array $selectedOptions, ?string $answerText): array
    {
        $correct = trim((string) $question->correct_answer);
        $given = trim((string) ($selectedOptions[0] ?? $answerText ?? ''));

        if ($correct === '' || $given === '') {
            return ['is_correct' => false, 'marks' => 0.0];
        }

        $isCorrect = $given === $correct;

        return ['is_correct' => $isCorrect, 'marks' => $isCorrect ? (float) $question->marks : 0.0];
    }

    /** @return array{is_correct: ?bool, marks: ?float} */
    private function gradeCheckbox(ExamQuestion $question, array $selectedOptions): array
    {
        $correct = $question->correctOptions();
        $given = array_values(array_unique($selectedOptions));

        if ($correct === [] || $given === []) {
            return ['is_correct' => false, 'marks' => 0.0];
        }

        $selectedCorrect = count(array_intersect($given, $correct));
        $wrong = array_diff($given, $correct);

        if ($selectedCorrect === count($correct) && $wrong === []) {
            return ['is_correct' => true, 'marks' => (float) $question->marks];
        }

        $partial = ($selectedCorrect / count($correct)) * (float) $question->marks;

        return ['is_correct' => false, 'marks' => round($partial, 2)];
    }

    /** @return array{is_correct: ?bool, marks: ?float} */
    private function gradeTrueFalse(ExamQuestion $question, array $selectedOptions, ?string $answerText): array
    {
        $normalize = fn (?string $value) => match (strtolower(trim((string) $value))) {
            'true', '1', 'صح', 'صحيح' => 'true',
            'false', '0', 'خطأ', 'خطا' => 'false',
            default => '',
        };

        $correct = $normalize($question->correct_answer);
        $given = $normalize($selectedOptions[0] ?? $answerText);

        if ($correct === '' || $given === '') {
            return ['is_correct' => false, 'marks' => 0.0];
        }

        $isCorrect = $given === $correct;

        return ['is_correct' => $isCorrect, 'marks' => $isCorrect ? (float) $question->marks : 0.0];
    }

    /** @return array{is_correct: ?bool, marks: ?float} */
    private function gradeShort(ExamQuestion $question, ?string $answerText): array
    {
        $correct = trim((string) $question->correct_answer);

        if ($correct === '') {
            return ['is_correct' => null, 'marks' => null];
        }

        $given = trim((string) $answerText);

        if ($given === '') {
            return ['is_correct' => false, 'marks' => 0.0];
        }

        $isCorrect = mb_strtolower($given) === mb_strtolower($correct);

        return ['is_correct' => $isCorrect, 'marks' => $isCorrect ? (float) $question->marks : 0.0];
    }

    // =====================================================================
    // المزامنة مع الدرجات
    // =====================================================================

    /**
     * مزامنة نتيجة المحاولة مع جدول grades (لا تُلمس الدرجات المعتمدة).
     * النتيجة تدخل بحالة submitted ليعتمدها مدير الجامع كالمعتاد.
     */
    public function syncGrade(Exam $exam, ExamAttempt $attempt): ?Grade
    {
        if ($attempt->status !== ExamAttempt::STATUS_GRADED || $attempt->score === null) {
            return null;
        }

        $grade = Grade::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $attempt->student_id)
            ->first();

        if ($grade && $grade->status === 'approved') {
            return $grade;
        }

        if (! $grade) {
            $grade = new Grade([
                'tenant_id' => $exam->tenant_id,
                'exam_id' => $exam->id,
                'student_id' => $attempt->student_id,
            ]);
        }

        $grade->fill([
            'score' => $attempt->score,
            'status' => 'submitted',
            'notes' => 'تصحيح إلكتروني',
        ])->save();

        return $grade;
    }

    // =====================================================================
    // أدوات مساعدة
    // =====================================================================

    /** هل انتهى وقت المحاولة؟ (grace = السماحية بعد الانتهاء) */
    public function deadlinePassed(Exam $exam, ExamAttempt $attempt, bool $grace = false): bool
    {
        if ($exam->duration_minutes === null || $attempt->started_at === null) {
            return false;
        }

        $deadline = $attempt->started_at->copy()->addMinutes($exam->duration_minutes);

        if ($grace) {
            $deadline = $deadline->addSeconds(self::SUBMIT_GRACE_SECONDS);
        }

        return now()->greaterThan($deadline);
    }

    /** تلاميذ الامتحان (صف/شعبة) مع حسابات البوابة. */
    public function roster(Exam $exam): Collection
    {
        return Student::query()
            ->active()
            ->where('classroom_id', $exam->classroom_id)
            ->when($exam->section_id, fn ($q) => $q->where('section_id', $exam->section_id))
            ->orderBy('name')
            ->get(['id', 'name', 'tenant_id', 'user_id']);
    }

    private function notifyStudents(Exam $exam): void
    {
        $roster = $this->roster($exam);

        if ($roster->isEmpty()) {
            return;
        }

        $date = $exam->exam_date?->toDateString() ?? '—';

        $this->notifications->notifyRoster(
            $roster,
            'امتحان جديد: '.$exam->title,
            'تم نشر '.($exam->kind?->label() ?? 'امتحان').' «'.$exam->title.'» بتاريخ '.$date,
            route('student.exams', [], false)
        );
    }
}
