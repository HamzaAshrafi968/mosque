<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Models\Classroom;
use App\Models\Homework;
use App\Models\HomeworkQuestion;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Tests\TestCase;

/**
 * أسئلة الواجب (تصحيح آلي): الإنشاء مع الإجابات الصحيحة، مطابقة الدرجة الكلية،
 * إدارة الأسئلة لاحقاً، القفل بعد التصحيح، والعزل بين الأساتذة.
 */
class HomeworkQuestionsTest extends TestCase
{
    /** @return array{0: Tenant, 1: User} */
    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return [$mosque, User::factory()->admin()->for($mosque)->create()];
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacherUser(Tenant $mosque): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function subject(Tenant $mosque): Subject
    {
        return Subject::create(['tenant_id' => $mosque->id, 'name' => 'القرآن']);
    }

    private function classroom(Tenant $mosque): Classroom
    {
        return Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);
    }

    private function student(Tenant $mosque, Classroom $classroom): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function homework(Tenant $mosque, Teacher $teacher, Classroom $classroom, array $overrides = []): Homework
    {
        return Homework::create(array_merge([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $this->subject($mosque)->id,
            'classroom_id' => $classroom->id,
            'title' => 'واجب التجويد',
            'due_date' => today()->addDay()->toDateString(),
            'pass_marks' => 2,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function question(Homework $homework, array $overrides = []): HomeworkQuestion
    {
        return HomeworkQuestion::create(array_merge([
            'tenant_id' => $homework->tenant_id,
            'homework_id' => $homework->id,
            'type' => 'mcq',
            'text' => 'ما أول سورة في المصحف؟',
            'marks' => 3,
            'options' => ['الفاتحة', 'البقرة'],
            'correct_answer' => 'الفاتحة',
            'sort_order' => 1,
        ], $overrides));
    }

    // ------------------------------------------------------------- create

    public function test_teacher_creates_homework_with_auto_graded_questions(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $subject = $this->subject($mosque);
        $classroom = $this->classroom($mosque);
        $this->student($mosque, $classroom);

        $this->actingAs($teacherUser)->post(route('teacher.homeworks.store'), [
            'title' => 'واجب الفاتحة',
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'due_date' => today()->addDay()->toDateString(),
            'pass_marks' => 3,
            'total_marks' => 7,
            'questions' => [
                ['type' => 'mcq', 'text' => 'ما أول سورة؟', 'marks' => 2, 'options' => ['الفاتحة', 'البقرة'], 'correct_answer' => 0],
                ['type' => 'true_false', 'text' => 'الفاتحة مكية', 'marks' => 1, 'correct_answer' => 'true'],
                ['type' => 'short', 'text' => 'اسم أول سورة', 'marks' => 2, 'correct_answer' => 'الفاتحة'],
                ['type' => 'essay', 'text' => 'اشرح فضل الفاتحة', 'marks' => 2],
            ],
        ])->assertRedirect(route('teacher.homeworks.index'));

        $homework = Homework::where('title', 'واجب الفاتحة')->firstOrFail();
        $this->assertSame('7.00', (string) $homework->total_marks);
        $this->assertSame(4, $homework->questions()->count());

        $mcq = $homework->questions()->where('type', QuestionType::Mcq->value)->firstOrFail();
        $this->assertSame('الفاتحة', $mcq->correct_answer);

        $essay = $homework->questions()->where('type', QuestionType::Essay->value)->firstOrFail();
        $this->assertNull($essay->correct_answer);

        $this->assertSame(1, $homework->submissions()->count());
    }

    public function test_questions_total_must_match_the_declared_total_marks(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $subject = $this->subject($mosque);
        $classroom = $this->classroom($mosque);

        $this->actingAs($teacherUser)->post(route('teacher.homeworks.store'), [
            'title' => 'واجب غير متوازن',
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'due_date' => today()->addDay()->toDateString(),
            'total_marks' => 5,
            'questions' => [
                ['type' => 'true_false', 'text' => 'الفاتحة مكية', 'marks' => 2, 'correct_answer' => 'true'],
            ],
        ])->assertSessionHasErrors('total_marks');

        $this->assertDatabaseCount('homeworks', 0);
    }

    public function test_pass_marks_cannot_exceed_the_total_marks(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser] = $this->teacherUser($mosque);
        $subject = $this->subject($mosque);
        $classroom = $this->classroom($mosque);

        $this->actingAs($teacherUser)->post(route('teacher.homeworks.store'), [
            'title' => 'واجب',
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'due_date' => today()->addDay()->toDateString(),
            'pass_marks' => 8,
            'total_marks' => 5,
            'questions' => [
                ['type' => 'true_false', 'text' => 'الفاتحة مكية', 'marks' => 5, 'correct_answer' => 'true'],
            ],
        ])->assertSessionHasErrors('pass_marks');

        $this->assertDatabaseCount('homeworks', 0);
    }

    // ------------------------------------------------------------- manage

    public function test_edit_page_lists_questions_with_their_correct_answers(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $classroom = $this->classroom($mosque);
        $homework = $this->homework($mosque, $teacher, $classroom, ['total_marks' => 3]);
        $this->question($homework);

        $this->actingAs($teacherUser)->get(route('teacher.homeworks.edit', $homework))
            ->assertOk()
            ->assertSee('ما أول سورة في المصحف؟')
            ->assertSee('الإجابة الصحيحة')
            ->assertSee('الفاتحة');
    }

    public function test_adding_questions_updates_the_total_marks(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $classroom = $this->classroom($mosque);
        $homework = $this->homework($mosque, $teacher, $classroom);

        $this->actingAs($teacherUser)->post(route('teacher.homeworks.questions.store', $homework), [
            'questions' => [
                ['type' => 'mcq', 'text' => 'سؤال', 'marks' => 3, 'options' => ['أ', 'ب'], 'correct_answer' => 0],
            ],
        ])->assertRedirect();

        $this->assertSame('3.00', (string) $homework->fresh()->total_marks);
        $this->assertSame(1, $homework->questions()->count());
    }

    public function test_updating_and_deleting_a_question_recalculates_the_total_marks(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $classroom = $this->classroom($mosque);
        $homework = $this->homework($mosque, $teacher, $classroom, ['total_marks' => 3]);
        $question = $this->question($homework);

        $this->actingAs($teacherUser)->put(route('teacher.homeworks.questions.update', $question), [
            'text' => 'ما أول سورة في المصحف؟',
            'marks' => 4,
            'options' => ['الفاتحة', 'البقرة'],
            'correct_answer' => 0,
        ])->assertRedirect();

        $this->assertSame('4.00', (string) $homework->fresh()->total_marks);

        $this->actingAs($teacherUser)
            ->delete(route('teacher.homeworks.questions.destroy', $question))
            ->assertRedirect();

        $this->assertNull($homework->fresh()->total_marks);
        $this->assertSame(0, $homework->questions()->count());
    }

    public function test_updating_main_fields_keeps_the_declared_total_for_manual_homework(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $classroom = $this->classroom($mosque);
        $homework = $this->homework($mosque, $teacher, $classroom, ['total_marks' => 50, 'pass_marks' => 25]);

        $this->actingAs($teacherUser)->put(route('teacher.homeworks.update', $homework), [
            'title' => 'واجب محدّث',
            'subject_id' => $homework->subject_id,
            'classroom_id' => $classroom->id,
            'due_date' => today()->addDays(2)->toDateString(),
            'total_marks' => 50,
            'pass_marks' => 25,
        ])->assertRedirect(route('teacher.homeworks.edit', $homework));

        $homework->refresh();
        $this->assertSame('واجب محدّث', $homework->title);
        $this->assertSame('50.00', (string) $homework->total_marks);
    }

    public function test_updating_a_homework_with_questions_rejects_a_mismatched_total(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $classroom = $this->classroom($mosque);
        $homework = $this->homework($mosque, $teacher, $classroom, ['total_marks' => 3]);
        $this->question($homework);

        $this->actingAs($teacherUser)->put(route('teacher.homeworks.update', $homework), [
            'title' => $homework->title,
            'subject_id' => $homework->subject_id,
            'classroom_id' => $classroom->id,
            'due_date' => $homework->due_date->format('Y-m-d'),
            'total_marks' => 5,
            'pass_marks' => 2,
        ])->assertSessionHasErrors('total_marks');

        $this->assertSame('3.00', (string) $homework->fresh()->total_marks);
    }

    public function test_homework_is_locked_after_any_submission_is_graded(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $classroom = $this->classroom($mosque);
        $homework = $this->homework($mosque, $teacher, $classroom);
        $student = $this->student($mosque, $classroom);

        $submission = $homework->submissions()->create([
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
        ]);

        $this->actingAs($teacherUser)->patch(route('teacher.submissions.update', $submission), [
            'grade' => 5,
            'status' => 'graded',
        ])->assertRedirect();

        $this->actingAs($teacherUser)
            ->get(route('teacher.homeworks.edit', $homework))
            ->assertRedirect(route('teacher.homeworks.submissions', $homework));

        $this->actingAs($teacherUser)->post(route('teacher.homeworks.questions.store', $homework), [
            'questions' => [
                ['type' => 'true_false', 'text' => 'سؤال', 'marks' => 1, 'correct_answer' => 'true'],
            ],
        ])->assertSessionHasErrors('homework');
    }

    public function test_submissions_page_shows_questions_and_correct_answers(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $classroom = $this->classroom($mosque);
        $homework = $this->homework($mosque, $teacher, $classroom, ['total_marks' => 3]);
        $this->question($homework);

        $this->actingAs($teacherUser)->get(route('teacher.homeworks.submissions', $homework))
            ->assertOk()
            ->assertSee('أسئلة الواجب والإجابات الصحيحة')
            ->assertSee('ما أول سورة في المصحف؟')
            ->assertSee('الفاتحة');
    }

    public function test_index_shows_the_question_badge_and_edit_link(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $classroom = $this->classroom($mosque);
        $homework = $this->homework($mosque, $teacher, $classroom, ['total_marks' => 3]);
        $this->question($homework);

        $this->actingAs($teacherUser)->get(route('teacher.homeworks.index'))
            ->assertOk()
            ->assertSee('1 سؤال')
            ->assertSee('تعديل');
    }

    // ------------------------------------------------------------- isolation

    public function test_a_teacher_cannot_manage_another_teachers_homework(): void
    {
        [$mosque] = $this->mosque();
        [, $teacher] = $this->teacherUser($mosque);
        [$otherUser] = $this->teacherUser($mosque);
        $classroom = $this->classroom($mosque);
        $homework = $this->homework($mosque, $teacher, $classroom);

        $this->actingAs($otherUser)
            ->get(route('teacher.homeworks.edit', $homework))
            ->assertForbidden();

        $this->actingAs($otherUser)->post(route('teacher.homeworks.questions.store', $homework), [
            'questions' => [
                ['type' => 'true_false', 'text' => 'سؤال', 'marks' => 1, 'correct_answer' => 'true'],
            ],
        ])->assertForbidden();
    }
}
