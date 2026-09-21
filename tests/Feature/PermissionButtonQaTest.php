<?php

namespace Tests\Feature;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Permission;
use App\Models\ProgramEnrollment;
use App\Models\Role;
use App\Models\Section;
use App\Models\ShariaCourse;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * QA matrix for button visibility: granting a permission shows its button and
 * revoking it removes the button from the user's interface (not just the route).
 *
 * Three groups: shift (دوامات), people (teacher/manager), system (settings).
 */
class PermissionButtonQaTest extends TestCase
{
    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    private function manager(Tenant $mosque): User
    {
        return User::factory()->admin()->for($mosque)->create();
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function revoke(Tenant $mosque, string $roleCode, string $permissionCode): void
    {
        $permission = Permission::where('code', $permissionCode)->firstOrFail();

        Role::where('tenant_id', $mosque->id)
            ->where('code', $roleCode)
            ->firstOrFail()
            ->permissions()
            ->detach($permission->id);
    }

    // --------------------------------------------------------------- shift

    public static function shiftButtons(): array
    {
        return [
            'create form' => ['sessions.create', 'إضافة دوام جديد'],
            'edit form' => ['sessions.update', 'حفظ التعديل'],
            'delete button' => ['sessions.delete', 'حذف دوام'],
        ];
    }

    #[DataProvider('shiftButtons')]
    public function test_shift_buttons_disappear_when_permission_is_revoked(string $permission, string $button): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        StudySession::create(['tenant_id' => $mosque->id, 'name' => 'الدوام الأول', 'is_active' => true]);

        $this->actingAs($manager)
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee($button, false);

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, $permission);

