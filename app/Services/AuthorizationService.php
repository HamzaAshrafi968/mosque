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
     * @return array<int, string>
     */
    public function scopesFor(User $user, string $permission): array
    {
        return $user->roles()
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permissions.code', $permission)
            ->pluck('permission_role.scope')
            ->unique()
            ->values()
            ->all();
    }

    public function userHasRoleCode(User $user, string $code): bool
    {
        return $user->roles()->where('roles.code', $code)->exists();
    }
}
