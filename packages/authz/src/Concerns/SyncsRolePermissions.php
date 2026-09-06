<?php

declare(strict_types=1);

namespace AIArmada\Authz\Concerns;

use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\CommerceSupport\Models\Role;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared permission sync logic for Role create/edit pages.
 *
 * Uses Spatie's syncPermissions() which handles the pivot table correctly.
 *
 * @property Role $record
 * @property array<string, mixed> $data
 */
trait SyncsRolePermissions
{
    use ScopesAuthzTenancy;

    /**
     * @var list<string>
     */
    protected array $permissionNames = [];

    /**
     * Extract permission names from form data before save.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractPermissionIds(array $data): array
    {
        $permissions = [];

        // Collect all permission fields (permissions_resource_*, permissions_pages_*, permissions_widgets_*, permissions_custom)
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'permissions_') && is_array($value)) {
                $permissions = array_merge($permissions, $value);
                unset($data[$key]);
            }
        }

        $this->permissionNames = array_values(array_filter(
            array_map('strval', $permissions)
        ));

        return $data;
    }

    /**
     * Sync permissions to the role using Spatie's syncPermissions.
     *
     * Only permissions that already exist for the role's guard can be assigned.
     * Discovery and permission creation are explicit operations.
     */
    protected function syncPermissionsToRole(): void
    {
        /** @var Role $role */
        $role = $this->record;

        if ($this->permissionNames === []) {
            $role->syncPermissions([]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $guardName = $role->guard_name;

        $permissions = Permission::query()
            ->where('guard_name', $guardName)
            ->whereIn('name', $this->permissionNames)
            ->get();

        $existingPermissionNames = $permissions
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        $missingPermissionNames = array_values(array_diff($this->permissionNames, $existingPermissionNames));

        if ($missingPermissionNames !== []) {
            throw PermissionDoesNotExist::create($missingPermissionNames[0], $guardName);
        }

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
