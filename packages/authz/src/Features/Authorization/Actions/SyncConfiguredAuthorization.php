<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Authz\Features\Authorization\Models\Role;

final readonly class SyncConfiguredAuthorization
{
    public function __construct(private EnsurePermissions $ensurePermissions) {}

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

            foreach ($guards as $guard) {
                $this->ensurePermissions->execute($guard, $permissions);

                $permissionCount += count($permissions);

                $existing = Permission::query()
                    ->where('guard_name', $guard)
                    ->whereIn('name', $permissions)
                    ->get()
                    ->keyBy('name');

                foreach ($roles as $roleName => $rolePermissions) {
                    $role = Role::findOrCreate($roleName, $guard);

                    $resolved = array_map(
                        static fn (string $name): Permission | string => $existing->get($name) ?? $name,
                        $rolePermissions,
                    );

                    $role->syncPermissions($resolved);
                    $roleCount++;
                }
            }

            return [$permissionCount, $roleCount];
        });
    }
}
