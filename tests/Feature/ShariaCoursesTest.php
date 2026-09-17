<?php

namespace Tests\Feature;

use App\Models\ShariaCourse;
use App\Models\ShariaCourseAttendance;
use App\Models\ShariaCourseLesson;
use App\Models\ShariaCourseStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use App\Services\ShariaCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShariaCoursesTest extends TestCase
{
    use RefreshDatabase;

    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        $admin = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $admin];
    }

    private function makeTeacher(string $tenantId): array
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $teacher = Teacher::factory()->create(['tenant_id' => $tenantId, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function makeCourse(string $tenantId, User $admin, array $attributes = []): ShariaCourse
    {
        return ShariaCourse::create([
            'tenant_id' => $tenantId,
            'name' => 'دورة الفقه',
            'status' => 'active',
            'created_by' => $admin->id,
            ...$attributes,
        ]);
    }

    private function addStudent(ShariaCourse $course, string $name = 'طالب الدورة'): ShariaCourseStudent
    {
        return ShariaCourseStudent::create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_admin_creates_course_and_audit_is_recorded(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.store'), [
                'name' => 'دورة التفسير',
                'supervisor_ids' => [$teacher->id],
                'location' => 'القاعة الكبرى',
                'start_date' => now()->toDateString(),
                'status' => 'active',
            ])
            ->assertRedirect();

        $course = ShariaCourse::query()->where('name', 'دورة التفسير')->firstOrFail();

        $this->assertDatabaseHas('sharia_courses', [
            'tenant_id' => $mosque->id,
            'name' => 'دورة التفسير',
        ]);
        $this->assertDatabaseHas('sharia_course_supervisor', [
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sharia_course.created']);
    }

    public function test_super_admin_inside_a_mosque_can_create_course_with_supervisors(): void
    {
        [$mosque] = $this->mosque();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_SUPER_ADMIN]);
        app(RoleService::class)->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        [, $teacher] = $this->makeTeacher($mosque->id);
        [, $secondTeacher] = $this->makeTeacher($mosque->id);

        $this->actingAs($superAdmin)
            ->withSession(['super_admin_mosque_id' => $mosque->id])
            ->post(route('admin.sharia-courses.store'), [
                'name' => 'دورة العقيدة',
                'supervisor_ids' => [$teacher->id, $secondTeacher->id],
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $course = ShariaCourse::query()->where('name', 'دورة العقيدة')->firstOrFail();

        $this->assertDatabaseHas('sharia_courses', [
            'tenant_id' => $mosque->id,
            'name' => 'دورة العقيدة',
        ]);
        $this->assertSame(2, $course->supervisors()->count());
    }

    public function test_course_students_are_fully_independent_from_school_students(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = $this->makeCourse($mosque->id, $admin);

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.store', $course), [
                'name' => 'عبد الله المستقل',
                'phone' => '0500000000',
                'gender' => 'male',
            ])
            ->assertRedirect();

        $this->assertSame(1, ShariaCourseStudent::query()->count());
        $this->assertSame(0, Student::query()->count());
        $this->assertDatabaseHas('sharia_course_students', ['name' => 'عبد الله المستقل', 'course_id' => $course->id]);
    }

    public function test_attendance_records_all_four_statuses_and_computes_percentage(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = $this->makeCourse($mosque->id, $admin);
        $lesson = ShariaCourseLesson::create([
            'tenant_id' => $mosque->id,
            'course_id' => $course->id,
            'title' => 'الدرس الأول',
            'type' => 'lesson',
            'date' => now()->toDateString(),
        ]);

        $s1 = $this->addStudent($course, 'طالب 1');
        $s2 = $this->addStudent($course, 'طالب 2');
        $s3 = $this->addStudent($course, 'طالب 3');
        $s4 = $this->addStudent($course, 'طالب 4');

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.attendance.store', $course), [
                'date' => now()->toDateString(),
                'lesson_id' => $lesson->id,
                'marks' => [
                    $s1->id => ['status' => 'present'],
                    $s2->id => ['status' => 'absent'],
                    $s3->id => ['status' => 'late'],
                    $s4->id => ['status' => 'excused', 'notes' => 'مرض'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(4, ShariaCourseAttendance::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'sharia_course.attendance_saved']);

        $summary = app(ShariaCourseService::class)->attendanceSummary($course);
        $this->assertSame(1, $summary['present']);
        $this->assertSame(1, $summary['absent']);
        $this->assertSame(1, $summary['late']);
        $this->assertSame(1, $summary['excused']);
        $this->assertSame(66.7, $summary['percentage']);
    }

    public function test_excused_without_notes_is_rejected(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = $this->makeCourse($mosque->id, $admin);
        $student = $this->addStudent($course);

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.attendance.store', $course), [
                'date' => now()->toDateString(),
                'marks' => [
                    $student->id => ['status' => 'excused'],
                ],
            ])
            ->assertSessionHasErrors('attendance');

        $this->assertSame(0, ShariaCourseAttendance::query()->count());
    }

    public function test_attendance_upserts_instead_of_duplicating(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = $this->makeCourse($mosque->id, $admin);
        $lesson = ShariaCourseLesson::create([
            'tenant_id' => $mosque->id,
            'course_id' => $course->id,
            'title' => 'درس',
            'type' => 'lesson',
            'date' => now()->toDateString(),
        ]);
        $student = $this->addStudent($course);

        $payload = [
            'date' => now()->toDateString(),
            'lesson_id' => $lesson->id,
            'marks' => [$student->id => ['status' => 'present']],
        ];

        $this->actingAs($admin)->post(route('admin.sharia-courses.attendance.store', $course), $payload)->assertRedirect();

        $payload['marks'][$student->id]['status'] = 'absent';
        $this->actingAs($admin)->post(route('admin.sharia-courses.attendance.store', $course), $payload)->assertRedirect();

        $this->assertSame(1, ShariaCourseAttendance::query()->count());
        $this->assertSame('absent', ShariaCourseAttendance::query()->first()->status->value);
    }

    public function test_teacher_without_supervision_is_forbidden(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = $this->makeCourse($mosque->id, $admin);
        [$teacherUser] = $this->makeTeacher($mosque->id);

        $this->actingAs($teacherUser)
            ->get(route('teacher.sharia-courses.show', $course))
            ->assertForbidden();
    }

    public function test_supervising_teacher_can_access_and_record_attendance(): void
    {
        [$mosque, $admin] = $this->mosque();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);
        $course = $this->makeCourse($mosque->id, $admin);
        $course->supervisors()->sync([$teacher->id]);
        $student = $this->addStudent($course);

        $this->actingAs($teacherUser)
            ->get(route('teacher.sharia-courses.show', $course))
            ->assertOk()
            ->assertSee($course->name);

        $this->actingAs($teacherUser)
            ->post(route('teacher.sharia-courses.attendance.store', $course), [
                'date' => now()->toDateString(),
                'marks' => [$student->id => ['status' => 'present']],
            ])
            ->assertRedirect();

        $this->assertSame(1, ShariaCourseAttendance::query()->count());
    }

    public function test_course_from_another_mosque_returns_not_found(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = $this->makeCourse($mosque->id, $admin);

        $otherMosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $otherMosque->id]);
        app(RoleService::class)->provisionTenantRoles($otherMosque);
        $otherAdmin = User::factory()->admin()->for($otherMosque)->create();

        $this->actingAs($otherAdmin)
            ->get(route('admin.sharia-courses.show', $course))
            ->assertNotFound();
    }

    public function test_all_course_tabs_render_for_admin(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = $this->makeCourse($mosque->id, $admin);
        $this->addStudent($course);

        foreach (['lessons', 'students', 'attendance', 'report'] as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.sharia-courses.show', ['course' => $course, 'tab' => $tab]))
                ->assertOk()
                ->assertSee($course->name);
        }

        $this->actingAs($admin)->get(route('admin.sharia-courses.index'))->assertOk()->assertSee($course->name);
    }

    public function test_deleting_course_cascades_lessons_students_and_attendance(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = $this->makeCourse($mosque->id, $admin);
        $lesson = ShariaCourseLesson::create([
            'tenant_id' => $mosque->id,
            'course_id' => $course->id,
            'title' => 'درس',
            'type' => 'lesson',
            'date' => now()->toDateString(),
        ]);
        $student = $this->addStudent($course);
        ShariaCourseAttendance::create([
            'tenant_id' => $mosque->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.sharia-courses.destroy', $course))
            ->assertRedirect(route('admin.sharia-courses.index'));

        $this->assertSame(0, ShariaCourse::query()->count());
        $this->assertSame(0, ShariaCourseLesson::query()->count());
        $this->assertSame(0, ShariaCourseStudent::query()->count());
        $this->assertSame(0, ShariaCourseAttendance::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'sharia_course.deleted']);
    }
}
