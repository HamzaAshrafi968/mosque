<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the finance permissions added to the teacher default matrix.
 * Only missing grants are attached; custom role edits stay untouched.
 */
return new class extends Migration
{
    private const GRANTS = [
        'finance.view' => 'own',
        'finance.create' => 'own',
        'finance.adjust' => 'own',
        'finance.transfer' => 'own',
    ];

    public function up(): void
    {
        app(RoleService::class)->ensurePermissionCatalog();

        Role::where('code', RoleService::ROLE_TEACHER)->each(function (Role $role) {
            foreach (self::GRANTS as $code => $scope) {
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
        // Grants are not rolled back: revoking them could lock users out.
    }
};
