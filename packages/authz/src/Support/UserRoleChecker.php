<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final class UserRoleChecker
{
    public static function hasRole(mixed $user, string $role): bool
    {
        return is_object($user)
            && method_exists($user, 'hasRole')
            && (bool) $user->hasRole($role);
    }

    /**
     * Check a role assignment without the active team/scope filter.
     *
     * This is intentionally distinct from hasRole(): a global super-admin
     * assignment must remain global when the current request is team-scoped.
     */
    public static function hasGlobalRole(mixed $user, string $role): bool
    {
        if (! is_object($user) || ! method_exists($user, 'hasRole')) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams) {
            return (bool) $user->hasRole($role);
        }

        // Drop team scoping entirely for the check: a global assignment may
        // itself carry a team id (single-tenant seeders scope everything to
        // the owner), so filtering by "team null" would miss it. This mirrors
        // FilamentPermission::isSuperAdmin, keeping Gate and UI verdicts
        // coherent.
        $teams = $registrar->teams;
        $registrar->teams = false;

        $hadLoadedRoles = $user instanceof Model && $user->relationLoaded('roles');
        $loadedRoles = $hadLoadedRoles ? $user->getRelation('roles') : null;

        if ($user instanceof Model) {
            $user->unsetRelation('roles');
        }

        try {
            return (bool) $user->hasRole($role);
        } finally {
            $registrar->teams = $teams;

            if ($user instanceof Model) {
                $user->unsetRelation('roles');

                if ($hadLoadedRoles) {
                    $user->setRelation('roles', $loadedRoles);
                }
            }
        }
    }
}
