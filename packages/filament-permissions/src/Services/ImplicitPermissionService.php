<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Services;

use AIArmada\FilamentPermissions\Models\PermissionGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ImplicitPermissionService
{
    protected const CACHE_KEY = 'permissions:implicit_map';

    /**
     * Standard implicit ability mappings.
     *
     * @var array<string, array<string>>
     */
    protected array $standardMappings = [
        'manage' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
        'edit' => ['view', 'update'],
        'admin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'replicate'],
        'full_access' => ['*'],
    ];

    /**
     * Expand a permission to include its implicit abilities.
     *
     * @return Collection<int, string>
     */
    public function expand(string $permission): Collection
    {
        $parts = explode('.', $permission);

        if (count($parts) < 2) {
            return collect([$permission]);
        }

        $resource = $parts[0];
        $ability = end($parts);

        // Check if ability has implicit expansions
        $expandedAbilities = $this->getImplicitAbilities($ability);

        if ($expandedAbilities->isEmpty()) {
            return collect([$permission]);
        }

        /** @var Collection<int, string> */
        return $expandedAbilities->map(fn (string $a): string => "{$resource}.{$a}");
    }

    /**
     * Get implicit abilities for a given ability.
     *
     * @return Collection<int, string>
     */
    public function getImplicitAbilities(string $ability): Collection
    {
        $allMappings = $this->getAllMappings();

        $implicitAbilities = $allMappings[$ability] ?? [];

        return collect($implicitAbilities);
    }

    /**
     * Check if a permission implies another permission.
     */
    public function implies(string $permission, string $impliedPermission): bool
    {
        // Same permission
        if ($permission === $impliedPermission) {
            return true;
        }

        $parts = explode('.', $permission);
        $impliedParts = explode('.', $impliedPermission);

        // Different resources
        if ($parts[0] !== $impliedParts[0]) {
            return false;
        }

        if (count($parts) < 2 || count($impliedParts) < 2) {
            return false;
        }

        $ability = end($parts);
        $impliedAbility = end($impliedParts);

        $implicitAbilities = $this->getImplicitAbilities($ability);

        return $implicitAbilities->contains($impliedAbility) || $implicitAbilities->contains('*');
    }

    /**
     * Register a custom implicit ability mapping.
     *
     * @param  array<string>  $impliedAbilities
     */
    public function registerMapping(string $ability, array $impliedAbilities): void
    {
        $this->standardMappings[$ability] = $impliedAbilities;
        $this->clearCache();
    }

    /**
     * Register multiple mappings at once.
     *
     * @param  array<string, array<string>>  $mappings
     */
    public function registerMappings(array $mappings): void
    {
        $this->standardMappings = array_merge($this->standardMappings, $mappings);
        $this->clearCache();
    }

    /**
     * Get all implicit ability mappings.
     *
     * @return array<string, array<string>>
     */
    public function getAllMappings(): array
    {
        $ttl = config('filament-permissions.cache_ttl', 3600);

        return Cache::remember(self::CACHE_KEY, $ttl, function (): array {
            $mappings = $this->standardMappings;

            // Load group-defined implicit abilities
            $groupMappings = $this->loadGroupMappings();

            return array_merge($mappings, $groupMappings);
        });
    }

    /**
     * Get all permissions a user has, including implicit ones.
     *
     * @param  object  $user
     * @return Collection<int, string>
     */
    public function expandUserPermissions($user): Collection
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return collect();
        }

        $directPermissions = collect($user->getAllPermissions()->pluck('name'));
        $allPermissions = collect();

        foreach ($directPermissions as $permission) {
            $allPermissions = $allPermissions->merge($this->expand($permission));
        }

        return $allPermissions->unique()->values();
    }

    /**
     * Check if a user has a permission (including implicit permissions).
     *
     * @param  object  $user
     */
    public function userHasPermission($user, string $permission): bool
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return false;
        }

        $directPermissions = $user->getAllPermissions()->pluck('name');

        // Check direct permission
        if ($directPermissions->contains($permission)) {
            return true;
        }

        // Check if any direct permission implies the target
        foreach ($directPermissions as $directPermission) {
            if ($this->implies($directPermission, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Load implicit ability mappings from permission groups.
     *
     * @return array<string, array<string>>
     */
    protected function loadGroupMappings(): array
    {
        $mappings = [];

        $groups = PermissionGroup::query()
            ->whereNotNull('implicit_abilities')
            ->get();

        foreach ($groups as $group) {
            $implicitAbilities = $group->implicit_abilities;

            if (is_array($implicitAbilities)) {
                foreach ($implicitAbilities as $ability => $implied) {
                    if (is_array($implied)) {
                        $mappings[$ability] = array_merge($mappings[$ability] ?? [], $implied);
                    }
                }
            }
        }

        // Deduplicate
        foreach ($mappings as $ability => $implied) {
            $mappings[$ability] = array_unique($implied);
        }

        return $mappings;
    }
}
