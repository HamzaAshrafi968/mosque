<?php

namespace Tests\Unit;

use App\Models\Classroom;
use App\Models\Homework;
use App\Models\HomeworkQuestion;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Services\HomeworkAutoGradeService;
use Tests\TestCase;

/**
 * محرّك التصحيح الآلي للواجبات: لا علامات سالبة، والجواب الفارغ = صفر،
 * والمقالي/بلا إجابة متوقعة يحتاج تصحيحاً يدوياً.
 */
class HomeworkAutoGraderTest extends TestCase
{
    private function homework(): Homework
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);
        $teacher = Teacher::factory()->create(['tenant_id' => $tenant->id]);

        return Homework::create([
            'tenant_id' => $tenant->id,
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::create(['tenant_id' => $tenant->id, 'name' => 'القرآن'])->id,
            'classroom_id' => Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الصف الأول'])->id,
            'title' => 'واجب',
            'due_date' => today()->addDay()->toDateString(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function question(Homework $homework, array $overrides = []): HomeworkQuestion
    {
        return HomeworkQuestion::create(array_merge([
            'tenant_id' => $homework->tenant_id,
            'homework_id' => $homework->id,
            'type' => 'mcq',
            'text' => 'سؤال',
            'marks' => 3,
            'options' => ['أ', 'ب'],
            'correct_answer' => 'أ',
        ], $overrides));
    }

    private function grader(): HomeworkAutoGradeService
    {
        return app(HomeworkAutoGradeService::class);
    }

    public function test_all_auto_graded_answers_produce_a_final_score(): void
    {
        $homework = $this->homework();
        $mcq = $this->question($homework);
        $trueFalse = $this->question($homework, ['type' => 'true_false', 'marks' => 2, 'options' => null, 'correct_answer' => 'true']);
        $short = $this->question($homework, ['type' => 'short', 'marks' => 2, 'options' => null, 'correct_answer' => 'Makkah']);

        $result = $this->grader()->gradeAnswers($homework, [
            $mcq->id => ['selected_options' => ['أ']],
            $trueFalse->id => ['answer_text' => 'صح'],
            $short->id => ['answer_text' => '  makkah '],
        ]);

        $this->assertSame(7.0, $result['score']);
        $this->assertFalse($result['needs_manual_grading']);
        $this->assertTrue($result['details'][$mcq->id]['is_correct']);
    }

    public function test_wrong_and_empty_answers_score_zero_without_negative_marks(): void
    {
        $homework = $this->homework();
        $mcq = $this->question($homework);
        $second = $this->question($homework, ['text' => 'سؤال آخر']);

        $result = $this->grader()->gradeAnswers($homework, [
            $mcq->id => ['selected_options' => ['ب']],
            $second->id => [],
        ]);

        $this->assertSame(0.0, $result['score']);
        $this->assertFalse($result['details'][$mcq->id]['is_correct']);
        $this->assertSame(0.0, $result['details'][$second->id]['marks']);
    }

    public function test_checkbox_grants_partial_marks(): void
    {
        $homework = $this->homework();
        $checkbox = $this->question($homework, [
            'type' => 'checkbox',
            'marks' => 4,
            'options' => ['أ', 'ب', 'ج'],
            'correct_answer' => json_encode(['أ', 'ب']),
        ]);

        $result = $this->grader()->gradeAnswers($homework, [
            $checkbox->id => ['selected_options' => ['أ']],
        ]);

        $this->assertSame(2.0, $result['score']);
        $this->assertFalse($result['details'][$checkbox->id]['is_correct']);
    }

    public function test_essay_or_short_without_expected_answer_needs_manual_grading(): void
    {
        $homework = $this->homework();
        $essay = $this->question($homework, ['type' => 'essay', 'options' => null, 'correct_answer' => null]);
        $short = $this->question($homework, ['type' => 'short', 'options' => null, 'correct_answer' => null]);

        $result = $this->grader()->gradeAnswers($homework, [
            $essay->id => ['answer_text' => 'مقالة'],
            $short->id => ['answer_text' => 'إجابة'],
        ]);

        $this->assertNull($result['score']);
        $this->assertTrue($result['needs_manual_grading']);
        $this->assertNull($result['details'][$essay->id]['marks']);
        $this->assertNull($result['details'][$short->id]['marks']);
    }
}
