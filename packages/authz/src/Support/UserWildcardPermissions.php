<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reads the current wildcard permission names for a user.
 *
 * Eloquent users are cached for the duration of a request (keyed by model
 * identity and permission team) so repeated Gate checks do not re-query the
 * permission tables. The cache is flushed whenever a permission or role
 * assignment is written, and at the start of every Octane request, so a
 * grant, revocation, role change, or super-admin change is still reflected
 * on the next check.
 */
final class UserWildcardPermissions
{
    /**
     * @var array<string, list<string>>
     */
    private static array $cache = [];

    /**
     * @return list<string>
     */
    public static function for(object $user): array
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return [];
        }

        if (! $user instanceof Model) {
            return self::resolve($user);
        }

        $key = self::cacheKey($user);

        if ($key === null) {
            return self::resolve($user);
        }

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        return self::$cache[$key] = self::resolve($user);
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return list<string>
     */
    private static function resolve(object $user): array
    {
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

    private static function cacheKey(Model $user): ?string
    {
        $key = $user->getKey();

        if ($key === null || $key === '') {
            return null;
        }

        $registrar = app(PermissionRegistrar::class);

        return json_encode([
            $user->getConnection()->getName(),
            $user->getConnection()->getDatabaseName(),
            $user->getTable(),
            $user->getMorphClass(),
            $key,
            $registrar->teams,
            $registrar->teams ? $registrar->getPermissionsTeamId() : null,
        ], JSON_THROW_ON_ERROR);
    }
}
