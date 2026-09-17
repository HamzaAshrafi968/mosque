<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\RoleService;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * منع التعارضات على كل مسارات الكتابة: نموذج الإدارة، الـ API، والتسجيل.
 */
class ScheduleConflictPreventionTest extends TestCase
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

    private function section(Tenant $mosque, Classroom $classroom, string $name = 'أ'): Section
    {
        return Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => $name]);
    }

    private function teacher(Tenant $mosque, string $name = 'المعلم'): Teacher
    {
        return Teacher::factory()->create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    private function subject(Tenant $mosque, string $name = 'القرآن'): Subject
    {
        return Subject::create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    private function payload(Tenant $mosque, Classroom $classroom, array $overrides = []): array
    {
        return array_merge([
            'classroom_id' => $classroom->id,
            'teacher_id' => $this->teacher($mosque)->id,
            'subject_id' => $this->subject($mosque)->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ], $overrides);
    }

    // ------------------------------------------------------- admin form

    public function test_admin_form_rejects_a_teacher_double_booking(): void
    {
        [$mosque, $manager] = $this->mosque();
        $teacher = $this->teacher($mosque);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque, 'الصف الأول')->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $this->classroom($mosque, 'الصف الثاني'), [
                'teacher_id' => $teacher->id,
                'starts_at' => '08:30',
                'ends_at' => '09:30',
            ]))
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(1, Schedule::count());
    }

    public function test_admin_form_rejects_a_section_double_booking(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $section = $this->section($mosque, $classroom);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
            'teacher_id' => $this->teacher($mosque)->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $classroom, [
                'section_id' => $section->id,
                'starts_at' => '08:30',
                'ends_at' => '09:30',
            ]))
            ->assertSessionHasErrors('classroom_id');

        $this->assertSame(1, Schedule::count());
    }

    public function test_adjacent_slots_are_allowed(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $teacher = $this->teacher($mosque);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $classroom, [
                'teacher_id' => $teacher->id,
                'starts_at' => '09:00',
                'ends_at' => '10:00',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Schedule::count());
    }

    public function test_teacher_conflict_is_detected_across_shifts(): void
    {
        [$mosque, $manager] = $this->mosque();
        $teacher = $this->teacher($mosque);

        $morning = StudySession::create(['tenant_id' => $mosque->id, 'name' => 'الصباحي']);
        $evening = StudySession::create(['tenant_id' => $mosque->id, 'name' => 'المسائي']);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque, 'الصف الصباحي')->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $morning->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        // The manager is viewing the evening shift, yet the teacher conflict
        // in the morning shift must still be caught.
        config(['app.current_study_session_id' => $evening->id]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $this->classroom($mosque, 'الصف المسائي'), [
                'teacher_id' => $teacher->id,
                'study_session_id' => $evening->id,
                'starts_at' => '08:30',
                'ends_at' => '09:30',
            ]))
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(1, Schedule::count());
    }

    // ------------------------------------------------------- API

    public function test_api_rejects_a_conflicting_slot(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $teacher = $this->teacher($mosque);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/admin/schedules', $this->payload($mosque, $classroom, [
            'teacher_id' => $teacher->id,
            'day_of_week' => 1,
            'starts_at' => '08:30',
            'ends_at' => '09:30',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('teacher_id');

        $this->assertSame(1, Schedule::count());
    }

    // ------------------------------------------------------- enrollment

    public function test_enrollment_rejects_a_student_schedule_conflict(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $sectionA = $this->section($mosque, $classroom, 'أ');
        $sectionB = $this->section($mosque, $classroom, 'ب');

        $student = Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $sectionA->id,
        ]);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $sectionA->id,
            'teacher_id' => $this->teacher($mosque)->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $sectionB->id,
            'teacher_id' => $this->teacher($mosque, 'معلم آخر')->id,
            'day_of_week' => 0,
            'starts_at' => '08:30',
            'ends_at' => '09:30',
        ]);

        try {
            app(EnrollmentService::class)->enroll($student, $sectionB);
            $this->fail('Expected a schedule conflict to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('section_id', $exception->errors());
        }

        $this->assertNull($student->fresh()->enrollments()->where('section_id', $sectionB->id)->first());
    }

    public function test_enrollment_allows_a_student_without_conflicts(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $sectionA = $this->section($mosque, $classroom, 'أ');
        $sectionB = $this->section($mosque, $classroom, 'ب');

        $student = Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $sectionA->id,
        ]);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $sectionA->id,
            'teacher_id' => $this->teacher($mosque)->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $sectionB->id,
            'teacher_id' => $this->teacher($mosque, 'معلم آخر')->id,
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $membership = app(EnrollmentService::class)->enroll($student, $sectionB);

        $this->assertSame($sectionB->id, $membership->section_id);
        $this->assertSame($sectionB->id, $student->fresh()->section_id);
    }
}
