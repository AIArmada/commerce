<?php

declare(strict_types=1);

namespace AIArmada\Authz\Actions;

use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;

final readonly class SyncConfiguredAuthorization
{
    /**
     * @param  list<string>  $permissions
     * @param  array<string, list<string>>  $roles
     * @param  list<string>  $guards
     * @return array{int, int}
     */
    public function execute(array $permissions, array $roles, array $guards): array
    {
        return (new Role)->getConnection()->transaction(function () use ($permissions, $roles, $guards): array {
            $permissionCount = 0;
            $roleCount = 0;

            foreach ($permissions as $permission) {
                foreach ($guards as $guard) {
                    Permission::findOrCreate($permission, $guard);
                    $permissionCount++;
                }
            }

            foreach ($roles as $roleName => $rolePermissions) {
                foreach ($guards as $guard) {
                    $role = Role::findOrCreate($roleName, $guard);
                    $role->syncPermissions($rolePermissions);
                    $roleCount++;
                }
            }

            return [$permissionCount, $roleCount];
        });
    }
}
