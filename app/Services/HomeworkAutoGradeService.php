<?php

namespace App\Services;

use App\Models\Homework;
use App\Models\HomeworkQuestion;

/**
 * تصحيح إجابات الواجب آلياً اعتماداً على أسئلته وإجاباتها الصحيحة.
 * يُستخدم عند التسليم الإلكتروني (بوابة الطالب) ويمكن استدعاؤه من أي مسار تصحيح.
 */
class HomeworkAutoGradeService
{
    public function __construct(private readonly AutoGrader $grader) {}

    /**
     * @param  array<string, array{selected_options?: array<int, string>, answer_text?: ?string}>  $answers  مفاتيحها معرّفات الأسئلة
     * @return array{score: ?float, needs_manual_grading: bool, details: array<string, array{is_correct: ?bool, marks: ?float}>}
     */
    public function gradeAnswers(Homework $homework, array $answers): array
    {
        $questions = $homework->questions()->get();
        $total = 0.0;
        $needsManualGrading = $questions->isEmpty();
        $details = [];

        foreach ($questions as $question) {
            $submitted = $answers[$question->id] ?? [];
            $selected = array_values(array_map('strval', (array) ($submitted['selected_options'] ?? [])));
            $text = isset($submitted['answer_text']) ? (string) $submitted['answer_text'] : null;

            $result = $this->gradeQuestion($question, $selected, $text);
            $details[$question->id] = $result;

            if ($result['marks'] === null) {
                $needsManualGrading = true;
            } else {
                $total += (float) $result['marks'];
            }
        }

        return [
            'score' => $needsManualGrading ? null : round($total, 2),
            'needs_manual_grading' => $needsManualGrading,
            'details' => $details,
        ];
    }

    /**
     * @param  array<int, string>  $selectedOptions
     * @return array{is_correct: ?bool, marks: ?float}
     */
    public function gradeQuestion(HomeworkQuestion $question, array $selectedOptions, ?string $answerText): array
    {
        return $this->grader->grade(
            $question->type,
            (float) $question->marks,
            $question->correct_answer,
            $selectedOptions,
            $answerText,
        );
    }
}
