<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the permission grants introduced with the full route/API
 * enforcement: parents, quran_review and reward_points. Existing system roles
 * keep access to the features they already had before the new permission
 * codes were added; custom roles stay untouched.
 */
return new class extends Migration
{
    private const TEACHER_GRANTS = [
        'quran_review.view' => 'own',
        'quran_review.create' => 'own',
        'reward_points.view' => 'own',
        'reward_points.create' => 'own',
        'reward_points.delete' => 'own',
    ];

    private const MANAGER_GRANTS = [
        'parents.view' => 'mosque',
        'parents.create' => 'mosque',
        'parents.update' => 'mosque',
        'parents.delete' => 'mosque',
        'quran_review.view' => 'mosque',
        'quran_review.create' => 'mosque',
        'reward_points.view' => 'mosque',
        'reward_points.create' => 'mosque',
        'reward_points.delete' => 'mosque',
    ];

    public function up(): void
    {
        app(RoleService::class)->ensurePermissionCatalog();

        $this->grant(RoleService::ROLE_TEACHER, self::TEACHER_GRANTS);
        $this->grant(RoleService::ROLE_MOSQUE_MANAGER, self::MANAGER_GRANTS);
    }

    public function down(): void
    {
        // Grants are not rolled back: revoking them could lock users out.
    }

    /** @param array<string, string> $grants code => scope */
    private function grant(string $roleCode, array $grants): void
    {
        Role::where('code', $roleCode)->each(function (Role $role) use ($grants) {
            foreach ($grants as $code => $scope) {
                $permission = Permission::where('code', $code)->first();

                if (! $permission) {
                    continue;
                }

                $exists = $role->permissions()->where('permissions.code', $code)->exists();

                if (! $exists) {
                    $role->permissions()->attach($permission->id, ['scope' => $scope]);
                }
            }
        });
    }
};
