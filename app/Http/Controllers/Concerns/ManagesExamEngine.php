<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\DeliveryMode;
use App\Enums\ExamKind;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Services\ExamService;
use App\Support\ExamQuestionNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * منطق محرّك الاختبارات المشترك بين لوحة الإدارة وبوابة الأستاذ:
 * صفحة الأسئلة والنتائج، النشر/الإغلاق، بناء الأسئلة الجماعي، ملف PDF،
 * والتصحيح اليدوي. العزل بين الجامعات عبر Global Scope، وعزل الأستاذ
 * عبر assertExamAccess.
 */
trait ManagesExamEngine
{
    /** بادئة أسماء المسارات: admin | teacher. */
    abstract protected function examRoutePrefix(): string;

    /** حماية إضافية للأستاذ (لا شيء للمدير). */
    abstract protected function assertExamAccess(Request $request, Exam $exam): void;

    protected function examRoute(string $name, mixed $parameters = []): string
    {
        return route($this->examRoutePrefix().'.exams.'.$name, $parameters);
    }

    // =====================================================================
    // الصفحة الرئيسية للامتحان (الأسئلة + النتائج)
    // =====================================================================

    public function show(Request $request, Exam $exam): View
    {
        $this->assertExamAccess($request, $exam);

        $exam->load(['subject:id,name', 'classroom:id,name', 'section:id,name', 'teacher:id,name']);

        $attempts = $exam->attempts()
            ->with([
                'student:id,name',
                'answers.question:id,text,type,marks,correct_answer',
            ])
            ->get()
            ->keyBy('student_id');

        return view('exams.show', [
            'exam' => $exam,
            'questions' => $exam->questions()->get(),
            'attempts' => $attempts,
            'roster' => app(ExamService::class)->roster($exam),
            'questionTypes' => QuestionType::cases(),
            'routePrefix' => $this->examRoutePrefix(),
        ]);
    }

    // =====================================================================
    // دورة الحياة
    // =====================================================================

    public function publish(Request $request, Exam $exam, ExamService $service): RedirectResponse
    {
        $this->assertExamAccess($request, $exam);

        $service->publish($exam);

        return back()->with('success', 'تم نشر الامتحان وأصبح ظاهراً للطلاب');
    }

    public function close(Request $request, Exam $exam): RedirectResponse
    {
        $this->assertExamAccess($request, $exam);

        $exam->update(['status' => ExamStatus::Closed]);

        return back()->with('success', 'تم إغلاق الامتحان');
    }

    // =====================================================================
    // بناء الأسئلة
    // =====================================================================

