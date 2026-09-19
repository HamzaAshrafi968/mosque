<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ExamService;
use App\Services\RoleService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * محرّك الاختبارات: النشر، المحاولة الواحدة، المؤقت والتسليم، التصحيح الآلي
 * والمزامنة مع الدرجات، قفل التعديل، والصلاحيات.
 */
class ExamEngineTest extends TestCase
{
    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $manager];
    }

    private function classroom(Tenant $mosque, string $name = 'الصف الأول'): Classroom
    {
        return Classroom::create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    private function teacherUser(Tenant $mosque): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    /** @param array<string, mixed> $overrides */
    private function exam(Tenant $mosque, Classroom $classroom, array $overrides = []): Exam
    {
        return Exam::create(array_merge([
            'tenant_id' => $mosque->id,
            'subject_id' => Subject::create(['tenant_id' => $mosque->id, 'name' => 'القرآن'])->id,
            'classroom_id' => $classroom->id,
            'title' => 'اختبار التجويد',
            'kind' => 'exam',
            'mode' => 'online',
            'status' => 'draft',
            'exam_date' => today()->toDateString(),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'pass_marks' => 5,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function question(Exam $exam, array $overrides = []): ExamQuestion
    {
        return ExamQuestion::create(array_merge([
            'tenant_id' => $exam->tenant_id,
            'exam_id' => $exam->id,
            'type' => 'mcq',
            'text' => 'ما أول سورة في المصحف؟',
            'marks' => 10,
            'options' => ['الفاتحة', 'البقرة'],
            'correct_answer' => 'الفاتحة',
            'sort_order' => 1,
        ], $overrides));
    }

    private function student(Tenant $mosque, Classroom $classroom, ?User $user = null): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'user_id' => $user?->id,
        ]);
    }

    // ------------------------------------------------------- publish

    public function test_publish_requires_a_question_or_a_pdf(): void
    {
        [$mosque, $manager] = $this->mosque();
        $exam = $this->exam($mosque, $this->classroom($mosque));

        $this->actingAs($manager)
            ->post(route('admin.exams.publish', $exam))
            ->assertSessionHasErrors('publish');

        $this->assertSame(ExamStatus::Draft, $exam->fresh()->status);
    }

    public function test_publish_rejects_a_marks_mismatch(): void
    {
        [$mosque, $manager] = $this->mosque();
        $exam = $this->exam($mosque, $this->classroom($mosque));
        $this->question($exam, ['marks' => 4]);

        $this->actingAs($manager)
            ->post(route('admin.exams.publish', $exam))
            ->assertSessionHasErrors('publish');

        $this->assertSame(ExamStatus::Draft, $exam->fresh()->status);
    }

    public function test_publish_succeeds_and_notifies_students(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom);
        $this->question($exam);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $this->student($mosque, $classroom, $studentUser);

        $this->actingAs($manager)
            ->post(route('admin.exams.publish', $exam))
            ->assertRedirect();

        $exam->refresh();

        $this->assertSame(ExamStatus::Published, $exam->status);
        $this->assertNotNull($exam->published_at);
        $this->assertGreaterThanOrEqual(1, DB::table('notifications')->count());
    }

    // ------------------------------------------------------- attempts

    public function test_start_creates_one_attempt_and_resumes_it(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, ['status' => 'published', 'published_at' => now()]);
        $this->question($exam);
        $student = $this->student($mosque, $classroom);

        $service = app(ExamService::class);

        $first = $service->start($exam, $student);
        $second = $service->start($exam, $student->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ExamAttempt::count());
    }

    public function test_start_rejects_a_future_exam_and_unpublished_exam(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $service = app(ExamService::class);
        $student = $this->student($mosque, $classroom);

        $draft = $this->exam($mosque, $classroom);
        $this->question($draft);

        $this->expectException(ValidationException::class);
        $service->start($draft, $student);
    }

    public function test_start_rejects_a_future_exam_date(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, [
            'status' => 'published',
            'published_at' => now(),
            'exam_date' => today()->addDay()->toDateString(),
        ]);
        $this->question($exam);
        $student = $this->student($mosque, $classroom);

        $this->expectException(ValidationException::class);
        app(ExamService::class)->start($exam, $student);
    }

    public function test_start_rejects_a_finished_attempt(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, ['status' => 'published', 'published_at' => now()]);
        $this->question($exam);
        $student = $this->student($mosque, $classroom);

        ExamAttempt::create([
            'tenant_id' => $mosque->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now(),
            'status' => ExamAttempt::STATUS_GRADED,
            'score' => 10,
        ]);

        $this->expectException(ValidationException::class);
        app(ExamService::class)->start($exam, $student);
    }

    // ------------------------------------------------------- submit

    public function test_submit_auto_grades_and_syncs_a_submitted_grade(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, ['status' => 'published', 'published_at' => now()]);
        $mcq = $this->question($exam);
        $checkbox = $this->question($exam, [
            'type' => 'checkbox',
            'text' => 'اختر الأجزاء المحفوظة',
            'marks' => 0,
            'options' => ['جزء 1', 'جزء 2'],
            'correct_answer' => json_encode(['جزء 1', 'جزء 2']),
            'sort_order' => 2,
        ]);
        $student = $this->student($mosque, $classroom);

        $service = app(ExamService::class);
        $attempt = $service->start($exam, $student);

        $attempt = $service->submit($attempt, [
            $mcq->id => ['selected_options' => ['الفاتحة']],
            $checkbox->id => ['selected_options' => ['جزء 1', 'جزء 2']],
        ]);

        $this->assertSame(ExamAttempt::STATUS_GRADED, $attempt->status);
        $this->assertSame('10.00', $attempt->score);
        $this->assertNotNull($attempt->submitted_at);

        $grade = Grade::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('submitted', $grade->status);
        $this->assertSame('10.00', $grade->score);
    }

    public function test_submit_with_an_essay_waits_for_manual_grading(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, ['status' => 'published', 'published_at' => now()]);
        $mcq = $this->question($exam, ['marks' => 5]);
        $essay = $this->question($exam, ['type' => 'essay', 'marks' => 5, 'options' => null, 'correct_answer' => null, 'sort_order' => 2]);
        $student = $this->student($mosque, $classroom);

        $service = app(ExamService::class);
        $attempt = $service->submit($service->start($exam, $student), [
            $mcq->id => ['selected_options' => ['الفاتحة']],
            $essay->id => ['answer_text' => 'إجابة مقالية'],
        ]);

        $this->assertSame(ExamAttempt::STATUS_SUBMITTED, $attempt->status);
        $this->assertNull($attempt->score);
        $this->assertSame(0, Grade::count());
    }

    public function test_submit_rejects_after_the_grace_period(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, ['status' => 'published', 'published_at' => now()]);
        $this->question($exam);
        $student = $this->student($mosque, $classroom);

        $attempt = ExamAttempt::create([
            'tenant_id' => $mosque->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(32),
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
        ]);

        $this->expectException(ValidationException::class);
        app(ExamService::class)->submit($attempt, []);
    }

    public function test_submit_is_accepted_inside_the_grace_period(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, ['status' => 'published', 'published_at' => now()]);
        $question = $this->question($exam);
        $student = $this->student($mosque, $classroom);

        $attempt = ExamAttempt::create([
            'tenant_id' => $mosque->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => now()->subSeconds(30 * 60 + 30),
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
        ]);

        $attempt = app(ExamService::class)->submit($attempt, [
            $question->id => ['selected_options' => ['الفاتحة']],
        ]);

        $this->assertSame(ExamAttempt::STATUS_GRADED, $attempt->status);
    }

    // ------------------------------------------------------- manual grading

    public function test_manual_grading_validates_the_range_and_syncs_the_grade(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, ['status' => 'published', 'published_at' => now()]);
        $this->question($exam, ['type' => 'essay', 'options' => null, 'correct_answer' => null]);
        $student = $this->student($mosque, $classroom);

        $service = app(ExamService::class);
        $attempt = $service->submit($service->start($exam, $student), []);

        $this->actingAs($manager)
            ->get(route('admin.exams.show', $exam))
            ->assertOk()
            ->assertSee('بانتظار التصحيح');

        $this->actingAs($manager)
            ->post(route('admin.exams.attempts.grade', $attempt), ['score' => 99])
            ->assertSessionHasErrors('score');

        $this->actingAs($manager)
            ->post(route('admin.exams.attempts.grade', $attempt), ['score' => 8])
            ->assertRedirect();

        $attempt->refresh();

        $this->assertSame(ExamAttempt::STATUS_GRADED, $attempt->status);
        $this->assertSame('8.00', $attempt->score);
        $this->assertSame('submitted', Grade::firstOrFail()->status);
    }

    public function test_pdf_attachment_can_be_uploaded_streamed_and_deleted(): void
    {
        Storage::fake('local');

        [$mosque, $manager] = $this->mosque();
        $exam = $this->exam($mosque, $this->classroom($mosque));

        $this->actingAs($manager)
            ->post(route('admin.exams.attachment.store', $exam), [
                'attachment' => UploadedFile::fake()->create('exam.pdf', 80, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $exam->refresh();

        $this->assertNotNull($exam->attachment_key);
        $this->assertSame('exam.pdf', $exam->attachment_name);

        $this->actingAs($manager)
            ->get(route('admin.exams.attachment', $exam))
            ->assertOk();

        $this->actingAs($manager)
            ->delete(route('admin.exams.attachment.destroy', $exam))
            ->assertRedirect();

        $this->assertNull($exam->fresh()->attachment_key);
    }

    public function test_manager_can_create_an_exam_from_the_web_form_as_a_draft(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'الفقه']);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'امتحان الفقه',
                'kind' => 'quiz',
                'mode' => 'online',
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'exam_date' => today()->toDateString(),
                'duration_minutes' => 20,
                'total_marks' => 20,
                'pass_marks' => 10,
            ])
            ->assertRedirect();

        $exam = Exam::firstOrFail();

        $this->assertSame('draft', $exam->status->value);
        $this->assertSame('quiz', $exam->kind->value);
        $this->assertSame('online', $exam->mode->value);
        $this->assertSame(20, $exam->duration_minutes);
    }

    public function test_manager_can_create_an_exam_with_questions_in_one_submission(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'التوحيد']);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'امتحان التوحيد',
                'kind' => 'exam',
                'mode' => 'online',
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'exam_date' => today()->toDateString(),
                'duration_minutes' => 15,
                'total_marks' => 10,
                'pass_marks' => 5,
                'type' => 'mcq',
                'questions' => [
                    ['text' => 'سؤال 1', 'marks' => 6, 'options' => ['أ', 'ب'], 'correct_answer' => 0],
                    ['text' => 'سؤال 2', 'marks' => 4, 'options' => ['ج', 'د'], 'correct_answer' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();

        $this->assertSame('draft', $exam->status->value);
        $this->assertSame(2, $exam->questions()->count());
        $this->assertSame(10.0, $exam->questionsTotalMarks());

        $questions = $exam->questions()->get();

        $this->assertSame('أ', $questions[0]->correct_answer);
        $this->assertSame('د', $questions[1]->correct_answer);
    }

    public function test_manager_can_create_an_exam_with_mixed_question_types_in_one_submission(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'التفسير']);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'امتحان مختلط',
                'kind' => 'exam',
                'mode' => 'online',
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'exam_date' => today()->toDateString(),
                'total_marks' => 9,
                'pass_marks' => 5,
                'questions' => [
                    ['type' => 'mcq', 'text' => 'اختيار 1', 'marks' => 3, 'options' => ['أ', 'ب'], 'correct_answer' => 0],
                    ['type' => 'mcq', 'text' => 'اختيار 2', 'marks' => 3, 'options' => ['ج', 'د'], 'correct_answer' => 1],
                    ['type' => 'true_false', 'text' => 'صح أو خطأ', 'marks' => 2, 'correct_answer' => 'true'],
                    ['type' => 'essay', 'text' => 'مقالي بدون علامة', 'marks' => ''],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();
        $questions = $exam->questions()->orderBy('sort_order')->get();

        $this->assertSame(4, $questions->count());
        $this->assertSame(['mcq', 'mcq', 'true_false', 'essay'], $questions->pluck('type')->map(fn ($type) => $type->value)->all());
        $this->assertSame([3.0, 3.0, 2.0, 1.0], $questions->pluck('marks')->map(fn ($marks) => (float) $marks)->all());
        $this->assertSame('أ', $questions[0]->correct_answer);
        $this->assertSame('د', $questions[1]->correct_answer);
        $this->assertSame('true', $questions[2]->correct_answer);
        $this->assertNull($questions[3]->correct_answer);
    }

    public function test_exam_creation_ignores_blank_question_rows(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'الحديث']);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'امتحان بلا أسئلة',
                'kind' => 'exam',
                'mode' => 'onsite',
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'exam_date' => today()->toDateString(),
                'total_marks' => 10,
                'pass_marks' => 5,
                'type' => 'mcq',
                'questions' => [
                    ['text' => '', 'marks' => 1, 'options' => ['', ''], 'correct_answer' => ''],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();

        $this->assertSame(0, $exam->questions()->count());
    }

    public function test_exam_creation_rolls_back_when_a_question_is_invalid(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'السيرة']);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'امتحان خاطئ',
                'kind' => 'exam',
                'mode' => 'online',
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'exam_date' => today()->toDateString(),
                'total_marks' => 10,
                'pass_marks' => 5,
                'type' => 'mcq',
                'questions' => [
                    ['text' => 'سؤال خاطئ', 'marks' => 10, 'options' => ['أ', 'ب'], 'correct_answer' => 'ز'],
                ],
            ])
            ->assertSessionHasErrors('questions.0.correct_answer');

        $this->assertSame(0, Exam::count());
    }

    public function test_teacher_can_create_an_exam_with_questions_from_the_portal(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'التفسير']);

        $this->actingAs($teacherUser)
            ->post(route('teacher.exams.store'), [
                'title' => 'امتحان التفسير',
                'kind' => 'quiz',
                'mode' => 'online',
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'exam_date' => today()->toDateString(),
                'total_marks' => 4,
                'pass_marks' => 2,
                'type' => 'true_false',
                'questions' => [
                    ['text' => 'القرآن أربعة عشر جزءاً', 'marks' => 4, 'correct_answer' => 'false'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();

        $this->assertSame($teacher->id, $exam->teacher_id);
        $this->assertSame(1, $exam->questions()->count());
        $this->assertSame('false', $exam->questions()->first()->correct_answer);
    }

    public function test_exam_create_pages_include_the_question_builder(): void
    {
        [$mosque, $manager] = $this->mosque();
        [$teacherUser] = $this->teacherUser($mosque);
        Subject::create(['tenant_id' => $mosque->id, 'name' => 'القرآن']);

        $this->actingAs($manager)
            ->get(route('admin.exams.create'))
            ->assertOk()
            ->assertSee('data-exam-question-builder', false)
            ->assertSee('إضافة قسم')
            ->assertSee('توليد الصفوف');

        $this->actingAs($teacherUser)
            ->get(route('teacher.exams.create'))
            ->assertOk()
            ->assertSee('data-exam-question-builder', false)
            ->assertSee('إضافة قسم')
            ->assertSee('توليد الصفوف');
    }

    // ------------------------------------------------------- edit lock

    public function test_questions_cannot_be_changed_after_an_attempt_starts(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom, ['status' => 'published', 'published_at' => now()]);
        $question = $this->question($exam);
        $student = $this->student($mosque, $classroom);

        app(ExamService::class)->start($exam, $student);

        $this->actingAs($manager)
            ->post(route('admin.exams.questions.store', $exam), [
                'type' => 'mcq',
                'questions' => [
                    ['text' => 'سؤال جديد', 'marks' => 1, 'options' => ['أ', 'ب'], 'correct_answer' => 'أ'],
                ],
            ])
            ->assertSessionHasErrors('exam');

        $this->actingAs($manager)
            ->delete(route('admin.exams.questions.destroy', $question))
            ->assertSessionHasErrors('exam');

        $this->assertSame(1, $exam->questions()->count());
    }

    public function test_bulk_question_builder_stores_mixed_types_and_defaults_empty_marks(): void
    {
        [$mosque, $manager] = $this->mosque();
        $exam = $this->exam($mosque, $this->classroom($mosque));

        $this->actingAs($manager)
            ->post(route('admin.exams.questions.store', $exam), [
                'questions' => [
                    ['type' => 'true_false', 'text' => 'صح/خطأ 1', 'marks' => '', 'correct_answer' => 'false'],
                    ['type' => 'short', 'text' => 'قصير 1', 'marks' => 2, 'correct_answer' => 'كلمة'],
                    ['type' => 'checkbox', 'text' => 'متعدد 1', 'marks' => 3, 'options' => ['أ', 'ب'], 'correct_options' => [0, 1]],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $questions = $exam->questions()->orderBy('sort_order')->get();

        $this->assertSame(3, $questions->count());
        $this->assertSame(['true_false', 'short', 'checkbox'], $questions->pluck('type')->map(fn ($type) => $type->value)->all());
        $this->assertSame(1.0, (float) $questions[0]->marks);
        $this->assertSame(2.0, (float) $questions[1]->marks);
        $this->assertSame(3.0, (float) $questions[2]->marks);
        $this->assertSame(['أ', 'ب'], $questions[2]->correct_answer ? json_decode($questions[2]->correct_answer, true) : null);
    }

    public function test_bulk_question_builder_stores_questions_with_validation(): void
    {
        [$mosque, $manager] = $this->mosque();
        $exam = $this->exam($mosque, $this->classroom($mosque));

        $this->actingAs($manager)
            ->post(route('admin.exams.questions.store', $exam), [
                'type' => 'mcq',
                'questions' => [
                    ['text' => 'سؤال 1', 'marks' => 6, 'options' => ['أ', 'ب'], 'correct_answer' => 0],
                    ['text' => 'سؤال 2', 'marks' => 4, 'options' => ['ج', 'د'], 'correct_answer' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $exam->questions()->count());
        $this->assertSame('أ', $exam->questions()->orderBy('sort_order')->first()->correct_answer);

        // An mcq whose correct answer is not one of the options is rejected.
        $this->actingAs($manager)
            ->post(route('admin.exams.questions.store', $exam), [
                'type' => 'mcq',
                'questions' => [
                    ['text' => 'سؤال خاطئ', 'marks' => 1, 'options' => ['أ', 'ب'], 'correct_answer' => 'ز'],
                ],
            ])
            ->assertSessionHasErrors('questions.0.correct_answer');
    }

    // ------------------------------------------------------- authorization

    public function test_teacher_can_manage_own_exam_but_not_another_teachers_exam(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        [, $otherTeacher] = $this->teacherUser($mosque);

        $own = $this->exam($mosque, $classroom, ['teacher_id' => $teacher->id]);
        $foreign = $this->exam($mosque, $classroom, ['teacher_id' => $otherTeacher->id, 'title' => 'امتحان آخر']);

        $this->actingAs($teacherUser)->get(route('teacher.exams.show', $own))->assertOk();
        $this->actingAs($teacherUser)->get(route('teacher.exams.show', $foreign))->assertForbidden();

        $this->actingAs($teacherUser)
            ->post(route('teacher.exams.questions.store', $foreign), [
                'type' => 'mcq',
                'questions' => [['text' => 'سؤال', 'marks' => 1, 'options' => ['أ', 'ب'], 'correct_answer' => 'أ']],
            ])
            ->assertForbidden();
    }

    public function test_revoking_publish_permission_blocks_publishing(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom);
        $this->question($exam);

        $role = Role::where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_MOSQUE_MANAGER)->firstOrFail();
        app(RoleService::class)->syncRolePermissions($role, ['students.view' => 'mosque']);

        $this->actingAs($manager)
            ->post(route('admin.exams.publish', $exam))
            ->assertForbidden();
    }

    // ------------------------------------------------------- API

    public function test_admin_api_publishes_questions_and_reads_results(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $exam = $this->exam($mosque, $classroom);
        $student = $this->student($mosque, $classroom);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/admin/exams/{$exam->id}/questions", [
            'type' => 'mcq',
            'questions' => [
                ['text' => 'ما أول سورة؟', 'marks' => 10, 'options' => ['الفاتحة', 'البقرة'], 'correct_answer' => 'الفاتحة'],
            ],
        ])->assertCreated();

        $this->postJson("/api/v1/admin/exams/{$exam->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $attempt = app(ExamService::class)->start($exam->fresh(), $student);
        app(ExamService::class)->submit($attempt, [
            $exam->questions()->firstOrFail()->id => ['selected_options' => ['الفاتحة']],
        ]);

        $this->getJson("/api/v1/admin/exams/{$exam->id}/results")
            ->assertOk()
            ->assertJsonPath('data.attempts.0.score', 10);
    }

    public function test_teacher_api_rejects_another_teachers_exam(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        [$teacherUser] = $this->teacherUser($mosque);
        [, $otherTeacher] = $this->teacherUser($mosque);

        $foreign = $this->exam($mosque, $classroom, ['teacher_id' => $otherTeacher->id]);

        Sanctum::actingAs($teacherUser);

        $this->postJson("/api/v1/teacher/exams/{$foreign->id}/publish")->assertForbidden();
    }
}
