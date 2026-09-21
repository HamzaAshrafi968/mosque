<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Tests\TestCase;

/**
 * بوابة الطالب: ظهور الامتحانات الإلكترونية المنشورة، بدء المحاولة،
 * صفحة الامتحان بالمؤقت، التسليم، وورقة النتيجة.
 */
class StudentExamPortalTest extends TestCase
{
    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    private function classroom(Tenant $mosque, string $name = 'الصف الأول'): Classroom
    {
        return Classroom::create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    private function exam(Tenant $mosque, Classroom $classroom, array $overrides = []): Exam
    {
        return Exam::create(array_merge([
            'tenant_id' => $mosque->id,
            'subject_id' => Subject::create(['tenant_id' => $mosque->id, 'name' => 'القرآن'])->id,
            'classroom_id' => $classroom->id,
            'title' => 'اختبار التجويد',
            'kind' => 'quiz',
            'mode' => 'online',
            'status' => 'published',
            'published_at' => now(),
            'exam_date' => today()->toDateString(),
            'duration_minutes' => 15,
            'total_marks' => 10,
            'pass_marks' => 5,
        ], $overrides));
    }

    private function question(Exam $exam): ExamQuestion
    {
        return ExamQuestion::create([
            'tenant_id' => $exam->tenant_id,
            'exam_id' => $exam->id,
            'type' => 'mcq',
            'text' => 'ما أول سورة في المصحف؟',
            'marks' => 10,
            'options' => ['الفاتحة', 'البقرة'],
            'correct_answer' => 'الفاتحة',
            'sort_order' => 1,
        ]);
    }

    /** @return array{0: Student, 1: User} */
    private function studentUser(Tenant $mosque, Classroom $classroom): array
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);

        $student = Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'user_id' => $user->id,
        ]);

        return [$student, $user];
    }

    public function test_student_exams_page_lists_published_electronic_exams(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom);
        $this->question($exam);
        [, $user] = $this->studentUser($mosque, $classroom);

        $this->actingAs($user)
            ->get(route('student.exams'))
            ->assertOk()
            ->assertSee('الامتحانات الإلكترونية')
            ->assertSee($exam->title)
            ->assertSee('ابدأ الامتحان');
    }

    public function test_student_can_start_take_and_submit_an_exam(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom);
        $question = $this->question($exam);
        [, $user] = $this->studentUser($mosque, $classroom);

        $this->actingAs($user)
            ->get(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.take', $exam));

        $this->actingAs($user)
            ->get(route('student.exams.take', $exam))
            ->assertOk()
            ->assertSee($question->text)
            ->assertSee('exam-timer', false);

        $this->actingAs($user)
            ->post(route('student.exams.submit', $exam), [
                'answers' => [
                    $question->id => ['selected_options' => ['الفاتحة']],
                ],
            ])
            ->assertRedirect(route('student.exams.result', $exam));

        $attempt = ExamAttempt::firstOrFail();

        $this->assertSame('graded', $attempt->status);
        $this->assertSame('10.00', $attempt->score);
    }

    public function test_student_result_page_shows_the_score_and_the_answer_key(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom);
        $question = $this->question($exam);
        [, $user] = $this->studentUser($mosque, $classroom);

        $this->actingAs($user)->get(route('student.exams.start', $exam));

        $this->actingAs($user)->post(route('student.exams.submit', $exam), [
            'answers' => [$question->id => ['selected_options' => ['الفاتحة']]],
        ]);

        $this->actingAs($user)
            ->get(route('student.exams.result', $exam))
            ->assertOk()
            ->assertSee('10')
            ->assertSee('ناجح')
            ->assertSee('الفاتحة');
    }

    public function test_student_cannot_access_an_exam_for_another_classroom(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $otherClassroom = $this->classroom($mosque, 'الصف الثاني');
        $exam = $this->exam($mosque, $otherClassroom);
        $this->question($exam);
        [, $user] = $this->studentUser($mosque, $classroom);

        $this->actingAs($user)
            ->get(route('student.exams.start', $exam))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('student.exams.take', $exam))
            ->assertForbidden();
    }

    public function test_unpublished_exam_is_not_visible_to_students(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, ['status' => 'draft', 'published_at' => null]);
        $this->question($exam);
        [, $user] = $this->studentUser($mosque, $classroom);

        $this->actingAs($user)->get(route('student.exams'))->assertDontSee($exam->title);
        $this->actingAs($user)->get(route('student.exams.take', $exam))->assertForbidden();
    }

    public function test_a_finished_attempt_redirects_to_the_result_instead_of_retaking(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom);
        $question = $this->question($exam);
        [, $user] = $this->studentUser($mosque, $classroom);

        $this->actingAs($user)->get(route('student.exams.start', $exam));

        $this->actingAs($user)->post(route('student.exams.submit', $exam), [
            'answers' => [$question->id => ['selected_options' => ['الفاتحة']]],
        ]);

        $this->actingAs($user)
            ->get(route('student.exams.take', $exam))
            ->assertRedirect(route('student.exams.result', $exam));
    }
}