        $this->actingAs($manager)
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertDontSee($button, false);
    }

    public function test_shift_page_and_switcher_disappear_without_view_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        StudySession::create(['tenant_id' => $mosque->id, 'name' => 'الدوام الأول', 'is_active' => true]);

        $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()->assertSee('كل الدوامات');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'sessions.view');

        $this->actingAs($manager)->get(route('admin.sessions.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()->assertDontSee('كل الدوامات');
    }

    // --------------------------------------------------------------- people

    public static function adminQuranButtons(): array
    {
        return [
            'tasmee create' => ['quran.tasmee.create', '+ تسجيل تسميع'],
            'completion create' => ['quran.completion.view', '+ تسجيل إتمام حفظ'],
            'faith meeting create' => ['faith_meetings.create', '+ لقاء إيماني'],
        ];
    }

    #[DataProvider('adminQuranButtons')]
    public function test_admin_quran_hub_buttons_disappear_when_permission_is_revoked(string $permission, string $button): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.quran.index'))->assertOk()->assertSee($button);

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, $permission);

        $this->actingAs($manager)->get(route('admin.quran.index'))->assertOk()->assertDontSee($button);
    }

    public static function teacherQuranButtons(): array
    {
        return [
            'tasmee create' => ['quran.tasmee.create', '+ تسجيل تسميع'],
            'qualifying create' => ['qualifying.create', '+ تقييم أسبوعي'],
            'ijazah create' => ['ijazah.create', '+ تقييم شهري'],
        ];
    }

    #[DataProvider('teacherQuranButtons')]
    public function test_teacher_quran_hub_buttons_disappear_when_permission_is_revoked(string $permission, string $button): void
    {
        $mosque = $this->mosque();
        [$teacherUser] = $this->teacher($mosque);

        $this->actingAs($teacherUser)->get(route('teacher.quran.index'))->assertOk()->assertSee($button);

        $this->revoke($mosque, RoleService::ROLE_TEACHER, $permission);

        $this->actingAs($teacherUser)->get(route('teacher.quran.index'))->assertOk()->assertDontSee($button);
    }

    public function test_readings_enrollment_form_disappears_without_update_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        // برنامج القراءات مرحلة متقدمة: النموذج يظهر للمؤهلين (أتموا الإجازة) فقط.
        $student = Student::factory()->create(['tenant_id' => $mosque->id, 'status' => 'active']);

        ProgramEnrollment::create([
            'student_id' => $student->id,
            'program_type' => ProgramType::Ijazah,
            'started_at' => now()->subMonth()->toDateString(),
            'completed_at' => now()->toDateString(),
            'status' => ProgramEnrollmentStatus::Completed,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.quran.programs.index', ['type' => 'readings']))
            ->assertOk()
            ->assertSee('تسجيل في القراءات');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'quran_training.update');

        $this->actingAs($manager)
            ->get(route('admin.quran.programs.index', ['type' => 'readings']))
            ->assertOk()
            ->assertDontSee('تسجيل في القراءات');
    }

    public function test_student_index_buttons_follow_their_permissions(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        Student::factory()->create(['tenant_id' => $mosque->id, 'status' => 'active']);

        $this->actingAs($manager)->get(route('admin.students.index'))->assertOk()->assertSee('إضافة طالب');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'students.create');

        $this->actingAs($manager)->get(route('admin.students.index'))->assertOk()->assertDontSee('إضافة طالب');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'students.delete');

        $this->actingAs($manager)->get(route('admin.students.index'))->assertOk()->assertDontSee('>حذف<', false);
    }

    public function test_users_index_create_button_disappears_without_create_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.users.index'))->assertOk()->assertSee('>إنشاء<', false);

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'users.create');

        $this->actingAs($manager)->get(route('admin.users.index'))->assertOk()->assertDontSee('>إنشاء<', false);
    }

    public function test_reward_points_award_button_disappears_without_create_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.reward-points.index'))->assertOk()->assertSee('إضافة نقاط (ربح / خسارة)');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'reward_points.create');

        $this->actingAs($manager)->get(route('admin.reward-points.index'))->assertOk()->assertDontSee('إضافة نقاط (ربح / خسارة)');
    }

    public function test_announcement_publish_button_disappears_without_create_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        Announcement::create([
            'tenant_id' => $mosque->id,
            'title' => 'إعلان',
            'body' => 'نص',
            'audience' => 'all',
            'published_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('admin.announcements.index'))->assertOk()->assertSee('>نشر<', false);

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'announcements.create');

        $this->actingAs($manager)->get(route('admin.announcements.index'))->assertOk()->assertDontSee('>نشر<', false);
    }

    public function test_sidebar_link_disappears_when_its_permission_is_revoked(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()->assertSee('دفعات الحفظ');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'quran_batch.view');

        $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()->assertDontSee('دفعات الحفظ');
    }

    // --------------------------------------------------------------- system

    public function test_quran_settings_save_button_disappears_without_update_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)
            ->get(route('admin.settings.quran.edit'))
            ->assertOk()
            ->assertSee('حفظ الإعدادات');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'quran_settings.update');

        $this->actingAs($manager)
            ->get(route('admin.settings.quran.edit'))
            ->assertOk()
            ->assertDontSee('حفظ الإعدادات');
    }

    public function test_work_hours_settings_save_button_disappears_without_manage_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)
            ->get(route('admin.settings.work-hours.edit'))
            ->assertOk()
            ->assertSee('حفظ الإعدادات');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'work_hours.manage');

        $this->actingAs($manager)
            ->get(route('admin.settings.work-hours.edit'))
            ->assertOk()
            ->assertDontSee('حفظ الإعدادات');
    }

    public function test_reward_settings_save_button_disappears_without_update_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        StudySession::create(['tenant_id' => $mosque->id, 'name' => 'الدوام الأول', 'is_active' => true]);

        $this->actingAs($manager)
            ->get(route('admin.settings.rewards.edit'))
            ->assertOk()
            ->assertSee('حفظ إعدادات النقاط');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'quran_settings.update');

        $this->actingAs($manager)
            ->get(route('admin.settings.rewards.edit'))
            ->assertOk()
            ->assertDontSee('حفظ إعدادات النقاط');
    }

    public function test_payroll_close_button_disappears_without_close_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.payroll.index'))->assertOk()->assertSee('قفل الشهر');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'payroll.close');

        $this->actingAs($manager)->get(route('admin.payroll.index'))->assertOk()->assertDontSee('قفل الشهر');
    }

    // ------------------------------------------------------- user override

    public function test_per_user_deny_override_hides_the_button_even_when_the_role_grants_it(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.students.index'))->assertOk()->assertSee('إضافة طالب');

        app(RoleService::class)->syncUserPermissions($manager, ['students.create' => 'deny']);

        $this->actingAs($manager)->get(route('admin.students.index'))->assertOk()->assertDontSee('إضافة طالب');
        $this->actingAs($manager)->post(route('admin.students.store'), [])->assertForbidden();
    }

    // ------------------------------------------------- classrooms / sections

    public function test_classroom_dashboard_buttons_follow_their_permissions(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);

        $this->actingAs($manager)
            ->get(route('admin.classrooms.show', $classroom))
            ->assertOk()
            ->assertSee('إضافة شعبة')
            ->assertSee('تعديل الصف');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'sections.create');
        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'classes.update');

        $this->actingAs($manager)
            ->get(route('admin.classrooms.show', $classroom))
            ->assertOk()
            ->assertDontSee('إضافة شعبة')
            ->assertDontSee('تعديل الصف');
    }

    public function test_section_dashboard_teacher_assignment_disappears_without_update_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);

        $this->actingAs($manager)
            ->get(route('admin.sections.show', $section))
            ->assertOk()
            ->assertSee('تكليف المعلم');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'sections.update');

        $this->actingAs($manager)
            ->get(route('admin.sections.show', $section))
            ->assertOk()
            ->assertDontSee('تكليف المعلم');
    }

    public function test_sharia_course_lesson_form_disappears_without_update_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة الفقه',
            'status' => 'active',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.sharia-courses.show', $course))
            ->assertOk()
            ->assertSee('إضافة درس / محاضرة');

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, 'sharia_courses.update');

        $this->actingAs($manager)
            ->get(route('admin.sharia-courses.show', $course))
            ->assertOk()
            ->assertDontSee('إضافة درس / محاضرة');
    }
}
