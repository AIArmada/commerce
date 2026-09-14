<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;

final class WildcardPermissionCache
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissions = [];

    /**
     * @var array<string, bool>
     */
    private array $superAdminVerdicts = [];

    /**
     * @return list<string>
     */
    public function get(object $user): array
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return [];
        }

        $key = $this->getCacheKey($user);

        if (isset($this->permissions[$key])) {
            return $this->permissions[$key];
        }

        /** @var array<int, mixed> $permissionNames */
        $permissionNames = $user->getAllPermissions()->pluck('name')->all();
        $wildcardPermissions = [];

        foreach ($permissionNames as $permissionName) {
            if (is_string($permissionName) && str_contains($permissionName, '*')) {
                $wildcardPermissions[] = $permissionName;
            }
        }

        return $this->permissions[$key] = $wildcardPermissions;
    }

    /**
     * Memoize a global super-admin verdict per user+role for the request.
     *
     * The check always runs with a null team id, so the team is not part of
     * the key.
     *
     * @param  Closure():bool  $check
     */
    public function rememberSuperAdmin(object $user, string $role, Closure $check): bool
    {
        $identifier = method_exists($user, 'getAuthIdentifier')
            ? $user->getAuthIdentifier()
            : null;

        if ($identifier === null) {
            $identifier = spl_object_id($user);
        }

        $key = implode('|', [$user::class, (string) $identifier, $role]);

        if (! array_key_exists($key, $this->superAdminVerdicts)) {
            $this->superAdminVerdicts[$key] = (bool) $check();
        }

        return $this->superAdminVerdicts[$key];
    }

    public function clear(): void
    {
        $this->permissions = [];
        $this->superAdminVerdicts = [];
    }

    private function getCacheKey(object $user): string
    {
        $identifier = method_exists($user, 'getAuthIdentifier')
            ? $user->getAuthIdentifier()
            : null;

        if ($identifier === null) {
            $identifier = spl_object_id($user);
        }

        $registrar = app(PermissionRegistrar::class);
        $team = $registrar->teams
            ? 'team:' . (string) $registrar->getPermissionsTeamId()
            : 'global';
        $guard = $user instanceof Model
            ? Guard::getDefaultName($user)
            : '';

        return implode('|', [
            $user::class,
            (string) $identifier,
            $team,
            $guard,
        ]);
    }
}
