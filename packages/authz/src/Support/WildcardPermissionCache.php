<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

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

    public function clear(): void
    {
        $this->permissions = [];
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
