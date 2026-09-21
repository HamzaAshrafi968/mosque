<?php

namespace App\Services;

use App\Enums\RoleScope;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Central authorization layer:  can(user, permission, resource)
 *
 * Resolution order (spec §50):
 *   1. authentication (caller must pass a User)
 *   2. permission (via role grants)
 *   3. scope (global / mosque / class / section / own)
 *   4. mosque ownership
 *   5. resource ownership (optional $owns closure for 'own' scope)
 */
class AuthorizationService
{
    /**
     * ذاكرة مؤقتة على مستوى الطلب: أدوار وصلاحيات كل مستخدم تُحمَّل مرة
     * واحدة (استعلامان) بدل 2-3 استعلامات لكل نداء can() — الواجهة تنادي
     * can() عشرات المرات في كل صفحة.
     *
     * @var array<string, bool>
     */
    private array $superAdminMemo = [];

    /** @var array<string, array{overrides: array<string, array<int, array{effect: ?string, scope: ?string}>>, roles: array<string, array<int, ?string>>}> */
    private array $permissionMemo = [];

    /**
     * تُفرَّغ في بداية كل طلب (InitializeTenant) وعند أي تغيير على الأدوار
     * أو الصلاحيات (RoleService) فتبقى المنح/السحب فورية.
     */
    public function flushMemo(): void
    {
        $this->superAdminMemo = [];
        $this->permissionMemo = [];
    }

    /** True when the user holds a role granting this permission at any scope. */
    public function hasPermission(User $user, string $permission): bool
    {
        return $this->scopesFor($user, $permission) !== [];
    }

    /**
     * @param  Model|null  $subject  the resource being accessed, when applicable
     * @param  Closure|null  $owns  predicate for the 'own' scope: fn(User, ?Model) => bool
     */
    public function can(User $user, string $permission, ?Model $subject = null, ?Closure $owns = null): bool
    {
        // مدير الجوامع holds every permission above all mosques.
        if ($user->isSuperAdmin() || $this->userHasRoleCode($user, RoleService::ROLE_SUPER_ADMIN)) {
            return true;
        }

        // Users without a mosque cannot hold per-mosque grants.
        if ($user->tenant_id === null) {
            return false;
        }

        $scopes = $this->scopesFor($user, $permission);

        if ($scopes === []) {
            return false;
        }

        // A global-scope grant bypasses mosque restrictions.
        if (in_array(RoleScope::Global->value, $scopes, true)) {
            return true;
        }

        // Mosque isolation: the subject must belong to the user's mosque.
        if ($subject instanceof Model) {
            $subjectTenant = $subject->getAttribute('tenant_id');

            if ($subjectTenant !== null && (string) $subjectTenant !== (string) $user->tenant_id) {
                return false;
            }
        }

        if (in_array(RoleScope::Mosque->value, $scopes, true)) {
            return true;
        }

        if (in_array(RoleScope::Own->value, $scopes, true)) {
            if ($owns !== null) {
                return (bool) $owns($user, $subject);
            }

            // Without a custom predicate, "own" at least still enforces mosque isolation.
            return true;
        }

        // class / section scopes behave like mosque scope unless a richer
        // predicate is provided (used by the role editor for future binding).
        return $owns !== null ? (bool) $owns($user, $subject) : true;
    }

    /** The user may act if they hold ANY of the listed permissions. */
    public function canAny(User $user, array $permissions, ?Model $subject = null, ?Closure $owns = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($user, $permission, $subject, $owns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scopes granted for a permission across all roles of the user.
     *
     * Direct user overrides win over every role grant: an explicit deny
     * revokes the permission, an allow replaces the role scopes.
     *
     * @return array<int, string>
     */
    public function scopesFor(User $user, string $permission): array
    {
        $memo = $this->memoFor($user);

        $overrides = $memo['overrides'][$permission] ?? [];

        if ($overrides !== []) {
            foreach ($overrides as $override) {
                if ($override['effect'] === 'deny') {
                    return [];
                }
            }

            return array_values(array_unique(array_filter(
                array_column($overrides, 'scope'),
                fn ($scope) => $scope !== null && $scope !== ''
            )));
        }

        return array_values(array_unique(array_filter(
            $memo['roles'][$permission] ?? [],
            fn ($scope) => $scope !== null && $scope !== ''
        )));
    }

    /**
     * تحميل كل صلاحيات المستخدم (التجاوزات المباشرة + منح الأدوار) مرة واحدة
     * لكل مستخدم في الطلب الواحد.
     *
     * @return array{overrides: array<string, array<int, array{effect: ?string, scope: ?string}>>, roles: array<string, array<int, ?string>>}
     */
    private function memoFor(User $user): array
    {
        $id = (string) $user->getKey();

        if (! isset($this->permissionMemo[$id])) {
            $overrides = [];

            foreach ($user->permissions()->get(['permissions.id', 'permissions.code']) as $permission) {
                $overrides[$permission->code][] = [
                    'effect' => $permission->pivot->effect,
                    'scope' => $permission->pivot->scope,
                ];
            }

            $roles = [];

            foreach ($user->roles()->with('permissions')->get() as $role) {
                foreach ($role->permissions as $permission) {
                    $roles[$permission->code][] = $permission->pivot->scope;
                }
            }

            $this->permissionMemo[$id] = ['overrides' => $overrides, 'roles' => $roles];
        }

        return $this->permissionMemo[$id];
    }

    public function userHasRoleCode(User $user, string $code): bool
    {
        $id = (string) $user->getKey();

        if ($code === RoleService::ROLE_SUPER_ADMIN && isset($this->superAdminMemo[$id])) {
            return $this->superAdminMemo[$id];
        }

        $exists = $user->roles()->where('roles.code', $code)->exists();

        if ($code === RoleService::ROLE_SUPER_ADMIN) {
            $this->superAdminMemo[$id] = $exists;
        }

        return $exists;
    }
}
