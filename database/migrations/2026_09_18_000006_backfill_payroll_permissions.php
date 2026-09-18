<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * منح صلاحيات كشوف الرواتب وأسعار الساعة للأدوار القائمة:
 * مدير الجامع (mosque) لكل الصلاحيات، والأستاذ (own) لمشاهدة كشوفه فقط.
 */
return new class extends Migration
{
    private const MANAGER_GRANTS = [
        'hourly_rates.manage' => 'mosque',
        'payroll.view' => 'mosque',
        'payroll.manage' => 'mosque',
        'payroll.pay' => 'mosque',
        'payroll.close' => 'mosque',
        'payroll.reopen' => 'mosque',
    ];

    private const TEACHER_GRANTS = [
        'payroll.view' => 'own',
    ];

    public function up(): void
    {
        app(RoleService::class)->ensurePermissionCatalog();

        $this->grant(RoleService::ROLE_MOSQUE_MANAGER, self::MANAGER_GRANTS);
        $this->grant(RoleService::ROLE_TEACHER, self::TEACHER_GRANTS);
    }

    /** @param  array<string, string>  $grants */
    private function grant(string $roleCode, array $grants): void
    {
        Role::where('code', $roleCode)->each(function (Role $role) use ($grants) {
            foreach ($grants as $code => $scope) {
                $permission = Permission::where('code', $code)->first();

                if (! $permission) {
                    continue;
                }

                if (! $role->permissions()->where('permissions.code', $code)->exists()) {
                    $role->permissions()->attach($permission->id, ['scope' => $scope]);
                }
            }
        });
    }

    public function down(): void
    {
        // لا يُسحب المنح عند التراجع حتى لا يُقفل المستخدمون خارج النظام.
    }
};
