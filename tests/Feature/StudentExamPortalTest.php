<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Tests\TestCase;

/**
 * بوابة الطالب معطّلة: حتى مع وجود امتحان منشور صالح، كل مسارات الامتحانات
 * الإلكترونية تحوّل إلى صفحة «البوابة معطّلة» ولا تُنشأ محاولات.
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

    public function test_student_exam_portal_routes_are_disabled(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom);
        $this->question($exam);
        [, $user] = $this->studentUser($mosque, $classroom);

        $this->actingAs($user)->get(route('student.exams'))->assertRedirect(route('portal.disabled'));
        $this->actingAs($user)->get(route('student.exams.start', $exam))->assertRedirect(route('portal.disabled'));
        $this->actingAs($user)->get(route('student.exams.take', $exam))->assertRedirect(route('portal.disabled'));
        $this->actingAs($user)->get(route('student.exams.result', $exam))->assertRedirect(route('portal.disabled'));
    }

    public function test_disabled_portal_does_not_create_exam_attempts(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom);
        $question = $this->question($exam);
        [, $user] = $this->studentUser($mosque, $classroom);

        $this->actingAs($user)->get(route('student.exams.start', $exam));

        $this->actingAs($user)->post(route('student.exams.submit', $exam), [
            'answers' => [
                $question->id => ['selected_options' => ['الفاتحة']],
            ],
        ])->assertRedirect(route('portal.disabled'));

        $this->assertDatabaseCount('exam_attempts', 0);
    }
}
