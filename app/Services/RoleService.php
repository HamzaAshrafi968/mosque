<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Role & permission provisioning (catalog seeding, per-mosque default roles,
 * role assignment). Kept separate from authorization checks (AuthorizationService).
 */
class RoleService
{
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_MOSQUE_MANAGER = 'mosque_manager';

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_GUARDIAN = 'guardian';

    public const ROLE_STUDENT = 'student';

    /**
     * Idempotent: make sure every catalog permission exists in the DB.
     */
    public function ensurePermissionCatalog(): void
    {
        // Cheap existence probe keeps test seeding fast and correct.
        if (Permission::query()->where('code', PermissionCatalog::codes()[0] ?? '')->exists()) {
            return;
        }

        foreach (PermissionCatalog::rows() as $code => $row) {
            Permission::updateOrCreate(
                ['code' => $code],
                ['resource' => $row['resource'], 'action' => $row['action'], 'label' => $row['label']]
            );
        }
    }

    /**
     * Create the global "مدير الجوامع" role (above all mosques) with every
     * permission granted at the global scope. Idempotent.
     */
    public function ensureGlobalSuperAdminRole(): Role
    {
        $this->ensurePermissionCatalog();

        $role = Role::firstOrCreate(
            ['code' => self::ROLE_SUPER_ADMIN, 'tenant_id' => null],
            [
                'name' => 'مدير الجوامع',
                'description' => 'صلاحيات كاملة على جميع الجوامع',
                'is_system' => true,
            ]
        );

        $this->syncRolePermissions($role, array_fill_keys(PermissionCatalog::codes(), 'global'));

        return $role;
    }

    /**
     * Create the default per-mosque roles (mosque_manager, teacher) if missing.
     */
    public function provisionTenantRoles(Tenant $tenant): void
    {
        $this->ensurePermissionCatalog();

        $definitions = [
            [
                'code' => self::ROLE_MOSQUE_MANAGER,
                'name' => 'مدير الجامع',
                'description' => 'يدير بيانات جامعه: الطلاب والأساتذة والصفوف والجداول والدرجات',
                'grants' => PermissionCatalog::MOSQUE_MANAGER,
            ],
            [
                'code' => self::ROLE_TEACHER,
                'name' => 'أستاذ',
                'description' => 'يدير صفوفه وطلابه والحضور والدرجات والواجبات والدروس',
                'grants' => PermissionCatalog::TEACHER,
            ],
            [
                'code' => self::ROLE_GUARDIAN,
                'name' => 'ولي أمر',
                'description' => 'يطلع على بيانات أبنائه فقط من خلال بوابة ولي الأمر',
                'grants' => PermissionCatalog::GUARDIAN,
            ],
            [
                'code' => self::ROLE_STUDENT,
                'name' => 'طالب',
                'description' => 'يطلع على بياناته الأكاديمية فقط من خلال بوابة الطالب',
                'grants' => PermissionCatalog::STUDENT,
            ],
        ];

        foreach ($definitions as $definition) {
            $role = Role::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ]
            );

            if ($role->wasRecentlyCreated) {
                $this->syncRolePermissions($role, $definition['grants']);
            }
        }
    }

    /**
     * Replace all permissions of a role. Accepts a [code => scope] map.
     */
    public function syncRolePermissions(Role $role, array $grants): void
    {
        $rows = [];

        foreach ($grants as $code => $scope) {
            $permission = Permission::where('code', $code)->first();

            if (! $permission) {
                continue;
            }

            $rows[] = [
                'permission_id' => $permission->id,
                'role_id' => $role->id,
                'scope' => $scope,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($role, $rows) {
            $role->permissions()->detach();

            $pivot = $role->permissions()->newPivotStatement();

            foreach (array_chunk($rows, 200) as $chunk) {
                $pivot->insert($chunk);
            }
        });
    }

    /**
     * Attach a role (by code) to a user. Lazily provisions the tenant default
     * roles & the global role when needed so the system always has valid roles.
     */
    public function assignRole(User $user, string $code): void
    {
        $role = $this->resolveRole($user, $code);

        // Lazily provision the tenant's default roles when a role is missing
        // (e.g. existing mosques created before a role type existed).
        if (! $role && $user->tenant_id !== null) {
            if ($tenant = Tenant::find($user->tenant_id)) {
                $this->provisionTenantRoles($tenant);
            }

            $role = $this->resolveRole($user, $code);
        }

        if ($role && ! $user->roles()->where('roles.code', $code)->exists()) {
            $user->roles()->attach($role->id);
        }
    }

    public function removeRole(User $user, string $code): void
    {
        $user->roles()->where('roles.code', $code)->get()->each(fn (Role $role) => $user->roles()->detach($role->id));
    }

    /**
     * Called on user creation: sync the legacy users.role string to a role row.
     */
    public static function attachDefaultFor(User $user): void
    {
        $code = match ($user->role) {
            User::ROLE_ADMIN => self::ROLE_MOSQUE_MANAGER,
            User::ROLE_SUPER_ADMIN => self::ROLE_SUPER_ADMIN,
            User::ROLE_GUARDIAN => self::ROLE_GUARDIAN,
            User::ROLE_STUDENT => self::ROLE_STUDENT,
            default => self::ROLE_TEACHER,
        };

        $service = app(self::class);
        $service->ensurePermissionCatalog();

        if ($user->tenant_id === null) {
            $service->ensureGlobalSuperAdminRole();
        } elseif ($tenant = Tenant::find($user->tenant_id)) {
            $service->provisionTenantRoles($tenant);
        }

        $service->assignRole($user, $code);
    }

    private function resolveRole(User $user, string $code): ?Role
    {
        if ($user->tenant_id === null) {
            return Role::where('code', $code)
                ->whereNull('tenant_id')
                ->first();
        }

        return Role::where('tenant_id', $user->tenant_id)
            ->where('code', $code)
            ->first();
    }
}
