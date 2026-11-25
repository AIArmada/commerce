<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Authz\Features\Authorization\Models\Role;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

final readonly class EnsureRoleDefinition
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /** @param list<string> $permissions */
    public function execute(string $name, string $guard, string | int | null $scope, array $permissions, bool $prune): Role
    {
        if ($name === '' || $guard === '') {
            throw new InvalidArgumentException('Role name and guard are required.');
        }

        return (new Role)->getConnection()->transaction(function () use ($name, $guard, $scope, $permissions, $prune): Role {
            $attributes = ['name' => $name, 'guard_name' => $guard];

            if ($this->registrar->teams) {
                $attributes[$this->registrar->teamsKey] = $scope;
            }

            $role = Role::query()->firstOrCreate($attributes);

            foreach (array_unique($permissions) as $permission) {
                Permission::findOrCreate($permission, $guard);
            }

            if ($prune) {
                $role->syncPermissions($permissions);
            } elseif ($permissions !== []) {
                $role->givePermissionTo($permissions);
            }

            return $role;
        });
    }
}
