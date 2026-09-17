<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Http\Resources\Api\V1\ExamAttemptResource;
use App\Http\Resources\Api\V1\ExamQuestionResource;
use App\Http\Resources\Api\V1\ExamResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Services\ExamService;
use App\Support\ExamQuestionNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * نقاط نهاية محرّك الاختبارات في الـ API (إدارة/أستاذ): النشر، بناء الأسئلة،
 * النتائج، والتصحيح اليدوي. أداء الامتحان نفسه متاح للطلاب عبر بوابة الويب.
 */
trait ManagesExamApi
{
    abstract protected function assertApiExamAccess(Request $request, Exam $exam): void;

    public function publish(Request $request, string $examId, ExamService $service): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->assertApiExamAccess($request, $exam);

        $service->publish($exam);

        return $this->success(
            ExamResource::make($exam->fresh()->load(['subject', 'classroom', 'section'])),
            'تم نشر الامتحان'
        );
    }

    public function close(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->assertApiExamAccess($request, $exam);

        $exam->update(['status' => ExamStatus::Closed]);

        return $this->success(ExamResource::make($exam->fresh()), 'تم إغلاق الامتحان');
    }

    public function storeQuestions(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->assertApiExamAccess($request, $exam);

        if (! $exam->canEdit()) {
            throw ValidationException::withMessages([
                'exam' => 'لا يمكن تعديل الامتحان أو أسئلته بعد بدء محاولات الطلاب',
            ]);
        }

        $data = $request->validate([
            'type' => ['required', Rule::enum(QuestionType::class)],
            'questions' => ['required', 'array', 'min:1', 'max:100'],
            'questions.*.text' => ['required', 'string', 'max:2000'],
            'questions.*.marks' => ['required', 'numeric', 'min:0', 'max:1000'],
            'questions.*.options' => ['nullable', 'array', 'max:10'],
            'questions.*.options.*' => ['nullable', 'string', 'max:500'],
            'questions.*.correct_answer' => ['nullable'],
            'questions.*.correct_options' => ['nullable', 'array', 'max:10'],
            'questions.*.correct_options.*' => ['nullable'],
        ]);

        $type = QuestionType::from($data['type']);
        $sortStart = (int) $exam->questions()->max('sort_order');
        $created = collect();

        foreach (array_values($data['questions']) as $index => $row) {
            [$options, $correctAnswer] = ExamQuestionNormalizer::normalize($type, $row, 'questions.'.$index.'.');

            $created->push(ExamQuestion::create([
                'tenant_id' => $exam->tenant_id,
                'exam_id' => $exam->id,
                'type' => $type->value,
                'text' => $row['text'],
                'marks' => $row['marks'],
                'options' => $options,
                'correct_answer' => $correctAnswer,
                'sort_order' => $sortStart + $index + 1,
            ]));
        }

        return $this->created(
            ['questions' => ExamQuestionResource::collection($created)],
            'تمت إضافة الأسئلة'
        );
    }

    public function updateQuestion(Request $request, string $examId, string $questionId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->assertApiExamAccess($request, $exam);

        $question = ExamQuestion::where('exam_id', $exam->id)->findOrFail($questionId);

        if (! $exam->canEdit()) {
            throw ValidationException::withMessages([
                'exam' => 'لا يمكن تعديل الامتحان أو أسئلته بعد بدء محاولات الطلاب',
            ]);
        }

        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'marks' => ['required', 'numeric', 'min:0', 'max:1000'],
            'options' => ['nullable', 'array', 'max:10'],
            'options.*' => ['nullable', 'string', 'max:500'],
            'correct_answer' => ['nullable'],
            'correct_options' => ['nullable', 'array', 'max:10'],
            'correct_options.*' => ['nullable'],
        ]);

        [$options, $correctAnswer] = ExamQuestionNormalizer::normalize($question->type, $data, '');

        $question->update([
            'text' => $data['text'],
            'marks' => $data['marks'],
            'options' => $options,
            'correct_answer' => $correctAnswer,
        ]);

        return $this->success(ExamQuestionResource::make($question->fresh()), 'تم تحديث السؤال');
    }

    public function destroyQuestion(Request $request, string $examId, string $questionId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->assertApiExamAccess($request, $exam);

        if (! $exam->canEdit()) {
            throw ValidationException::withMessages([
                'exam' => 'لا يمكن تعديل الامتحان أو أسئلته بعد بدء محاولات الطلاب',
            ]);
        }

        ExamQuestion::where('exam_id', $exam->id)->findOrFail($questionId)->delete();

        return $this->success(message: 'تم حذف السؤال');
    }

    public function results(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->assertApiExamAccess($request, $exam);

        $attempts = $exam->attempts()
            ->with(['student:id,name', 'answers', 'exam'])
            ->get();

        return $this->success([
            'exam' => ExamResource::make($exam->load(['subject', 'classroom', 'section'])),
            'questions' => ExamQuestionResource::collection($exam->questions()->get()),
            'attempts' => ExamAttemptResource::collection($attempts),
        ]);
    }

    public function manualGrade(Request $request, string $examId, string $attemptId, ExamService $service): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->assertApiExamAccess($request, $exam);

        $attempt = ExamAttempt::where('exam_id', $exam->id)->findOrFail($attemptId);

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$exam->total_marks],
        ]);

        $attempt = $service->manualGrade($attempt, (float) $data['score'], $request->user());

        return $this->success(
            ExamAttemptResource::make($attempt->load(['student:id,name', 'exam'])),
            'تم حفظ التصحيح'
        );
    }
}
