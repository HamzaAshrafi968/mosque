<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ExamService;
use App\Services\RoleService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * نطاق الاختبار: دوام كامل أو صف/عدة صفوف، والتحقق من قائمة الطلاب
 * وظهور الاختبار للطالب في الحالتين.
 */
class ExamTargetingTest extends TestCase
{
    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $manager];
    }

    private function shift(Tenant $mosque, string $name): StudySession
    {
        return StudySession::create([
            'tenant_id' => $mosque->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function classroom(Tenant $mosque, string $name, ?StudySession $session = null): Classroom
    {
        return Classroom::create([
            'tenant_id' => $mosque->id,
            'name' => $name,
            'study_session_id' => $session?->id,
        ]);
    }

    private function subject(Tenant $mosque): Subject
    {
        return Subject::create(['tenant_id' => $mosque->id, 'name' => 'القرآن']);
    }

    private function student(Tenant $mosque, Classroom $classroom, ?StudySession $session = null, ?Section $section = null): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section?->id,
            'study_session_id' => $session?->id,
        ]);
    }

    public function test_shift_wide_exam_targets_every_student_in_the_shift(): void
    {
        [$mosque, $manager] = $this->mosque();
        $firstShift = $this->shift($mosque, 'الدوام الأول');
        $secondShift = $this->shift($mosque, 'الدوام الثاني');

        $firstClassroom = $this->classroom($mosque, 'الصف الأول', $firstShift);
        $secondClassroom = $this->classroom($mosque, 'الصف الثاني', $firstShift);
        $otherClassroom = $this->classroom($mosque, 'الصف الثالث', $secondShift);

        $firstStudent = $this->student($mosque, $firstClassroom, $firstShift);
        $secondStudent = $this->student($mosque, $secondClassroom, $firstShift);
        $otherStudent = $this->student($mosque, $otherClassroom, $secondShift);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'اختبار الدوام الأول',
                'subject_id' => $this->subject($mosque)->id,
                'study_session_id' => $firstShift->id,
                'exam_date' => today()->toDateString(),
                'total_marks' => 10,
                'pass_marks' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();

        $this->assertNull($exam->classroom_id);
        $this->assertSame($firstShift->id, $exam->study_session_id);
        $this->assertSame(0, $exam->classrooms()->count());
        $this->assertTrue($exam->isShiftWide());

        $rosterIds = app(ExamService::class)->roster($exam)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$firstStudent->id, $secondStudent->id], $rosterIds);
        $this->assertNotContains($otherStudent->id, $rosterIds);

        $exam->update(['status' => 'published', 'published_at' => now()]);

        $this->assertTrue(Exam::query()->visibleForStudent($firstStudent)->whereKey($exam->id)->exists());
        $this->assertFalse(Exam::query()->visibleForStudent($otherStudent)->whereKey($exam->id)->exists());
    }

    public function test_exam_can_target_several_classrooms(): void
    {
        [$mosque, $manager] = $this->mosque();
        $shift = $this->shift($mosque, 'الدوام الأول');

        $firstClassroom = $this->classroom($mosque, 'الصف الأول', $shift);
        $secondClassroom = $this->classroom($mosque, 'الصف الثاني', $shift);
        $otherClassroom = $this->classroom($mosque, 'الصف الثالث', $shift);

        $firstStudent = $this->student($mosque, $firstClassroom, $shift);
        $secondStudent = $this->student($mosque, $secondClassroom, $shift);
        $otherStudent = $this->student($mosque, $otherClassroom, $shift);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'اختبار صفين',
                'subject_id' => $this->subject($mosque)->id,
                'study_session_id' => $shift->id,
                'classroom_ids' => [$firstClassroom->id, $secondClassroom->id],
                'exam_date' => today()->toDateString(),
                'total_marks' => 10,
                'pass_marks' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();

        $this->assertSame($firstClassroom->id, $exam->classroom_id);
        $this->assertEqualsCanonicalizing(
            [$firstClassroom->id, $secondClassroom->id],
            $exam->classrooms()->pluck('classrooms.id')->all()
        );

        $rosterIds = app(ExamService::class)->roster($exam)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$firstStudent->id, $secondStudent->id], $rosterIds);
        $this->assertNotContains($otherStudent->id, $rosterIds);

        $exam->update(['status' => 'published', 'published_at' => now()]);

        $this->assertTrue(Exam::query()->visibleForStudent($secondStudent)->whereKey($exam->id)->exists());
        $this->assertFalse(Exam::query()->visibleForStudent($otherStudent)->whereKey($exam->id)->exists());
    }

    public function test_single_classroom_exam_can_still_filter_by_section(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque, 'الصف الأول');
        $firstSection = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);
        $secondSection = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'ب']);

        $firstStudent = $this->student($mosque, $classroom, null, $firstSection);
        $secondStudent = $this->student($mosque, $classroom, null, $secondSection);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'اختبار الشعبة أ',
                'subject_id' => $this->subject($mosque)->id,
                'classroom_ids' => [$classroom->id],
                'section_id' => $firstSection->id,
                'exam_date' => today()->toDateString(),
                'total_marks' => 10,
                'pass_marks' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();

        $rosterIds = app(ExamService::class)->roster($exam)->pluck('id')->all();
        $this->assertSame([$firstStudent->id], $rosterIds);
        $this->assertNotContains($secondStudent->id, $rosterIds);
    }

    public function test_exam_requires_a_shift_or_at_least_one_classroom(): void
    {
        [$mosque, $manager] = $this->mosque();

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'اختبار بلا نطاق',
                'subject_id' => $this->subject($mosque)->id,
                'exam_date' => today()->toDateString(),
                'total_marks' => 10,
                'pass_marks' => 5,
            ])
            ->assertSessionHasErrors('classroom_ids');

        $this->assertSame(0, Exam::count());
    }

    public function test_a_section_cannot_be_combined_with_several_classrooms(): void
    {
        [$mosque, $manager] = $this->mosque();
        $firstClassroom = $this->classroom($mosque, 'الصف الأول');
        $secondClassroom = $this->classroom($mosque, 'الصف الثاني');
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $firstClassroom->id, 'name' => 'أ']);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'اختبار خاطئ',
                'subject_id' => $this->subject($mosque)->id,
                'classroom_ids' => [$firstClassroom->id, $secondClassroom->id],
                'section_id' => $section->id,
                'exam_date' => today()->toDateString(),
                'total_marks' => 10,
                'pass_marks' => 5,
            ])
            ->assertSessionHasErrors('section_id');

        $this->assertSame(0, Exam::count());
    }

    public function test_a_classroom_from_another_shift_is_rejected(): void
    {
        [$mosque, $manager] = $this->mosque();
        $firstShift = $this->shift($mosque, 'الدوام الأول');
        $secondShift = $this->shift($mosque, 'الدوام الثاني');
        $otherClassroom = $this->classroom($mosque, 'صف الدوام الثاني', $secondShift);

        $this->actingAs($manager)
            ->post(route('admin.exams.store'), [
                'title' => 'اختبار خاطئ',
                'subject_id' => $this->subject($mosque)->id,
                'study_session_id' => $firstShift->id,
                'classroom_ids' => [$otherClassroom->id],
                'exam_date' => today()->toDateString(),
                'total_marks' => 10,
                'pass_marks' => 5,
            ])
            ->assertSessionHasErrors('classroom_ids');

        $this->assertSame(0, Exam::count());
    }

    public function test_create_page_exposes_the_target_picker(): void
    {
        [$mosque, $manager] = $this->mosque();
        $shift = $this->shift($mosque, 'الدوام الأول');
        $classroom = $this->classroom($mosque, 'الصف الأول', $shift);
        Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);

        $this->actingAs($manager)
            ->get(route('admin.exams.create'))
            ->assertOk()
            ->assertSee('data-exam-target-picker', false)
            ->assertSee('دوام كامل')
            ->assertSee('صفوف محددة')
            ->assertSee('الصف الأول');
    }

    public function test_teacher_create_page_exposes_the_target_picker(): void
    {
        [$mosque] = $this->mosque();
        $shift = $this->shift($mosque, 'الدوام الأول');
        $this->classroom($mosque, 'الصف الأول', $shift);

        $teacherUser = User::factory()->for($mosque)->create();
        Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $teacherUser->id]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.exams.create'))
            ->assertOk()
            ->assertSee('data-exam-target-picker', false)
            ->assertSee('الصف الأول');
    }

    public function test_admin_api_creates_a_multi_classroom_exam(): void
    {
        [$mosque, $manager] = $this->mosque();
        $firstClassroom = $this->classroom($mosque, 'الصف الأول');
        $secondClassroom = $this->classroom($mosque, 'الصف الثاني');

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/admin/exams', [
            'title' => 'اختبار عبر API',
            'subject_id' => $this->subject($mosque)->id,
            'classroom_ids' => [$firstClassroom->id, $secondClassroom->id],
            'exam_date' => today()->toDateString(),
            'total_marks' => 10,
            'pass_marks' => 5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'اختبار عبر API');

        $exam = Exam::firstOrFail();

        $this->assertSame(2, DB::table('exam_classroom')->where('exam_id', $exam->id)->count());
    }
}
