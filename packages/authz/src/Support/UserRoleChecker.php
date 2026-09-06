<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContextTeamResolver;
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
        $originalOwner = OwnerContext::resolve();
        $hadLoadedRoles = $user instanceof Model && $user->relationLoaded('roles');
        $loadedRoles = $hadLoadedRoles ? $user->getRelation('roles') : null;

        if ($user instanceof Model) {
            $user->unsetRelation('roles');
        }

        try {
            $registrar->setPermissionsTeamId(null);

            return (bool) $user->hasRole($role);
        } finally {
            $teamResolverClass = config('permission.team_resolver');
            $preservesOwnerType = is_string($teamResolverClass)
                && is_a($teamResolverClass, OwnerContextTeamResolver::class, true);

            $registrar->setPermissionsTeamId(
                $preservesOwnerType
                    ? $originalOwner
                    : $originalTeamId,
            );

            if ($user instanceof Model) {
                $user->unsetRelation('roles');

                if ($hadLoadedRoles) {
                    $user->setRelation('roles', $loadedRoles);
                }
            }
        }
    }
}
