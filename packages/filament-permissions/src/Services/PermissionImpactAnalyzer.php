<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Services;

use AIArmada\FilamentPermissions\Enums\ImpactLevel;
use DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionImpactAnalyzer
{
    public function __construct(
        protected RoleInheritanceService $roleInheritance
    ) {}

    /**
     * Analyze the impact of granting a permission to a role.
     *
     * @return array{
     *     permission: string,
     *     role: string,
     *     impact_level: ImpactLevel,
     *     affected_users_count: int,
     *     affected_roles: array<string>,
     *     reasoning: string
     * }
     */
    public function analyzePermissionGrant(string $permissionName, Role $role): array
    {
        $affectedRoles = $this->getAffectedRoles($role);
        $affectedUsers = $this->countAffectedUsers($affectedRoles);
        $impactLevel = ImpactLevel::fromAffectedUsers($affectedUsers);

        return [
            'permission' => $permissionName,
            'role' => $role->name,
            'impact_level' => $impactLevel,
            'affected_users_count' => $affectedUsers,
            'affected_roles' => $affectedRoles->pluck('name')->toArray(),
            'reasoning' => $this->generateReasoning($impactLevel, $affectedUsers, $affectedRoles->count()),
        ];
    }

    /**
     * Analyze the impact of revoking a permission from a role.
     *
     * @return array{
     *     permission: string,
     *     role: string,
     *     impact_level: ImpactLevel,
     *     affected_users_count: int,
     *     affected_roles: array<string>,
     *     users_losing_access: int,
     *     reasoning: string
     * }
     */
    public function analyzePermissionRevoke(string $permissionName, Role $role): array
    {
        $affectedRoles = $this->getAffectedRoles($role);
        $affectedUsers = $this->countAffectedUsers($affectedRoles);
        $usersLosingAccess = $this->countUsersLosingAccess($permissionName, $affectedRoles);
        $impactLevel = ImpactLevel::fromAffectedUsers($usersLosingAccess);

        return [
            'permission' => $permissionName,
            'role' => $role->name,
            'impact_level' => $impactLevel,
            'affected_users_count' => $affectedUsers,
            'affected_roles' => $affectedRoles->pluck('name')->toArray(),
            'users_losing_access' => $usersLosingAccess,
            'reasoning' => $this->generateReasoning($impactLevel, $usersLosingAccess, $affectedRoles->count()),
        ];
    }

    /**
     * Analyze the impact of deleting a role.
     *
     * @return array{
     *     role: string,
     *     impact_level: ImpactLevel,
     *     affected_users_count: int,
     *     child_roles: array<string>,
     *     permissions_to_redistribute: int,
     *     reasoning: string
     * }
     */
    public function analyzeRoleDeletion(Role $role): array
    {
        $children = $this->roleInheritance->getChildren($role);
        $affectedUsers = $this->countUsersWithRole($role);
        $permissionCount = $role->permissions()->count();
        $impactLevel = ImpactLevel::fromAffectedUsers($affectedUsers);

        // Increase impact if role has children
        if ($children->isNotEmpty()) {
            $impactLevel = match ($impactLevel) {
                ImpactLevel::None, ImpactLevel::Low => ImpactLevel::Medium,
                ImpactLevel::Medium => ImpactLevel::High,
                ImpactLevel::High, ImpactLevel::Critical => ImpactLevel::Critical,
            };
        }

        return [
            'role' => $role->name,
            'impact_level' => $impactLevel,
            'affected_users_count' => $affectedUsers,
            'child_roles' => $children->pluck('name')->toArray(),
            'permissions_to_redistribute' => $permissionCount,
            'reasoning' => $this->generateDeletionReasoning($impactLevel, $affectedUsers, $children->count()),
        ];
    }

    /**
     * Analyze the impact of changing role hierarchy.
     *
     * @return array{
     *     role: string,
     *     old_parent: string|null,
     *     new_parent: string|null,
     *     impact_level: ImpactLevel,
     *     permissions_gained: array<string>,
     *     permissions_lost: array<string>,
     *     affected_users_count: int,
     *     reasoning: string
     * }
     */
    public function analyzeHierarchyChange(Role $role, ?Role $newParent): array
    {
        $currentParent = $this->roleInheritance->getParent($role);
        $currentInherited = $currentParent !== null
            ? $currentParent->permissions->pluck('name')->toArray()
            : [];

        $newInherited = $newParent !== null
            ? $newParent->permissions->pluck('name')->toArray()
            : [];

        $gained = array_diff($newInherited, $currentInherited);
        $lost = array_diff($currentInherited, $newInherited);

        $affectedRoles = $this->getAffectedRoles($role);
        $affectedUsers = $this->countAffectedUsers($affectedRoles);
        $impactLevel = ImpactLevel::fromAffectedUsers($affectedUsers);

        // Increase impact if losing permissions
        if (count($lost) > count($gained)) {
            $impactLevel = match ($impactLevel) {
                ImpactLevel::None => ImpactLevel::Low,
                ImpactLevel::Low => ImpactLevel::Medium,
                ImpactLevel::Medium, ImpactLevel::High, ImpactLevel::Critical => $impactLevel,
            };
        }

        return [
            'role' => $role->name,
            'old_parent' => $currentParent?->name,
            'new_parent' => $newParent?->name,
            'impact_level' => $impactLevel,
            'permissions_gained' => array_values($gained),
            'permissions_lost' => array_values($lost),
            'affected_users_count' => $affectedUsers,
            'reasoning' => $this->generateHierarchyReasoning($impactLevel, count($gained), count($lost)),
        ];
    }

    /**
     * Analyze the impact of bulk permission changes.
     *
     * @param  array<string>  $permissions
     * @return array{
     *     operation: string,
     *     role: string,
     *     permission_count: int,
     *     impact_level: ImpactLevel,
     *     affected_users_count: int,
     *     affected_roles: array<string>,
     *     reasoning: string
     * }
     */
    public function analyzeBulkChange(string $operation, Role $role, array $permissions): array
    {
        $affectedRoles = $this->getAffectedRoles($role);
        $affectedUsers = $this->countAffectedUsers($affectedRoles);

        // Higher impact for bulk operations
        $baseImpact = ImpactLevel::fromAffectedUsers($affectedUsers);
        $impactLevel = count($permissions) > 10
            ? $this->escalateImpact($baseImpact)
            : $baseImpact;

        return [
            'operation' => $operation,
            'role' => $role->name,
            'permission_count' => count($permissions),
            'impact_level' => $impactLevel,
            'affected_users_count' => $affectedUsers,
            'affected_roles' => $affectedRoles->pluck('name')->toArray(),
            'reasoning' => $this->generateBulkReasoning($impactLevel, count($permissions), $affectedUsers),
        ];
    }

    /**
     * Get all roles affected by a change to a role (including descendants).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    protected function getAffectedRoles(Role $role): \Illuminate\Database\Eloquent\Collection
    {
        $descendants = $this->roleInheritance->getDescendants($role);

        return $descendants->prepend($role);
    }

    /**
     * Count users assigned to any of the given roles.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Role>  $roles
     */
    protected function countAffectedUsers(\Illuminate\Database\Eloquent\Collection $roles): int
    {
        $roleIds = $roles->pluck('id');

        return DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->whereIn('role_id', $roleIds)
            ->distinct('model_id')
            ->count('model_id');
    }

    /**
     * Count users who would lose access to a permission.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Role>  $affectedRoles
     */
    protected function countUsersLosingAccess(string $permissionName, \Illuminate\Database\Eloquent\Collection $affectedRoles): int
    {
        // Find other roles that grant this permission
        $permission = Permission::where('name', $permissionName)->first();
        if ($permission === null) {
            return 0;
        }

        $otherRolesWithPermission = Role::whereHas('permissions', function ($query) use ($permissionName): void {
            $query->where('name', $permissionName);
        })->whereNotIn('id', $affectedRoles->pluck('id'))->pluck('id');

        // Count users in affected roles who don't have permission through other roles
        $affectedRoleIds = $affectedRoles->pluck('id');

        return DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->whereIn('role_id', $affectedRoleIds)
            ->whereNotIn('model_id', function ($query) use ($otherRolesWithPermission): void {
                $query->select('model_id')
                    ->from(config('permission.table_names.model_has_roles', 'model_has_roles'))
                    ->whereIn('role_id', $otherRolesWithPermission);
            })
            ->distinct('model_id')
            ->count('model_id');
    }

    /**
     * Count users with a specific role.
     */
    protected function countUsersWithRole(Role $role): int
    {
        return DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->where('role_id', $role->id)
            ->count();
    }

    /**
     * Escalate impact level by one step.
     */
    protected function escalateImpact(ImpactLevel $level): ImpactLevel
    {
        return match ($level) {
            ImpactLevel::None => ImpactLevel::Low,
            ImpactLevel::Low => ImpactLevel::Medium,
            ImpactLevel::Medium => ImpactLevel::High,
            ImpactLevel::High, ImpactLevel::Critical => ImpactLevel::Critical,
        };
    }

    /**
     * Generate reasoning for impact assessment.
     */
    protected function generateReasoning(ImpactLevel $level, int $userCount, int $roleCount): string
    {
        $parts = [];

        if ($userCount > 0) {
            $parts[] = "{$userCount} user(s) will be affected";
        }

        if ($roleCount > 1) {
            $parts[] = "propagates to {$roleCount} role(s)";
        }

        $prefix = match ($level) {
            ImpactLevel::None => 'No significant impact:',
            ImpactLevel::Low => 'Low impact:',
            ImpactLevel::Medium => 'Medium impact:',
            ImpactLevel::High => 'High impact - review recommended:',
            ImpactLevel::Critical => 'Critical impact - approval required:',
        };

        return $prefix.' '.implode(', ', $parts);
    }

    /**
     * Generate reasoning for deletion impact.
     */
    protected function generateDeletionReasoning(ImpactLevel $level, int $userCount, int $childCount): string
    {
        $parts = ["{$userCount} user(s) will lose this role"];

        if ($childCount > 0) {
            $parts[] = "{$childCount} child role(s) will be orphaned";
        }

        return $this->generateReasoning($level, $userCount, 1).'. '.implode('. ', $parts);
    }

    /**
     * Generate reasoning for hierarchy change impact.
     */
    protected function generateHierarchyReasoning(ImpactLevel $level, int $gained, int $lost): string
    {
        $parts = [];

        if ($gained > 0) {
            $parts[] = "{$gained} permission(s) will be gained";
        }

        if ($lost > 0) {
            $parts[] = "{$lost} permission(s) will be lost";
        }

        return implode(', ', $parts);
    }

    /**
     * Generate reasoning for bulk changes.
     */
    protected function generateBulkReasoning(ImpactLevel $level, int $permCount, int $userCount): string
    {
        return "{$permCount} permissions affecting {$userCount} users";
    }
}
