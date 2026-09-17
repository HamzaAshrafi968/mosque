<?php

namespace Tests\Unit;

use App\Models\ExamQuestion;
use App\Services\ExamService;
use Tests\TestCase;

/**
 * قواعد التصحيح الآلي لكل نوع سؤال: لا علامات سالبة، والجواب الفارغ = صفر.
 */
class ExamGradingTest extends TestCase
{
    private function service(): ExamService
    {
        return app(ExamService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function question(array $attributes): ExamQuestion
    {
        return new ExamQuestion(array_merge([
            'type' => 'mcq',
            'text' => 'سؤال',
            'marks' => 5,
            'options' => ['أ', 'ب', 'ج'],
            'correct_answer' => 'أ',
        ], $attributes));
    }

    // ------------------------------------------------------- mcq

    public function test_mcq_full_marks_for_the_exact_option_and_zero_otherwise(): void
    {
        $question = $this->question([]);

        $this->assertSame(['is_correct' => true, 'marks' => 5.0], $this->service()->gradeQuestion($question, ['أ'], null));
        $this->assertSame(['is_correct' => false, 'marks' => 0.0], $this->service()->gradeQuestion($question, ['ب'], null));
        $this->assertSame(['is_correct' => false, 'marks' => 0.0], $this->service()->gradeQuestion($question, [], null));
    }

    // ------------------------------------------------------- checkbox

    public function test_checkbox_full_marks_only_when_all_correct_and_no_wrong(): void
    {
        $question = $this->question([
            'type' => 'checkbox',
            'options' => ['أ', 'ب', 'ج'],
            'correct_answer' => json_encode(['أ', 'ب']),
        ]);

        $this->assertSame(['is_correct' => true, 'marks' => 5.0], $this->service()->gradeQuestion($question, ['أ', 'ب'], null));
        // Partial: half of the correct answers → half marks.
        $this->assertSame(['is_correct' => false, 'marks' => 2.5], $this->service()->gradeQuestion($question, ['أ'], null));
        // Wrong option cancels the full mark even with all correct ones.
        $this->assertSame(['is_correct' => false, 'marks' => 5.0], $this->service()->gradeQuestion($question, ['أ', 'ب', 'ج'], null));
        $this->assertSame(['is_correct' => false, 'marks' => 0.0], $this->service()->gradeQuestion($question, [], null));
    }

    // ------------------------------------------------------- true/false

    public function test_true_false_accepts_arabic_and_boolean_forms(): void
    {
        $question = $this->question(['type' => 'true_false', 'options' => null, 'correct_answer' => 'true']);

        $this->assertSame(['is_correct' => true, 'marks' => 5.0], $this->service()->gradeQuestion($question, [], 'true'));
        $this->assertSame(['is_correct' => true, 'marks' => 5.0], $this->service()->gradeQuestion($question, ['صح'], null));
        $this->assertSame(['is_correct' => false, 'marks' => 0.0], $this->service()->gradeQuestion($question, ['false'], null));
        $this->assertSame(['is_correct' => false, 'marks' => 0.0], $this->service()->gradeQuestion($question, [], null));
    }

    // ------------------------------------------------------- short

    public function test_short_answer_is_case_insensitive(): void
    {
        $question = $this->question(['type' => 'short', 'options' => null, 'correct_answer' => 'Makkah']);

        $this->assertSame(['is_correct' => true, 'marks' => 5.0], $this->service()->gradeQuestion($question, [], '  makkah '));
        $this->assertSame(['is_correct' => false, 'marks' => 0.0], $this->service()->gradeQuestion($question, [], 'Madinah'));
    }

    public function test_short_without_expected_answer_needs_manual_grading(): void
    {
        $question = $this->question(['type' => 'short', 'options' => null, 'correct_answer' => null]);

        $this->assertSame(['is_correct' => null, 'marks' => null], $this->service()->gradeQuestion($question, [], 'أي إجابة'));
    }

    // ------------------------------------------------------- essay

    public function test_essay_is_never_auto_graded(): void
    {
        $question = $this->question(['type' => 'essay', 'options' => null, 'correct_answer' => null]);

        $this->assertSame(['is_correct' => null, 'marks' => null], $this->service()->gradeQuestion($question, [], 'مقالة'));
    }
}
