<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\ParentStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Tests\TestCase;

/**
 * تعطيل بوابتي الطالب وولي الأمر: تسجيل الدخول يعمل، لكن كل صفحات البوابة
 * تحوّل إلى صفحة «البوابة معطّلة»، وبقيت الأدوار الأخرى والإشعارات كما هي.
 */
class PortalDisabledTest extends TestCase
{
    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    /** @return array{0: User, 1: Student} */
    private function guardianFamily(Tenant $mosque): array
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_GUARDIAN]);
        $guardian = Guardian::create(['tenant_id' => $mosque->id, 'user_id' => $user->id, 'name' => 'ولي الأمر']);
        $child = Student::factory()->create(['tenant_id' => $mosque->id, 'status' => 'active']);

        ParentStudent::create([
            'tenant_id' => $mosque->id,
            'parent_id' => $guardian->id,
            'student_id' => $child->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        return [$user, $child];
    }

    private function studentUser(Tenant $mosque): User
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_STUDENT]);
        Student::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    private function teacherUser(Tenant $mosque): User
    {
        $user = User::factory()->for($mosque)->create();
        Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        return $user;
    }

    public function test_login_lands_portal_accounts_on_the_disabled_page(): void
    {
        $mosque = $this->mosque();
        [$guardianUser] = $this->guardianFamily($mosque);
        $studentUser = $this->studentUser($mosque);

        $this->post(route('login.store'), ['email' => $guardianUser->email, 'password' => 'password'])
            ->assertRedirect(route('portal.disabled'));

        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $studentUser->email, 'password' => 'password'])
            ->assertRedirect(route('portal.disabled'));
    }

    public function test_root_redirects_portal_accounts_to_the_disabled_page(): void
    {
        $mosque = $this->mosque();
        [$guardianUser] = $this->guardianFamily($mosque);
        $studentUser = $this->studentUser($mosque);

        $this->actingAs($guardianUser)->get('/')->assertRedirect(route('portal.disabled'));
        $this->actingAs($studentUser)->get('/')->assertRedirect(route('portal.disabled'));
    }

    public function test_disabled_page_renders_for_portal_accounts(): void
    {
        $mosque = $this->mosque();
        [$guardianUser] = $this->guardianFamily($mosque);
        $studentUser = $this->studentUser($mosque);

        $this->actingAs($guardianUser)->get(route('portal.disabled'))
            ->assertOk()
            ->assertSee('البوابة معطّلة')
            ->assertSee('بوابة ولي الأمر');

        $this->actingAs($studentUser)->get(route('portal.disabled'))
            ->assertOk()
            ->assertSee('البوابة معطّلة')
            ->assertSee('بوابة الطالب');
    }

    public function test_all_guardian_portal_routes_redirect_to_the_disabled_page(): void
    {
        $mosque = $this->mosque();
        [$guardianUser, $child] = $this->guardianFamily($mosque);

        $routes = [
            ['guardian.dashboard', []],
            ['guardian.profile', []],
            ['guardian.children.overview', [$child]],
            ['guardian.children.attendance', [$child]],
            ['guardian.children.subjects', [$child]],
            ['guardian.children.teachers', [$child]],
            ['guardian.children.exams', [$child]],
            ['guardian.children.grades', [$child]],
            ['guardian.children.homeworks', [$child]],
            ['guardian.children.announcements', [$child]],
        ];

        foreach ($routes as [$name, $parameters]) {
            $this->actingAs($guardianUser)
                ->get(route($name, $parameters))
                ->assertRedirect(route('portal.disabled'));
        }
    }

    public function test_all_student_portal_routes_redirect_to_the_disabled_page(): void
    {
        $mosque = $this->mosque();
        $studentUser = $this->studentUser($mosque);

        $routes = [
            'student.dashboard', 'student.profile', 'student.attendance', 'student.subjects',
            'student.teachers', 'student.exams', 'student.grades', 'student.homeworks',
            'student.announcements', 'student.reward-points', 'student.quran-khamsa',
            'student.quran-profile', 'student.quran-listening.index', 'student.quran-programs.index',
        ];

        foreach ($routes as $name) {
            $this->actingAs($studentUser)
                ->get(route($name))
                ->assertRedirect(route('portal.disabled'));
        }
    }

    public function test_notifications_remain_accessible_for_portal_accounts(): void
    {
        $mosque = $this->mosque();
        [$guardianUser] = $this->guardianFamily($mosque);
        $studentUser = $this->studentUser($mosque);

        $this->actingAs($guardianUser)->get(route('notifications.index'))->assertOk();
        $this->actingAs($studentUser)->get(route('notifications.index'))->assertOk();
    }

    public function test_admin_teacher_and_super_admin_are_unaffected(): void
    {
        $mosque = $this->mosque();
        $admin = User::factory()->admin()->for($mosque)->create();
        $teacherUser = $this->teacherUser($mosque);
        $super = User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($teacherUser)->get(route('teacher.dashboard'))->assertOk();
        $this->actingAs($super)->get(route('super-admin.dashboard'))->assertOk();
    }

    public function test_non_portal_roles_still_get_403_on_portal_routes(): void
    {
        $mosque = $this->mosque();
        $admin = User::factory()->admin()->for($mosque)->create();
        $teacherUser = $this->teacherUser($mosque);

        $this->actingAs($admin)->get(route('student.dashboard'))->assertForbidden();
        $this->actingAs($teacherUser)->get(route('guardian.dashboard'))->assertForbidden();
    }
}
