<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleComparer
{
    public function __construct(
        protected RoleInheritanceService $roleInheritance
    ) {}

    /**
     * Compare two roles and show their differences.
     *
     * @return array{
     *     role_a: string,
     *     role_b: string,
     *     shared_permissions: array<string>,
     *     only_in_a: array<string>,
     *     only_in_b: array<string>,
     *     similarity_percent: float
     * }
     */
    public function compare(Role $roleA, Role $roleB): array
    {
        $permissionsA = $roleA->permissions->pluck('name')->toArray();
        $permissionsB = $roleB->permissions->pluck('name')->toArray();

        $shared = array_intersect($permissionsA, $permissionsB);
        $onlyA = array_diff($permissionsA, $permissionsB);
        $onlyB = array_diff($permissionsB, $permissionsA);

        $total = count(array_unique(array_merge($permissionsA, $permissionsB)));
        $similarity = $total > 0 ? (count($shared) / $total) * 100 : 100;

        return [
            'role_a' => $roleA->name,
            'role_b' => $roleB->name,
            'shared_permissions' => array_values($shared),
            'only_in_a' => array_values($onlyA),
            'only_in_b' => array_values($onlyB),
            'similarity_percent' => round($similarity, 2),
        ];
    }

    /**
     * Compare a role with its parent.
     *
     * @return array{
     *     role: string,
     *     parent: string|null,
     *     inherited_permissions: array<string>,
     *     own_permissions: array<string>,
     *     override_count: int
     * }|null
     */
    public function compareWithParent(Role $role): ?array
    {
        $parent = $this->roleInheritance->getParent($role);

        if ($parent === null) {
            return null;
        }

        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $parentPermissions = $parent->permissions->pluck('name')->toArray();

        $inherited = array_intersect($rolePermissions, $parentPermissions);
        $own = array_diff($rolePermissions, $parentPermissions);

        return [
            'role' => $role->name,
            'parent' => $parent->name,
            'inherited_permissions' => array_values($inherited),
            'own_permissions' => array_values($own),
            'override_count' => count($own),
        ];
    }

    /**
     * Find roles similar to a given role.
     *
     * @return array<int, array{role: string, similarity_percent: float}>
     */
    public function findSimilarRoles(Role $role, float $minSimilarity = 50.0): array
    {
        $allRoles = Role::where('id', '!=', $role->id)->get();
        $similar = [];

        foreach ($allRoles as $otherRole) {
            $comparison = $this->compare($role, $otherRole);

            if ($comparison['similarity_percent'] >= $minSimilarity) {
                $similar[] = [
                    'role' => $otherRole->name,
                    'similarity_percent' => $comparison['similarity_percent'],
                ];
            }
        }

        // Sort by similarity descending
        usort($similar, fn ($a, $b) => $b['similarity_percent'] <=> $a['similarity_percent']);

        return $similar;
    }

    /**
     * Find redundant roles (roles with same permissions).
     *
     * @return array<int, array{roles: array<string>, permissions_count: int}>
     */
    public function findRedundantRoles(): array
    {
        $roles = Role::all();
        $permissionSets = [];

        foreach ($roles as $role) {
            $permissions = $role->permissions->pluck('name')->sort()->values()->toArray();
            $key = md5(json_encode($permissions));

            if (! isset($permissionSets[$key])) {
                $permissionSets[$key] = [
                    'roles' => [],
                    'permissions_count' => count($permissions),
                ];
            }

            $permissionSets[$key]['roles'][] = $role->name;
        }

        // Return only sets with multiple roles
        return array_values(array_filter(
            $permissionSets,
            fn (array $set): bool => count($set['roles']) > 1
        ));
    }

    /**
     * Get the diff between two roles as operations.
     *
     * @return array{
     *     to_add: array<string>,
     *     to_remove: array<string>,
     *     operations_count: int
     * }
     */
    public function getDiff(Role $from, Role $to): array
    {
        $fromPermissions = $from->permissions->pluck('name')->toArray();
        $toPermissions = $to->permissions->pluck('name')->toArray();

        $toAdd = array_diff($toPermissions, $fromPermissions);
        $toRemove = array_diff($fromPermissions, $toPermissions);

        return [
            'to_add' => array_values($toAdd),
            'to_remove' => array_values($toRemove),
            'operations_count' => count($toAdd) + count($toRemove),
        ];
    }

    /**
     * Generate a role hierarchy report.
     *
     * @return array{
     *     total_roles: int,
     *     max_depth: int,
     *     orphan_roles: array<string>,
     *     roles_per_level: array<int, int>
     * }
     */
    public function generateHierarchyReport(): array
    {
        $roles = Role::all();
        $orphans = [];
        $levelsCount = [];
        $maxDepth = 0;

        foreach ($roles as $role) {
            $depth = $this->roleInheritance->getDepth($role);
            $maxDepth = max($maxDepth, $depth);

            $levelsCount[$depth] = ($levelsCount[$depth] ?? 0) + 1;

            // Check for orphans (roles with invalid parent_id)
            /** @phpstan-ignore property.notFound */
            if ($role->parent_role_id !== null) {
                /** @phpstan-ignore property.notFound */
                $parent = Role::find($role->parent_role_id);
                if ($parent === null) {
                    $orphans[] = $role->name;
                }
            }
        }

        ksort($levelsCount);

        return [
            'total_roles' => $roles->count(),
            'max_depth' => $maxDepth,
            'orphan_roles' => $orphans,
            'roles_per_level' => $levelsCount,
        ];
    }

    /**
     * Find unused permissions (not assigned to any role).
     *
     * @return array<string>
     */
    public function findUnusedPermissions(): array
    {
        $allPermissions = Permission::pluck('name')->toArray();
        $usedPermissions = [];

        foreach (Role::all() as $role) {
            $usedPermissions = array_merge(
                $usedPermissions,
                $role->permissions->pluck('name')->toArray()
            );
        }

        $usedPermissions = array_unique($usedPermissions);

        return array_values(array_diff($allPermissions, $usedPermissions));
    }
}
