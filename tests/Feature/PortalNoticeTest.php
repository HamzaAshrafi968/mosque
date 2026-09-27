<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\ParentStudent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PortalNoticeSettingsService;
use App\Services\RoleService;
use Tests\TestCase;

/**
 * «إعلان بوابة أولياء الأمور»: نص ثابت يكتبه مدير الجامع من مركز الإعدادات
 * ويظهر أعلى الصفحة الرئيسية لبوابة ولي الأمر، مع عزل كل جامع.
 */
class PortalNoticeTest extends TestCase
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

    public function test_manager_saves_notice_and_guardian_dashboard_shows_it(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [$guardianUser] = $this->guardianFamily($mosque);

        $this->actingAs($manager)
            ->get(route('admin.settings.portal-notice.edit'))
            ->assertOk()
            ->assertSee('حفظ الإعلان');

        $this->actingAs($manager)
            ->patch(route('admin.settings.portal-notice.update'), [
                'notice' => "يبدأ التسجيل غداً\nيرجى إحضار الدفتر",
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $mosque->id,
            'key' => PortalNoticeSettingsService::KEY_GUARDIAN_NOTICE,
        ]);

        $this->assertSame(
            "يبدأ التسجيل غداً\nيرجى إحضار الدفتر",
            app(PortalNoticeSettingsService::class)->guardianNotice()
        );

        $this->actingAs($guardianUser)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('إعلان هام')
            ->assertSee('يبدأ التسجيل غداً')
            ->assertSee('يرجى إحضار الدفتر');

        $this->assertDatabaseHas('audit_logs', ['action' => 'portal_notice.updated']);
    }

    public function test_empty_notice_hides_the_banner(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [$guardianUser] = $this->guardianFamily($mosque);

        app(PortalNoticeSettingsService::class)->setGuardianNotice('إعلان مؤقت');

        $this->actingAs($manager)
            ->patch(route('admin.settings.portal-notice.update'), ['notice' => ''])
            ->assertRedirect();

        $this->assertNull(app(PortalNoticeSettingsService::class)->guardianNotice());

        $this->actingAs($guardianUser)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertDontSee('إعلان هام');
    }

    public function test_notice_is_isolated_per_mosque(): void
    {
        $mosqueA = $this->mosque();
        $managerA = $this->manager($mosqueA);
        [$guardianA] = $this->guardianFamily($mosqueA);

        $this->actingAs($managerA)
            ->patch(route('admin.settings.portal-notice.update'), ['notice' => 'إعلان الجامع الأول'])
            ->assertRedirect();

        $mosqueB = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosqueB->id]);
        app(RoleService::class)->provisionTenantRoles($mosqueB);
        [$guardianB] = $this->guardianFamily($mosqueB);

        $this->actingAs($guardianB)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertDontSee('إعلان الجامع الأول');

        $this->actingAs($guardianA)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('إعلان الجامع الأول');
    }

    public function test_teacher_cannot_manage_the_notice(): void
    {
        $mosque = $this->mosque();
        $teacher = User::factory()->for($mosque)->create();

        $this->actingAs($teacher)
            ->get(route('admin.settings.portal-notice.edit'))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->patch(route('admin.settings.portal-notice.update'), ['notice' => 'نص'])
            ->assertForbidden();
    }

    public function test_save_button_and_route_follow_update_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)
            ->get(route('admin.settings.portal-notice.edit'))
            ->assertOk()
            ->assertSee('حفظ الإعلان');

        $permission = Permission::where('code', 'portal_notice.update')->firstOrFail();

        Role::where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail()
            ->permissions()
            ->detach($permission->id);

        $this->actingAs($manager)
            ->get(route('admin.settings.portal-notice.edit'))
            ->assertOk()
            ->assertDontSee('حفظ الإعلان');

        $this->actingAs($manager)
            ->patch(route('admin.settings.portal-notice.update'), ['notice' => 'نص'])
            ->assertForbidden();
    }

    public function test_settings_center_lists_the_notice_tab_and_card(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('إعلان بوابة أولياء الأمور')
            ->assertSee(route('admin.settings.portal-notice.edit'));
    }
}
