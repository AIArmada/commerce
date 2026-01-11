<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared permission sync logic for Role create/edit pages.
 */
trait SyncsRolePermissions
{
    /**
     * @var list<string>
     */
    protected array $permissionIds = [];

    protected function extractPermissionIds(array $data): array
    {
        $this->permissionIds = array_map('strval', $data['permissions'] ?? []);
        unset($data['permissions']);

        return $data;
    }

    protected function syncPermissionsToRole(): void
    {
        /** @var class-string<Permission> $permissionModel */
        $permissionModel = config('permission.models.permission', Permission::class);

        $permissions = $permissionModel::query()
            ->where('guard_name', $this->record->guard_name)
            ->whereIn('id', $this->permissionIds)
            ->get();

        $this->record->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
