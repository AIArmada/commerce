<?php

declare(strict_types=1);

namespace AIArmada\Authz\Actions;

use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

final readonly class SyncRolePermissions
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /** @param list<string> $permissionNames */
    public function execute(Role $role, array $permissionNames): void
    {
        $role->getConnection()->transaction(function () use ($role, $permissionNames): void {
            $permissions = Permission::query()
                ->where('guard_name', $role->guard_name)
                ->whereIn('name', $permissionNames)
                ->get();

            $missing = array_values(array_diff($permissionNames, $permissions->pluck('name')->all()));

            if ($missing !== []) {
                throw PermissionDoesNotExist::create($missing[0], $role->guard_name);
            }

            $role->syncPermissions($permissions);
        });

        $this->registrar->forgetCachedPermissions();
    }
}
