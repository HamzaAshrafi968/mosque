<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\RoleService;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    private function mosqueWithManager(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $manager];
    }

    public function test_mosque_manager_has_default_permissions_in_own_mosque(): void
    {
        [$mosque, $manager] = $this->mosqueWithManager();
        $auth = app(AuthorizationService::class);
        $student = Student::factory()->create(['tenant_id' => $mosque->id]);

        $this->assertTrue($auth->can($manager, 'students.view', $student));
        $this->assertTrue($auth->can($manager, 'students.create'));
        $this->assertTrue($auth->can($manager, 'students.delete', $student));
        $this->assertTrue($auth->can($manager, 'schedule.approve'));
        $this->assertTrue($auth->can($manager, 'grades.approve'));
    }

    public function test_teacher_has_limited_own_scope_permissions(): void
    {
        [$mosque] = $this->mosqueWithManager();
        $teacher = User::factory()->for($mosque)->create();
        $auth = app(AuthorizationService::class);

        $this->assertTrue($auth->can($teacher, 'students.view'));
        $this->assertTrue($auth->can($teacher, 'attendance.create'));
        $this->assertTrue($auth->can($teacher, 'grades.submit'));
        $this->assertFalse($auth->can($teacher, 'students.delete'));
        $this->assertFalse($auth->can($teacher, 'students.create'));
        $this->assertFalse($auth->can($teacher, 'grades.approve'));
        $this->assertFalse($auth->can($teacher, 'users.create'));
    }

    public function test_mosque_manager_cannot_manage_another_mosque(): void
    {
        [$mosqueA, $managerA] = $this->mosqueWithManager();

        $mosqueB = Tenant::factory()->create();
        $studentB = Student::factory()->create(['tenant_id' => $mosqueB->id]);

        $auth = app(AuthorizationService::class);

        $this->assertFalse($auth->can($managerA, 'students.view', $studentB));
        $this->assertFalse($auth->can($managerA, 'students.delete', $studentB));
    }

    public function test_super_admin_can_access_all_mosques(): void
    {
        $roles = app(RoleService::class);
        $roles->ensureGlobalSuperAdminRole();

        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
        $roles->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        $mosqueA = Tenant::factory()->create();
        $mosqueB = Tenant::factory()->create();
        $studentA = Student::factory()->create(['tenant_id' => $mosqueA->id]);
        $studentB = Student::factory()->create(['tenant_id' => $mosqueB->id]);

        $auth = app(AuthorizationService::class);

        $this->assertTrue($auth->can($superAdmin, 'students.delete', $studentA));
        $this->assertTrue($auth->can($superAdmin, 'students.delete', $studentB));
        $this->assertTrue($auth->can($superAdmin, 'mosques.create'));
        $this->assertTrue($auth->can($superAdmin, 'permissions.manage'));
    }

    public function test_role_grants_are_updated_by_permission_matrix_sync(): void
    {
        [$mosque, $manager] = $this->mosqueWithManager();
        $auth = app(AuthorizationService::class);

        $this->assertTrue($auth->can($manager, 'grades.approve'));

        $role = Role::where('tenant_id', $mosque->id)->where('code', 'mosque_manager')->first();
        app(RoleService::class)->syncRolePermissions($role, [
            'students.view' => 'mosque',
            'attendance.create' => 'own',
        ]);

        $this->assertTrue($auth->can($manager, 'students.view'));
        $this->assertTrue($auth->can($manager, 'attendance.create'));
        $this->assertFalse($auth->can($manager, 'grades.approve'));
        $this->assertFalse($auth->can($manager, 'students.delete'));
    }

    public function test_super_admin_dashboard_is_forbidden_for_mosque_managers(): void
    {
        [, $manager] = $this->mosqueWithManager();

        $this->actingAs($manager)->get('/super-admin/dashboard')->assertForbidden();
    }

    public function test_super_admin_can_enter_mosque_and_access_its_admin_panel(): void
    {
        $roles = app(RoleService::class);
        $roles->ensureGlobalSuperAdminRole();

        [$mosque] = $this->mosqueWithManager();

        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
        $roles->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->post("/super-admin/mosques/{$mosque->id}/enter")
            ->assertRedirect(route('admin.dashboard'));

        $this->assertEquals($mosque->id, session('super_admin_mosque_id'));

        $this->get(route('admin.dashboard'))->assertOk();

        $this->post(route('super-admin.exit'))->assertRedirect(route('super-admin.dashboard'));
        $this->assertNull(session('super_admin_mosque_id'));

        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_super_admin_can_switch_mosque_from_header_switcher(): void
    {
        $roles = app(RoleService::class);
        $roles->ensureGlobalSuperAdminRole();

        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
        $roles->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        [$mosque] = $this->mosqueWithManager();
        $otherMosque = Tenant::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('super-admin.switch-mosque'), ['mosque_id' => $mosque->id])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertEquals($mosque->id, session('super_admin_mosque_id'));
        $this->get(route('admin.dashboard'))->assertOk();

        $this->post(route('super-admin.switch-mosque'), ['mosque_id' => $otherMosque->id])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertEquals($otherMosque->id, session('super_admin_mosque_id'));

        $this->post(route('super-admin.switch-mosque'), ['mosque_id' => null])
            ->assertRedirect(route('super-admin.dashboard'));

        $this->assertNull(session('super_admin_mosque_id'));
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_mosque_manager_cannot_use_mosque_switcher(): void
    {
        [, $manager] = $this->mosqueWithManager();

        $this->actingAs($manager)
            ->post(route('super-admin.switch-mosque'), ['mosque_id' => null])
            ->assertForbidden();
    }

    public function test_super_admin_dashboard_shows_mosque_switcher(): void
    {
        $roles = app(RoleService::class);
        $roles->ensureGlobalSuperAdminRole();

        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
        $roles->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        Tenant::factory()->create(['name' => 'جامع الاختبار']);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertSee('mosque-switcher-button')
            ->assertSee('جامع الاختبار');
    }
}
