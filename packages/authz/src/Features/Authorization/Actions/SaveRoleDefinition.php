<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final readonly class SaveRoleDefinition
{
    public function __construct(private PermissionRegistrar $registrar, private SyncRolePermissions $permissions) {}

    /** @param list<string> $permissionNames */
    public function execute(?string $id, string $name, string $guard, string | int | null $scope, array $permissionNames): Role
    {
        if (mb_trim($name) === '' || mb_strlen($name) > 255 || ! in_array($guard, (array) config('authz.guards', ['web']), true)) {
            throw new InvalidArgumentException('A valid role name and configured guard are required.');
        }

        if (! $this->registrar->teams && $scope !== null) {
            throw new AuthorizationException('Roles cannot be scoped when permission teams are disabled.');
        }

        /** @var class-string<Role> $roleClass */
        $roleClass = $this->registrar->getRoleClass();
        $model = new $roleClass;
        $connection = $model->getConnection();

        return $connection->transaction(function () use ($id, $name, $guard, $scope, $permissionNames, $model, $connection): Role {
            $role = $id === null ? $model : $model->newQuery()->whereKey($id)->lockForUpdate()->firstOrFail();
            $attributes = ['name' => $name, 'guard_name' => $guard];
            $teamsKey = $this->registrar->teamsKey;
            if ($this->registrar->teams) {
                $attributes[$teamsKey] = $scope;
            }

            $duplicate = $model->newQuery()->where($attributes);
            if ($role->exists) {
                $duplicate->where($role->getKeyName(), '!=', $role->getKey());
            }

            if ($duplicate->exists()) {
                throw RoleAlreadyExists::create($name, $guard);
            }

            if ($role->exists && (
                $guard !== $role->getAttribute('guard_name')
                || ($this->registrar->teams && (string) $scope !== (string) $role->getAttribute($teamsKey))
            )) {
                $assigned = $connection->table((string) config('permission.table_names.model_has_roles', 'model_has_roles'))
                    ->where($this->registrar->pivotRole, $role->getKey())
                    ->exists();

                if ($assigned) {
                    throw new AuthorizationException('An assigned role cannot change its guard or scope.');
                }
            }

            $role->fill($attributes);
            $role->saveOrFail();
            $this->permissions->execute($role, $permissionNames);

            return $role;
        });
    }
}
