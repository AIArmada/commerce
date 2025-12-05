<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionCacheService
{
    protected const PREFIX = 'perm:';

    protected Repository $cache;

    protected int $ttl;

    protected bool $enabled;

    public function __construct()
    {
        $this->cache = Cache::store(config('filament-permissions.cache.store', 'default'));
        $this->ttl = config('filament-permissions.cache_ttl', 3600);
        $this->enabled = config('filament-permissions.cache.enabled', true);
    }

    /**
     * Get or set a cached value.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function remember(string $key, callable $callback): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        return $this->cache->remember(
            self::PREFIX.$key,
            $this->ttl,
            $callback
        );
    }

    /**
     * Get cached user permissions.
     *
     * @param  object  $user
     * @return array<string>
     */
    public function getUserPermissions($user): array
    {
        return $this->remember(
            "user:{$user->getKey()}:permissions",
            function () use ($user): array {
                if (method_exists($user, 'getAllPermissions')) {
                    return $user->getAllPermissions()->pluck('name')->toArray();
                }

                return [];
            }
        );
    }

    /**
     * Get cached role permissions.
     *
     * @return array<string>
     */
    public function getRolePermissions(Role $role): array
    {
        return $this->remember(
            "role:{$role->id}:permissions",
            fn (): array => $role->permissions->pluck('name')->toArray()
        );
    }

    /**
     * Check if a user has a permission (cached).
     *
     * @param  object  $user
     */
    public function userHasPermission($user, string $permission): bool
    {
        $permissions = $this->getUserPermissions($user);

        return in_array($permission, $permissions, true);
    }

    /**
     * Invalidate user cache.
     *
     * @param  object  $user
     */
    public function forgetUser($user): void
    {
        $this->cache->forget(self::PREFIX."user:{$user->getKey()}:permissions");
        $this->cache->forget(self::PREFIX."user:{$user->getKey()}:roles");
    }

    /**
     * Invalidate role cache.
     */
    public function forgetRole(Role $role): void
    {
        $this->cache->forget(self::PREFIX."role:{$role->id}:permissions");
    }

    /**
     * Invalidate permission cache.
     */
    public function forgetPermission(Permission $permission): void
    {
        $this->cache->forget(self::PREFIX."permission:{$permission->id}");
    }

    /**
     * Flush all permission caches.
     */
    public function flush(): void
    {
        // Flush user caches
        $users = \DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->distinct()
            ->pluck('model_id');

        foreach ($users as $userId) {
            $this->cache->forget(self::PREFIX."user:{$userId}:permissions");
            $this->cache->forget(self::PREFIX."user:{$userId}:roles");
        }

        // Flush role caches
        $roles = Role::all();
        foreach ($roles as $role) {
            $this->cache->forget(self::PREFIX."role:{$role->id}:permissions");
        }

        // Flush hierarchy cache
        $this->cache->forget(self::PREFIX.'hierarchy:tree');
    }

    /**
     * Warm the cache for a user.
     *
     * @param  object  $user
     */
    public function warmUserCache($user): void
    {
        $this->getUserPermissions($user);
    }

    /**
     * Warm the cache for all roles.
     */
    public function warmRoleCache(): void
    {
        $roles = Role::all();

        foreach ($roles as $role) {
            $this->getRolePermissions($role);
        }
    }

    /**
     * Get cache statistics.
     *
     * @return array{enabled: bool, store: string, ttl: int}
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'store' => config('filament-permissions.cache.store', 'default'),
            'ttl' => $this->ttl,
        ];
    }

    /**
     * Temporarily disable caching.
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Re-enable caching.
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Run callback without caching.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutCache(callable $callback): mixed
    {
        $wasEnabled = $this->enabled;
        $this->enabled = false;

        try {
            return $callback();
        } finally {
            $this->enabled = $wasEnabled;
        }
    }
}
