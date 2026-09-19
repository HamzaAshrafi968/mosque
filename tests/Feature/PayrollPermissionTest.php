<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مصفوفة صلاحيات كشوف الرواتب: مدير الجامع (mosque) والأستاذ (own)،
 * والمنح الافتراضية والمنح الخلفية.
 */
class PayrollPermissionTest extends TestCase
{
    use RefreshDatabase;

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

    private function detachFromRole(Tenant $mosque, string $roleCode, array $codes): void
    {
        $permissions = Permission::query()->whereIn('code', $codes)->pluck('id');

        Role::query()
            ->where('tenant_id', $mosque->id)
            ->where('code', $roleCode)
            ->firstOrFail()
            ->permissions()
            ->detach($permissions->all());
    }

    public function test_provisioned_manager_gets_the_payroll_permissions(): void
    {
        $mosque = $this->mosque();

        $role = Role::query()
            ->where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail();

        foreach (['hourly_rates.manage', 'payroll.view', 'payroll.manage', 'payroll.pay', 'payroll.close', 'payroll.reopen'] as $code) {
            $this->assertTrue(
                $role->permissions()->where('permissions.code', $code)->exists(),
                "manager is missing {$code}"
            );
        }
    }

    public function test_provisioned_teacher_gets_own_payroll_view_only(): void
    {
        $mosque = $this->mosque();

        $role = Role::query()
            ->where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_TEACHER)
            ->firstOrFail();

        $grant = $role->permissions()->where('permissions.code', 'payroll.view')->first();

        $this->assertNotNull($grant);
        $this->assertSame('own', $grant->pivot->scope);
        $this->assertFalse($role->permissions()->where('permissions.code', 'payroll.pay')->exists());
    }

    public function test_admin_payroll_index_accepts_payroll_view_without_finance_view(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->detachFromRole($mosque, RoleService::ROLE_MOSQUE_MANAGER, ['finance.view']);

        $this->actingAs($manager)->get(route('admin.payroll.index'))->assertOk();
    }

    public function test_admin_payroll_index_is_forbidden_without_any_view_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->detachFromRole($mosque, RoleService::ROLE_MOSQUE_MANAGER, ['finance.view', 'payroll.view']);

        $this->actingAs($manager)->get(route('admin.payroll.index'))->assertForbidden();
    }

    public function test_recording_a_payment_follows_payroll_pay_or_finance_create(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), ['amount' => 100])
            ->assertRedirect();

        $this->detachFromRole($mosque, RoleService::ROLE_MOSQUE_MANAGER, ['finance.create', 'payroll.pay']);

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), ['amount' => 100])
            ->assertForbidden();
    }

    public function test_closing_requires_the_close_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.payroll.close', $teacher), ['month' => '2026-09'])
            ->assertRedirect();

        $this->detachFromRole($mosque, RoleService::ROLE_MOSQUE_MANAGER, ['payroll.close']);

        $this->actingAs($manager)
            ->post(route('admin.payroll.close', $teacher), ['month' => '2026-09'])
            ->assertForbidden();
    }

    public function test_teacher_portal_payroll_follows_the_view_permission(): void
    {
        $mosque = $this->mosque();
        [$teacherUser] = $this->teacher($mosque);

        $this->actingAs($teacherUser)->get(route('teacher.payroll.index'))->assertOk();

        $this->detachFromRole($mosque, RoleService::ROLE_TEACHER, ['payroll.view']);

        $this->actingAs($teacherUser)->get(route('teacher.payroll.index'))->assertForbidden();
    }

    public function test_teacher_cannot_open_the_admin_payroll_area(): void
    {
        $mosque = $this->mosque();
        [$teacherUser] = $this->teacher($mosque);

        $this->actingAs($teacherUser)->get(route('admin.payroll.index'))->assertForbidden();
    }

    public function test_merged_my_payroll_page_still_renders_without_payroll_permission(): void
    {
        $mosque = $this->mosque();
        [$teacherUser] = $this->teacher($mosque);

        $this->detachFromRole($mosque, RoleService::ROLE_TEACHER, ['payroll.view']);

        $this->actingAs($teacherUser)->get(route('teacher.timesheet.index'))->assertOk();
    }
}
