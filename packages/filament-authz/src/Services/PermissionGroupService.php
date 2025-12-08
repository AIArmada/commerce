<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Models\PermissionGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;

class PermissionGroupService
{
    protected const CACHE_KEY_PREFIX = 'permissions:groups:';

    /**
     * Create a new permission group.
     *
     * @param  array<string>  $permissions
     * @param  array<string>|null  $implicitAbilities
     */
    public function createGroup(
        string $name,
        ?string $description = null,
        ?string $parentId = null,
        array $permissions = [],
        ?array $implicitAbilities = null,
        bool $isSystem = false
    ): PermissionGroup {
        $group = PermissionGroup::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $description,
            'parent_id' => $parentId,
            'implicit_abilities' => $implicitAbilities,
            'is_system' => $isSystem,
        ]);

        if (! empty($permissions)) {
            $this->syncPermissions($group, $permissions);
        }

        $this->clearCache();

        return $group;
    }

    /**
     * Update a permission group.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateGroup(PermissionGroup $group, array $data): PermissionGroup
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $group->update($data);

        if (isset($data['permissions'])) {
            $this->syncPermissions($group, $data['permissions']);
        }

        $this->clearCache();

        return $group->refresh();
    }

    /**
     * Delete a permission group.
     */
    public function deleteGroup(PermissionGroup $group): bool
    {
        $result = $group->delete();
        $this->clearCache();

        return (bool) $result;
    }

    /**
     * Sync permissions to a group.
     *
     * @param  array<string>  $permissionNames
     */
    public function syncPermissions(PermissionGroup $group, array $permissionNames): void
    {
        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->get();

        $group->permissions()->sync($permissions->pluck('id'));
        $this->clearCache();
    }

    /**
     * Add permissions to a group.
     *
     * @param  array<string>  $permissionNames
     */
    public function addPermissions(PermissionGroup $group, array $permissionNames): void
    {
        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->get();

        $group->permissions()->syncWithoutDetaching($permissions->pluck('id'));
        $this->clearCache();
    }

    /**
     * Remove permissions from a group.
     *
     * @param  array<string>  $permissionNames
     */
    public function removePermissions(PermissionGroup $group, array $permissionNames): void
    {
        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->get();

        $group->permissions()->detach($permissions->pluck('id'));
        $this->clearCache();
    }

    /**
     * Get all permissions for a group, including inherited ones.
     *
     * @return Collection<int, Permission>
     */
    public function getGroupPermissions(PermissionGroup $group, bool $includeInherited = true): Collection
    {
        $cacheKey = self::CACHE_KEY_PREFIX."permissions:{$group->id}:".($includeInherited ? 'inherited' : 'direct');
        $ttl = config('filament-authz.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($group, $includeInherited): Collection {
            if ($includeInherited) {
                return $group->getAllPermissions();
            }

            return $group->permissions;
        });
    }

    /**
     * Get all root groups (no parent).
     *
     * @return Collection<int, PermissionGroup>
     */
    public function getRootGroups(): Collection
    {
        return PermissionGroup::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get the full hierarchy tree.
     *
     * @return Collection<int, PermissionGroup>
     */
    public function getHierarchyTree(): Collection
    {
        $cacheKey = self::CACHE_KEY_PREFIX.'hierarchy_tree';
        $ttl = config('filament-authz.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function (): Collection {
            return PermissionGroup::query()
                ->whereNull('parent_id')
                ->with('children')
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * Find a group by slug.
     */
    public function findBySlug(string $slug): ?PermissionGroup
    {
        return PermissionGroup::query()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Move a group to a new parent.
     */
    public function moveGroup(PermissionGroup $group, ?string $newParentId): PermissionGroup
    {
        // Prevent circular references
        if ($newParentId !== null) {
            $newParent = PermissionGroup::find($newParentId);
            if ($newParent !== null && $group->isAncestorOf($newParent)) {
                throw new InvalidArgumentException('Cannot move a group to one of its descendants.');
            }
        }

        // Check depth limit
        $maxDepth = config('filament-authz.hierarchies.max_group_depth', 5);
        if ($newParentId !== null) {
            $newParent = PermissionGroup::find($newParentId);
            $newDepth = $newParent !== null ? $newParent->getDepth() + 1 : 0;
            $subtreeDepth = $this->getMaxSubtreeDepth($group);

            if ($newDepth + $subtreeDepth > $maxDepth) {
                throw new InvalidArgumentException("Moving this group would exceed the maximum depth of {$maxDepth}.");
            }
        }

        $group->update(['parent_id' => $newParentId]);
        $this->clearCache();

        return $group->refresh();
    }

    /**
     * Reorder groups within the same parent.
     *
     * @param  array<string, int>  $order  [group_id => sort_order]
     */
    public function reorderGroups(array $order): void
    {
        foreach ($order as $groupId => $sortOrder) {
            PermissionGroup::query()
                ->where('id', $groupId)
                ->update(['sort_order' => $sortOrder]);
        }

        $this->clearCache();
    }

    /**
     * Get groups that contain a specific permission.
     *
     * @return Collection<int, PermissionGroup>
     */
    public function getGroupsWithPermission(string $permissionName): Collection
    {
        $permission = Permission::query()
            ->where('name', $permissionName)
            ->first();

        if ($permission === null) {
            return new Collection();
        }

        return PermissionGroup::query()
            ->whereHas('permissions', fn ($q) => $q->where('id', $permission->id))
            ->get();
    }

    /**
     * Clear all caches.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX.'hierarchy_tree');
        // Note: Individual group permission caches will expire naturally
    }

    /**
     * Get the maximum depth of a group's subtree.
     */
    protected function getMaxSubtreeDepth(PermissionGroup $group): int
    {
        $maxDepth = 0;

        foreach ($group->children as $child) {
            $childDepth = 1 + $this->getMaxSubtreeDepth($child);
            $maxDepth = max($maxDepth, $childDepth);
        }

        return $maxDepth;
    }
}
