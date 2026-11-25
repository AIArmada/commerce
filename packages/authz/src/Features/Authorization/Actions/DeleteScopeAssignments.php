<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\AuthzScope;
use AIArmada\Authz\Features\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final readonly class DeleteScopeAssignments
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function execute(AuthzScope $scope): void
    {
        if (! $this->registrar->teams) {
            return;
        }

        $teamsKey = (string) $this->registrar->teamsKey;
        /** @var class-string<Model> $roleClass */
        $roleClass = config('permission.models.role', Role::class);
        $role = new $roleClass;
        $roleTable = $role->getTable();
        $roleKey = $role->getKeyName();
        $rolePivotKey = (string) config('permission.column_names.role_pivot_key', 'role_id');
        $roleAssignments = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $permissionAssignments = (string) config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $rolePermissions = (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $connection = $role->getConnection();

        $connection->transaction(function () use ($connection, $scope, $teamsKey, $roleTable, $roleKey, $rolePivotKey, $roleAssignments, $permissionAssignments, $rolePermissions): void {
            $roleIds = $connection->table($roleTable)
                ->where($teamsKey, $scope->getKey())
                ->select($roleKey);

            $connection->table($roleAssignments)
                ->where($teamsKey, $scope->getKey())
                ->delete();
            $connection->table($permissionAssignments)
                ->where($teamsKey, $scope->getKey())
                ->delete();
            $connection->table($roleAssignments)
                ->whereIn($rolePivotKey, clone $roleIds)
                ->delete();
            $connection->table($rolePermissions)
                ->whereIn($rolePivotKey, clone $roleIds)
                ->delete();
            $connection->table($roleTable)
                ->where($teamsKey, $scope->getKey())
                ->delete();
        });

        $this->registrar->forgetCachedPermissions();
    }
}