    public function storeQuestions(Request $request, Exam $exam): RedirectResponse
    {
        $this->assertExamAccess($request, $exam);
        $this->assertExamEditable($exam);

        [$defaultType, $rows] = $this->validatedQuestionsData($request);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'questions' => 'أضف سؤالاً واحداً على الأقل بنص السؤال',
            ]);
        }

        $count = $this->saveQuestions($exam, $defaultType, $rows);

        return back()->with('success', 'تم حفظ '.$count.' سؤالاً');
    }

    /**
     * يحفظ صفوف الأسئلة على الامتحان ويعيد عددها.
     * كل صف يحمل نوعه الخاص (questions.*.type)، ويسقط على النوع الافتراضي
     * للتوافق مع الـ API الذي يرسل نوعاً واحداً لكل الطلب.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function saveQuestions(Exam $exam, ?QuestionType $defaultType, array $rows): int
    {
        $sortStart = (int) $exam->questions()->max('sort_order');

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

            ExamQuestion::create([
                'tenant_id' => $exam->tenant_id,
                'exam_id' => $exam->id,
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

    public function updateQuestion(Request $request, ExamQuestion $question): RedirectResponse
    {
        $exam = $question->exam()->firstOrFail();
        $this->assertExamAccess($request, $exam);
        $this->assertExamEditable($exam);

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

        $question->update([
            'text' => $data['text'],
            'marks' => $data['marks'],
            'options' => $options,
            'correct_answer' => $correctAnswer,
        ]);

        return back()->with('success', 'تم تحديث السؤال');
    }

    public function destroyQuestion(Request $request, ExamQuestion $question): RedirectResponse
    {
        $exam = $question->exam()->firstOrFail();
        $this->assertExamAccess($request, $exam);
        $this->assertExamEditable($exam);

        $question->delete();

        return back()->with('success', 'تم حذف السؤال');
    }

    // =====================================================================
    // ملف الامتحان (PDF)
    // =====================================================================

    public function storeAttachment(Request $request, Exam $exam): RedirectResponse
    {
        $this->assertExamAccess($request, $exam);

        $request->validate([
            'attachment' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ]);

        if ($exam->attachment_key) {
            Storage::disk('local')->delete($exam->attachment_key);
        }

        $file = $request->file('attachment');
        $path = $file->storeAs(
            'exam-attachments/'.$exam->tenant_id,
            (string) Str::uuid().'.pdf',
            'local'
        );

        $exam->update([
            'attachment_key' => $path,
            'attachment_name' => $file->getClientOriginalName(),
        ]);

        return back()->with('success', 'تم رفع ملف الامتحان');
    }

    public function destroyAttachment(Request $request, Exam $exam): RedirectResponse
    {
        $this->assertExamAccess($request, $exam);

        if ($exam->attachment_key) {
            Storage::disk('local')->delete($exam->attachment_key);
        }

        $exam->update(['attachment_key' => null, 'attachment_name' => null]);

        return back()->with('success', 'تم حذف ملف الامتحان');
    }

    public function attachment(Request $request, Exam $exam): StreamedResponse
    {
        $this->assertExamAccess($request, $exam);

        abort_unless($exam->attachment_key && Storage::disk('local')->exists($exam->attachment_key), 404);

        return Storage::disk('local')->download(
            $exam->attachment_key,
            $exam->attachment_name ?: 'exam.pdf'
        );
    }

    // =====================================================================
    // النتائج والتصحيح اليدوي
    // =====================================================================

    public function manualGrade(Request $request, ExamAttempt $attempt, ExamService $service): RedirectResponse
    {
        $exam = $attempt->exam()->firstOrFail();
        $this->assertExamAccess($request, $exam);

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$exam->total_marks],
        ]);

        $service->manualGrade($attempt, (float) $data['score'], $request->user());

        return back()->with('success', 'تم حفظ التصحيح');
    }

    // =====================================================================
    // أدوات مساعدة
    // =====================================================================

    /** @param array<string, mixed> $row */
    protected function validatedExamData(Request $request): array
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()?->tenant_id;

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', Rule::enum(ExamKind::class)],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->where('tenant_id', $tenantId)],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->where('tenant_id', $tenantId)],
            'exam_date' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'mode' => ['nullable', Rule::enum(DeliveryMode::class)],
            'total_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_marks' => ['nullable', 'integer', 'min:0', 'lte:total_marks'],
        ]);
    }

    /**
     * يتحقق من أسئلة النموذج (اختيارية عند إنشاء الامتحان) ويعيد [النوع الافتراضي، الصفوف].
     * كل صف قد يحمل نوعه الخاص (questions.*.type) فتكون الأسئلة متعددة الأنواع في دفعة واحدة،
     * ويسقط الصف بلا نوع على النوع العام type (توافق الـ API). العلامة الفارغة = 1.
     * الصفوف بلا نص سؤال تُتجاهل، فإن لم يتبقَّ صف يُعاد [null, []].
     *
     * @return array{0: ?QuestionType, 1: array<int, array<string, mixed>>}
     *
     * @throws ValidationException
     */
    protected function validatedQuestionsData(Request $request): array
    {
        $rows = collect($request->input('questions', []))
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['text'] ?? '')) !== '')
            ->values()
            ->all();

        if ($rows === []) {
            return [null, []];
        }

        $data = Validator::make(
            ['type' => $request->input('type'), 'questions' => $rows],
            [
                'type' => ['nullable', Rule::enum(QuestionType::class)],
                'questions' => ['required', 'array', 'min:1', 'max:100'],
                'questions.*.text' => ['required', 'string', 'max:2000'],
                'questions.*.type' => ['nullable', Rule::enum(QuestionType::class)],
                'questions.*.marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
                'questions.*.options' => ['nullable', 'array', 'max:10'],
                'questions.*.options.*' => ['nullable', 'string', 'max:500'],
                'questions.*.correct_answer' => ['nullable'],
                'questions.*.correct_options' => ['nullable', 'array', 'max:10'],
                'questions.*.correct_options.*' => ['nullable'],
            ]
        )->validate();

        $fallbackType = $data['type'] !== null ? QuestionType::from($data['type']) : null;

        $rows = array_map(function (array $row) use ($fallbackType): array {
            $type = $row['type'] ?? $fallbackType?->value;

            if ($type === null || $type === '') {
                throw ValidationException::withMessages([
                    'questions' => 'حدد نوع كل سؤال',
                ]);
            }

            $row['type'] = $type;
            $row['marks'] = ($row['marks'] ?? null) === null || ($row['marks'] ?? null) === ''
                ? 1
                : (float) $row['marks'];

            return $row;
        }, array_values($data['questions']));

        return [$fallbackType, $rows];
    }

    /** @throws ValidationException */
    protected function assertExamEditable(Exam $exam): void
    {
        if (! $exam->canEdit()) {
            throw ValidationException::withMessages([
                'exam' => 'لا يمكن تعديل الامتحان أو أسئلته بعد بدء محاولات الطلاب',
            ]);
        }
    }

    /**
     * يطبّع صف السؤال حسب نوعه ويعيد [options, correct_answer].
     *
     * @param  array<string, mixed>  $row
     * @return array{0: ?array<int, string>, 1: ?string}
     *
     * @throws ValidationException
     */
    protected function normalizeQuestion(QuestionType $type, array $row, int $index): array
    {
        return ExamQuestionNormalizer::normalize($type, $row, 'questions.'.$index.'.');
    }
}
