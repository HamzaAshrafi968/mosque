<?php

namespace Tests\Feature;

use App\Enums\ShariaMemorizationStatus;
use App\Models\ShariaCourse;
use App\Models\ShariaCourseStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الإنشاء المركزي للدورات الشرعية من مدير الجوامع: اختيار الجامع والمشرفين
 * والطلاب (موجودين + جدد)، إشعار مدير الجامع، وربط الطلاب وحالة الحفظ.
 */
class ShariaCourseCentralTest extends TestCase
{
    use RefreshDatabase;

    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $manager];
    }

    private function superAdmin(): User
    {
        config(['app.current_tenant_id' => null]);

        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_SUPER_ADMIN]);
        app(RoleService::class)->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        return $superAdmin;
    }

    private function makeTeacher(string $tenantId, string $name = 'مشرف الدورة'): array
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $teacher = Teacher::factory()->create(['tenant_id' => $tenantId, 'user_id' => $user->id, 'name' => $name]);

        return [$user, $teacher];
    }

    public function test_super_admin_creates_course_for_a_mosque_and_notifies_its_manager(): void
    {
        [$mosque, $manager] = $this->mosque();
        [, $otherManager] = $this->mosque();
        [, $supervisor] = $this->makeTeacher($mosque->id, 'المشرف الأول');

        $existingStudent = Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'طالب موجود']);

        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('super-admin.sharia-courses.store'), [
                'mosque_id' => $mosque->id,
                'name' => 'دورة الفقه المركزي',
                'description' => 'دورة أضافها مدير الجوامع',
                'location' => 'القاعة الكبرى',
                'start_date' => now()->toDateString(),
                'status' => 'active',
                'supervisor_ids' => [$supervisor->id],
                'student_ids' => [$existingStudent->id],
                'new_students' => [
                    ['name' => 'طالب جديد', 'phone' => '0599000000', 'gender' => 'male'],
                ],
            ])
            ->assertRedirect(route('super-admin.sharia-courses.index', ['mosque_id' => $mosque->id]))
            ->assertSessionHasNoErrors();

        $course = ShariaCourse::withoutGlobalScope('tenant')
            ->where('name', 'دورة الفقه المركزي')
            ->firstOrFail();

        $this->assertSame($mosque->id, $course->tenant_id);
        $this->assertSame(ShariaCourse::SOURCE_SUPER_ADMIN, $course->source);
        $this->assertTrue($course->isFromSuperAdmin());
        $this->assertSame($superAdmin->id, $course->created_by);
        $this->assertSame(1, $course->supervisors()->count());

        $this->assertDatabaseHas('sharia_course_supervisor', [
            'course_id' => $course->id,
            'teacher_id' => $supervisor->id,
        ]);

        $this->assertSame(2, $course->students()->count());
        $this->assertDatabaseHas('sharia_course_students', [
            'course_id' => $course->id,
            'student_id' => $existingStudent->id,
            'name' => 'طالب موجود',
        ]);
        $this->assertDatabaseHas('sharia_course_students', [
            'course_id' => $course->id,
            'student_id' => null,
            'name' => 'طالب جديد',
        ]);

        // مدير الجامع المستهدف يصله الإشعار، ومدير جامع آخر لا.
        $this->assertSame(1, $manager->fresh()->notifications()->count());
        $this->assertSame('دورة شرعية جديدة', $manager->fresh()->notifications()->first()->data['title']);
        $this->assertSame(0, $otherManager->fresh()->notifications()->count());

        // الشاشات المركزية تُعرض لمدير الجوامع.
        $this->actingAs($superAdmin)
            ->get(route('super-admin.sharia-courses.index'))
            ->assertOk()
            ->assertSee('دورة الفقه المركزي')
            ->assertSee('من مدير الجوامع');

        $this->actingAs($superAdmin)
            ->get(route('super-admin.sharia-courses.create'))
            ->assertOk()
            ->assertSee('خصائص الدورة');

        // الدورة تظهر في شاشات إدارة الجامع المستهدف فقط.
        config(['app.current_tenant_id' => $mosque->id]);

        $this->actingAs($manager)
            ->get(route('admin.sharia-courses.index'))
            ->assertOk()
            ->assertSee('دورة الفقه المركزي')
            ->assertSee('من مدير الجوامع');

        $this->actingAs($manager)
            ->get(route('admin.sharia-courses.show', ['course' => $course, 'tab' => 'students']))
            ->assertOk()
            ->assertSee('طالب موجود')
            ->assertSee('طالب جديد');
    }

    public function test_options_endpoint_lists_only_the_selected_mosque_teachers_and_students(): void
    {
        [$mosque] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id, 'مشرف الجامع المستهدف');
        Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'طالب الجامع المستهدف']);

        [$otherMosque] = $this->mosque();
        $this->makeTeacher($otherMosque->id, 'مشرف جامع آخر');
        Student::factory()->create(['tenant_id' => $otherMosque->id, 'name' => 'طالب جامع آخر']);

        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->getJson(route('super-admin.sharia-courses.options', ['mosque_id' => $mosque->id]))
            ->assertOk()
            ->assertJsonFragment(['name' => 'مشرف الجامع المستهدف'])
            ->assertJsonFragment(['name' => 'طالب الجامع المستهدف'])
            ->assertJsonMissing(['name' => 'مشرف جامع آخر'])
            ->assertJsonMissing(['name' => 'طالب جامع آخر']);
    }

    public function test_admin_enrolls_existing_students_once_without_duplicates(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة الحديث',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $first = Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'أول']);
        $second = Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'ثاني']);

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.existing', $course), [
                'student_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, ShariaCourseStudent::query()->count());
        $this->assertDatabaseHas('sharia_course_students', ['student_id' => $first->id, 'name' => 'أول']);
        $this->assertDatabaseHas('sharia_course_students', ['student_id' => $second->id, 'name' => 'ثاني']);

        // إعادة التسجيل لا تكرر الصفوف.
        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.existing', $course), [
                'student_ids' => [$first->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, ShariaCourseStudent::query()->count());
    }

    public function test_memorization_status_is_updated_by_admin_and_supervising_teacher_only(): void
    {
        [$mosque, $admin] = $this->mosque();
        [$supervisorUser, $supervisor] = $this->makeTeacher($mosque->id, 'المشرف');
        [$otherUser] = $this->makeTeacher($mosque->id, 'معلم غير مشرف');

        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة التجويد',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);
        $course->supervisors()->sync([$supervisor->id]);

        $student = ShariaCourseStudent::create([
            'tenant_id' => $mosque->id,
            'course_id' => $course->id,
            'name' => 'طالب الحفظ',
            'status' => 'active',
        ]);

        // المدير يحدّث الحالة.
        $this->actingAs($admin)
            ->patch(route('admin.sharia-courses.students.memorization', $student), [
                'memorization_status' => ShariaMemorizationStatus::HalfMemorized->value,
                'memorization_notes' => 'أتمّ نصف المنهج',
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(ShariaMemorizationStatus::HalfMemorized, $student->memorization_status);
        $this->assertSame($admin->id, $student->memorization_updated_by);

        // المشرف يحدّث الحالة.
        $this->actingAs($supervisorUser)
            ->patch(route('teacher.sharia-courses.students.memorization', $student), [
                'memorization_status' => ShariaMemorizationStatus::Memorized->value,
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(ShariaMemorizationStatus::Memorized, $student->memorization_status);
        $this->assertSame($supervisorUser->id, $student->memorization_updated_by);
        $this->assertNotNull($student->memorization_updated_at);

        // معلم غير مشرف لا يستطيع التحديث.
        $this->actingAs($otherUser)
            ->patch(route('teacher.sharia-courses.students.memorization', $student), [
                'memorization_status' => ShariaMemorizationStatus::NotMemorized->value,
            ])
            ->assertForbidden();

        $this->assertSame(ShariaMemorizationStatus::Memorized, $student->fresh()->memorization_status);
    }

    public function test_super_admin_course_is_isolated_from_other_mosques(): void
    {
        [$mosque] = $this->mosque();
        [$otherMosque, $otherManager] = $this->mosque();

        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('super-admin.sharia-courses.store'), [
                'mosque_id' => $mosque->id,
                'name' => 'دورة خاصة',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        config(['app.current_tenant_id' => $otherMosque->id]);

        $this->actingAs($otherManager)
            ->get(route('admin.sharia-courses.index'))
            ->assertOk()
            ->assertDontSee('دورة خاصة');
    }
}
