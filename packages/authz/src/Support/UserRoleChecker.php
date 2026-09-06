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

        $originalTeamId = $registrar->getPermissionsTeamId();
        $hadLoadedRoles = $user instanceof Model && $user->relationLoaded('roles');
        $loadedRoles = $hadLoadedRoles ? $user->getRelation('roles') : null;

        if ($user instanceof Model) {
            $user->unsetRelation('roles');
        }

        try {
            $registrar->setPermissionsTeamId(null);

            return (bool) $user->hasRole($role);
        } finally {
            $registrar->setPermissionsTeamId($originalTeamId);

            if ($user instanceof Model) {
                $user->unsetRelation('roles');

                if ($hadLoadedRoles) {
                    $user->setRelation('roles', $loadedRoles);
                }
            }
        }
    }
}
