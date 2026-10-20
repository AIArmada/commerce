<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Reads the current wildcard permission names for a user.
 *
 * The read deliberately discards previously loaded permission and role
 * relations so a grant, revocation, role change, role permission sync, or
 * super-admin change is reflected on the next check, including on user
 * instances that were loaded before the mutation.
 */
final class UserWildcardPermissions
{
    /**
     * @return list<string>
     */
    public static function for(object $user): array
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return [];
        }

        if ($user instanceof Model) {
            $user->unsetRelation('permissions');
            $user->unsetRelation('roles');
        }

        $wildcards = [];

        foreach ($user->getAllPermissions() as $permission) {
            $name = is_object($permission) ? ($permission->name ?? null) : null;

            if (is_string($name) && str_contains($name, '*')) {
                $wildcards[] = $name;
            }
        }

        return $wildcards;
    }
}
