<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final readonly class SavePermissionDefinition
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function execute(?string $id, string $name, string $guard): Permission
    {
        if (mb_trim($name) === '' || mb_strlen($name) > 255 || ! in_array($guard, (array) config('authz.guards', ['web']), true)) {
            throw new InvalidArgumentException('A valid permission name and configured guard are required.');
        }

        /** @var class-string<Permission> $permissionClass */
        $permissionClass = $this->registrar->getPermissionClass();
        $model = new $permissionClass;
        $connection = $model->getConnection();

        return $connection->transaction(function () use ($id, $name, $guard, $model, $connection): Permission {
            $permission = $id === null ? $model : $model->newQuery()->whereKey($id)->lockForUpdate()->firstOrFail();
            $attributes = ['name' => $name, 'guard_name' => $guard];
            $duplicate = $model->newQuery()->where($attributes);

            if ($permission->exists) {
                $duplicate->where($permission->getKeyName(), '!=', $permission->getKey());
            }

            if ($duplicate->exists()) {
                throw PermissionAlreadyExists::create($name, $guard);
            }

            if ($permission->exists && $guard !== $permission->getAttribute('guard_name')) {
                $pivot = $this->registrar->pivotPermission;
                $key = $permission->getKey();
                if ($connection->table((string) config('permission.table_names.role_has_permissions', 'role_has_permissions'))->where($pivot, $key)->exists()
                    || $connection->table((string) config('permission.table_names.model_has_permissions', 'model_has_permissions'))->where($pivot, $key)->exists()) {
                    throw new AuthorizationException('An assigned permission cannot change its guard.');
                }
            }

            $permission->fill($attributes);
            if (! $permission->saveOrFail()) {
                throw new RuntimeException('Authorization definition save was rejected.');
            }

            return $permission;
        });
    }
}
