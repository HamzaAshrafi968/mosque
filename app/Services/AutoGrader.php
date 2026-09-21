<?php

namespace App\Services;

use App\Enums\QuestionType;

/**
 * التصحيح الآلي لأنواع الأسئلة (مشترك بين أسئلة الاختبارات وأسئلة الواجبات):
 * لا توجد علامات سالبة، والجواب الفارغ = صفر، والمقالي/بلا إجابة متوقعة يحتاج تصحيحاً يدوياً.
 */
class AutoGrader
{
    /**
     * @param  array<int, string>  $selectedOptions
     * @return array{is_correct: ?bool, marks: ?float} marks = null يعني يحتاج تصحيحاً يدوياً
     */
    public function grade(
        QuestionType $type,
        float $marks,
        ?string $correctAnswer,
        array $selectedOptions,
        ?string $answerText,
    ): array {
        return match ($type) {
            QuestionType::Mcq => $this->gradeSingleChoice($marks, $correctAnswer, $selectedOptions, $answerText),
            QuestionType::Checkbox => $this->gradeCheckbox($marks, $correctAnswer, $selectedOptions),
            QuestionType::TrueFalse => $this->gradeTrueFalse($marks, $correctAnswer, $selectedOptions, $answerText),
            QuestionType::Short => $this->gradeShort($marks, $correctAnswer, $answerText),
            QuestionType::Essay => ['is_correct' => null, 'marks' => null],
        };
    }

    /** الإجابات الصحيحة المتعددة (checkbox) من نص الإجابة الصحيحة. */
    public function correctOptions(?string $correctAnswer): array
    {
        if ($correctAnswer === null || $correctAnswer === '') {
            return [];
        }

        $decoded = json_decode($correctAnswer, true);

        if (is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }

        return [(string) $correctAnswer];
    }

    /** @return array{is_correct: ?bool, marks: ?float} */
    private function gradeSingleChoice(float $marks, ?string $correctAnswer, array $selectedOptions, ?string $answerText): array
    {
        $correct = trim((string) $correctAnswer);
        $given = trim((string) ($selectedOptions[0] ?? $answerText ?? ''));

        if ($correct === '' || $given === '') {
            return ['is_correct' => false, 'marks' => 0.0];
        }

        $isCorrect = $given === $correct;

        return ['is_correct' => $isCorrect, 'marks' => $isCorrect ? $marks : 0.0];
    }

    /** @return array{is_correct: ?bool, marks: ?float} */
    private function gradeCheckbox(float $marks, ?string $correctAnswer, array $selectedOptions): array
    {
        $correct = $this->correctOptions($correctAnswer);
        $given = array_values(array_unique($selectedOptions));

        if ($correct === [] || $given === []) {
            return ['is_correct' => false, 'marks' => 0.0];
        }

        $selectedCorrect = count(array_intersect($given, $correct));
        $wrong = array_diff($given, $correct);

        if ($selectedCorrect === count($correct) && $wrong === []) {
            return ['is_correct' => true, 'marks' => $marks];
        }

        $partial = ($selectedCorrect / count($correct)) * $marks;

        return ['is_correct' => false, 'marks' => round($partial, 2)];
    }

    /** @return array{is_correct: ?bool, marks: ?float} */
    private function gradeTrueFalse(float $marks, ?string $correctAnswer, array $selectedOptions, ?string $answerText): array
    {
        $normalize = fn (?string $value) => match (strtolower(trim((string) $value))) {
            'true', '1', 'صح', 'صحيح' => 'true',
            'false', '0', 'خطأ', 'خطا' => 'false',
            default => '',
        };

        $correct = $normalize($correctAnswer);
        $given = $normalize($selectedOptions[0] ?? $answerText);

        if ($correct === '' || $given === '') {
            return ['is_correct' => false, 'marks' => 0.0];
        }

        $isCorrect = $given === $correct;

        return ['is_correct' => $isCorrect, 'marks' => $isCorrect ? $marks : 0.0];
    }

    /** @return array{is_correct: ?bool, marks: ?float} */
    private function gradeShort(float $marks, ?string $correctAnswer, ?string $answerText): array
    {
        $correct = trim((string) $correctAnswer);

        if ($correct === '') {
            return ['is_correct' => null, 'marks' => null];
        }

        $given = trim((string) $answerText);

        if ($given === '') {
            return ['is_correct' => false, 'marks' => 0.0];
        }

        $isCorrect = mb_strtolower($given) === mb_strtolower($correct);

        return ['is_correct' => $isCorrect, 'marks' => $isCorrect ? $marks : 0.0];
    }
}
