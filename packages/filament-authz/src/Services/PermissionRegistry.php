<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

class PermissionRegistry
{
    protected const CACHE_KEY = 'permissions:registry';

    /**
     * Registered permission definitions.
     *
     * @var array<string, array{name: string, description: string|null, group: string|null, resource: string|null}>
     */
    protected array $definitions = [];

    /**
     * Register a permission definition.
     */
    public function register(
        string $name,
        ?string $description = null,
        ?string $group = null,
        ?string $resource = null
    ): self {
        $this->definitions[$name] = [
            'name' => $name,
            'description' => $description,
            'group' => $group,
            'resource' => $resource,
        ];

        return $this;
    }

    /**
     * Register multiple permissions for a resource.
     *
     * @param  array<string>  $abilities
     */
    public function registerResource(
        string $resource,
        array $abilities = ['viewAny', 'view', 'create', 'update', 'delete'],
        ?string $group = null
    ): self {
        foreach ($abilities as $ability) {
            $name = "{$resource}.{$ability}";
            $description = ucfirst($ability).' '.str_replace('_', ' ', $resource);

            $this->register($name, $description, $group, $resource);
        }

        return $this;
    }

    /**
     * Register a wildcard permission.
     */
    public function registerWildcard(
        string $resource,
        ?string $description = null,
        ?string $group = null
    ): self {
        $name = "{$resource}.*";
        $description = $description ?? "All {$resource} permissions";

        return $this->register($name, $description, $group, $resource);
    }

    /**
     * Sync registered permissions to the database.
     *
     * @return array{created: int, skipped: int}
     */
    public function sync(string $guardName = 'web'): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($this->definitions as $definition) {
            $exists = Permission::query()
                ->where('name', $definition['name'])
                ->where('guard_name', $guardName)
                ->exists();

            if (! $exists) {
                Permission::create([
                    'name' => $definition['name'],
                    'guard_name' => $guardName,
                ]);
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->clearCache();

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Get all registered definitions.
     *
     * @return array<string, array{name: string, description: string|null, group: string|null, resource: string|null}>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * Get definitions grouped by resource.
     *
     * @return Collection<string, Collection<int, array{name: string, description: string|null, group: string|null, resource: string|null}>>
     */
    public function groupByResource(): Collection
    {
        /** @var Collection<string, Collection<int, array{name: string, description: string|null, group: string|null, resource: string|null}>> */
        return collect($this->definitions)
            ->groupBy(fn (array $d): string => $d['resource'] ?? 'other')
            ->map(fn (Collection $items): Collection => $items->values());
    }

    /**
     * Get definitions grouped by group.
     *
     * @return Collection<string, Collection<int, array{name: string, description: string|null, group: string|null, resource: string|null}>>
     */
    public function groupByGroup(): Collection
    {
        /** @var Collection<string, Collection<int, array{name: string, description: string|null, group: string|null, resource: string|null}>> */
        return collect($this->definitions)
            ->groupBy(fn (array $d): string => $d['group'] ?? 'ungrouped')
            ->map(fn (Collection $items): Collection => $items->values());
    }

    /**
     * Get all registered resources.
     *
     * @return Collection<int, string>
     */
    public function getResources(): Collection
    {
        return collect($this->definitions)
            ->pluck('resource')
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Get all registered groups.
     *
     * @return Collection<int, string>
     */
    public function getGroups(): Collection
    {
        return collect($this->definitions)
            ->pluck('group')
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Check if a permission is registered.
     */
    public function isRegistered(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /**
     * Get a permission definition.
     *
     * @return array{name: string, description: string|null, group: string|null, resource: string|null}|null
     */
    public function getDefinition(string $name): ?array
    {
        return $this->definitions[$name] ?? null;
    }

    /**
     * Clear the registry.
     */
    public function clear(): self
    {
        $this->definitions = [];
        $this->clearCache();

        return $this;
    }

    /**
     * Load definitions from a configuration array.
     *
     * @param  array<string, array<string, mixed>>  $config
     */
    public function loadFromConfig(array $config): self
    {
        foreach ($config as $name => $definition) {
            $this->register(
                $name,
                $definition['description'] ?? null,
                $definition['group'] ?? null,
                $definition['resource'] ?? null
            );
        }

        return $this;
    }

    /**
     * Export definitions to an array.
     *
     * @return array<string, array{name: string, description: string|null, group: string|null, resource: string|null}>
     */
    public function export(): array
    {
        return $this->definitions;
    }

    /**
     * Get permissions that exist in database but not in registry.
     *
     * @return Collection<int, Permission>
     */
    public function getUnregisteredPermissions(string $guardName = 'web'): Collection
    {
        $registeredNames = array_keys($this->definitions);

        return Permission::query()
            ->where('guard_name', $guardName)
            ->whereNotIn('name', $registeredNames)
            ->get();
    }

    /**
     * Get permissions that exist in registry but not in database.
     *
     * @return Collection<int, string>
     */
    public function getMissingPermissions(string $guardName = 'web'): Collection
    {
        $dbPermissions = Permission::query()
            ->where('guard_name', $guardName)
            ->pluck('name');

        return collect(array_keys($this->definitions))
            ->diff($dbPermissions)
            ->values();
    }

    /**
     * Clear the cache.
     */
    protected function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
