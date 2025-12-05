<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

class WildcardPermissionResolver
{
    protected const CACHE_KEY = 'permissions:wildcard_map';

    /**
     * Resolve a wildcard permission to concrete permissions.
     *
     * @return Collection<int, string>
     */
    public function resolve(string $wildcardPermission): Collection
    {
        // Not a wildcard, return as-is
        if (! $this->isWildcard($wildcardPermission)) {
            return collect([$wildcardPermission]);
        }

        $allPermissions = $this->getAllPermissions();

        // Handle different wildcard patterns
        return match (true) {
            $wildcardPermission === '*' => $allPermissions,
            str_ends_with($wildcardPermission, '.*') => $this->resolvePrefixWildcard($wildcardPermission, $allPermissions),
            str_contains($wildcardPermission, '*') => $this->resolvePatternWildcard($wildcardPermission, $allPermissions),
            default => collect([$wildcardPermission]),
        };
    }

    /**
     * Check if a permission string is a wildcard pattern.
     */
    public function isWildcard(string $permission): bool
    {
        return str_contains($permission, '*');
    }

    /**
     * Check if a specific permission matches a wildcard pattern.
     */
    public function matches(string $wildcardPattern, string $permission): bool
    {
        // Exact match
        if ($wildcardPattern === $permission) {
            return true;
        }

        // Universal wildcard
        if ($wildcardPattern === '*') {
            return true;
        }

        // Prefix wildcard (e.g., 'orders.*' matches 'orders.create')
        if (str_ends_with($wildcardPattern, '.*')) {
            $prefix = substr($wildcardPattern, 0, -2);

            return str_starts_with($permission, $prefix.'.');
        }

        // Pattern wildcard (e.g., '*.view' matches 'orders.view')
        if (str_contains($wildcardPattern, '*')) {
            $pattern = $this->wildcardToRegex($wildcardPattern);

            return preg_match($pattern, $permission) === 1;
        }

        return false;
    }

    /**
     * Get all unique permission prefixes (e.g., 'orders' from 'orders.create').
     *
     * @return Collection<int, string>
     */
    public function getPrefixes(): Collection
    {
        /** @var Collection<int, string> */
        return $this->getAllPermissions()
            ->map(fn (string $permission): ?string => $this->extractPrefix($permission))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Get all permissions that share the same prefix.
     *
     * @return Collection<int, string>
     */
    public function getByPrefix(string $prefix): Collection
    {
        return $this->getAllPermissions()
            ->filter(fn (string $permission) => $this->extractPrefix($permission) === $prefix)
            ->values();
    }

    /**
     * Group permissions by their prefix.
     *
     * @return Collection<string, Collection<int, string>>
     */
    public function groupByPrefix(): Collection
    {
        return $this->getAllPermissions()
            ->groupBy(fn (string $permission) => $this->extractPrefix($permission) ?? 'other')
            ->map(fn (Collection $permissions) => $permissions->values());
    }

    /**
     * Extract the prefix from a permission (e.g., 'orders' from 'orders.create').
     */
    public function extractPrefix(string $permission): ?string
    {
        $parts = explode('.', $permission);

        return count($parts) > 1 ? $parts[0] : null;
    }

    /**
     * Extract the action from a permission (e.g., 'create' from 'orders.create').
     */
    public function extractAction(string $permission): ?string
    {
        $parts = explode('.', $permission);

        return count($parts) > 1 ? end($parts) : null;
    }

    /**
     * Build a permission name from components.
     */
    public function buildPermission(string $resource, string $action): string
    {
        return "{$resource}.{$action}";
    }

    /**
     * Check if a user has a permission (supports wildcards).
     *
     * @param  object  $user
     */
    public function userHasPermission($user, string $permission): bool
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return false;
        }

        $userPermissions = collect($user->getAllPermissions()->pluck('name'));

        // Check direct permission
        if ($userPermissions->contains($permission)) {
            return true;
        }

        // Check wildcard permissions the user has
        $wildcardPermissions = $userPermissions->filter(fn (string $p) => $this->isWildcard($p));

        foreach ($wildcardPermissions as $wildcardPermission) {
            if ($this->matches($wildcardPermission, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the cached permission list.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get all permission names.
     *
     * @return Collection<int, string>
     */
    protected function getAllPermissions(): Collection
    {
        $ttl = config('filament-permissions.cache_ttl', 3600);

        return Cache::remember(self::CACHE_KEY, $ttl, function (): Collection {
            return Permission::query()->pluck('name');
        });
    }

    /**
     * Resolve a prefix wildcard pattern.
     *
     * @param  Collection<int, string>  $allPermissions
     * @return Collection<int, string>
     */
    protected function resolvePrefixWildcard(string $wildcardPermission, Collection $allPermissions): Collection
    {
        $prefix = substr($wildcardPermission, 0, -2);

        /** @var Collection<int, string> */
        return $allPermissions->filter(fn (string $p): bool => str_starts_with($p, $prefix.'.'));
    }

    /**
     * Resolve a pattern wildcard (contains * but not at the end).
     *
     * @param  Collection<int, string>  $allPermissions
     * @return Collection<int, string>
     */
    protected function resolvePatternWildcard(string $wildcardPermission, Collection $allPermissions): Collection
    {
        $pattern = $this->wildcardToRegex($wildcardPermission);

        return $allPermissions->filter(fn (string $p) => preg_match($pattern, $p) === 1);
    }

    /**
     * Convert a wildcard pattern to a regex pattern.
     */
    protected function wildcardToRegex(string $wildcard): string
    {
        $escaped = preg_quote($wildcard, '/');
        $pattern = str_replace('\*', '[^.]+', $escaped);

        return '/^'.$pattern.'$/';
    }
}
