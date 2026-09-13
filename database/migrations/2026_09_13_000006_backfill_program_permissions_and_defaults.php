<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\ProgramService;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the programs.* permissions to existing mosque-manager roles and
 * provision the five default schedule programs for every existing mosque.
 */
return new class extends Migration
{
    private const MANAGER_GRANTS = [
        'programs.view' => 'mosque',
        'programs.create' => 'mosque',
        'programs.update' => 'mosque',
        'programs.delete' => 'mosque',
    ];

    public function up(): void
    {
        $roles = app(RoleService::class);
        $roles->ensurePermissionCatalog();
        $roles->ensureGlobalSuperAdminRole();

        Role::where('code', RoleService::ROLE_MOSQUE_MANAGER)->each(function (Role $role) {
            foreach (self::MANAGER_GRANTS as $code => $scope) {
                $permission = Permission::where('code', $code)->first();

                if ($permission && ! $role->permissions()->where('permissions.code', $code)->exists()) {
                    $role->permissions()->attach($permission->id, ['scope' => $scope]);
                }
            }
        });

        $programs = app(ProgramService::class);

        Tenant::query()->each(fn (Tenant $tenant) => $programs->provisionTenantPrograms($tenant));
    }

    public function down(): void
    {
        // Grants and provisioned programs are not rolled back to avoid data loss.
    }
};
