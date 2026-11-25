<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization;

use AIArmada\Authz\Features\Authorization\Actions\AssignExistingRole;
use AIArmada\Authz\Features\Authorization\Actions\AssignUserRole;
use AIArmada\Authz\Features\Authorization\Actions\DeleteAuthzScope;
use AIArmada\Authz\Features\Authorization\Actions\DeletePermissionDefinition;
use AIArmada\Authz\Features\Authorization\Actions\DeleteRoleDefinition;
use AIArmada\Authz\Features\Authorization\Actions\EnsurePermissions;
use AIArmada\Authz\Features\Authorization\Actions\EnsureRoleDefinition;
use AIArmada\Authz\Features\Authorization\Actions\RevokeUserRole;
use AIArmada\Authz\Features\Authorization\Actions\SavePermissionDefinition;
use AIArmada\Authz\Features\Authorization\Actions\SaveRoleDefinition;
use AIArmada\Authz\Features\Authorization\Actions\SyncUserPermissions;
use AIArmada\Authz\Features\Authorization\Actions\SyncUserRoles;
use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\Authz\Features\Authorization\Api\RoleRecord;
use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Authz\Features\Authorization\Support\AuthzScopeResolver;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\PermissionRegistrar;

final readonly class RoleAdministration implements RoleAdministrationApi
{
    public function __construct(
        private EnsureRoleDefinition $roles,
        private EnsurePermissions $permissions,
        private AssignExistingRole $assign,
        private PermissionRegistrar $registrar,
        private SyncUserRoles $userRoles,
        private SyncUserPermissions $userPermissions,
        private SaveRoleDefinition $saveRole,
        private SavePermissionDefinition $savePermission,
        private DeleteRoleDefinition $deleteRole,
        private DeletePermissionDefinition $deletePermission,
        private DeleteAuthzScope $deleteScope,
        private AssignUserRole $assignUserRole,
        private RevokeUserRole $revokeUserRole,
    ) {}

    public function permissionNames(string $guard): array
    {
        return Permission::query()->where('guard_name', $guard)->pluck('name')->all();
    }

    public function ensurePermissions(string $guard, array $permissions): void
    {
        $this->permissions->execute($guard, $permissions);
    }

    public function ensureRole(string $name, string $guard, string | int | null $scope, array $permissions, bool $prune = false): RoleRecord
    {
        $role = $this->roles->execute($name, $guard, $scope, $permissions, $prune);

        return new RoleRecord((string) $role->getKey(), $name, $guard, $this->registrar->teams ? $scope : null);
    }

    public function resolveRecordScope(RecordReference $record): string | int | null
    {
        return AuthzScopeResolver::resolveId(EloquentRecordReference::resolve($record));
    }

    public function assignExistingRole(RecordReference $record, string $name, string $guard, string | int | null $scope): bool
    {
        return $this->assign->execute($record, $name, $guard, $scope);
    }

    public function assignRole(RecordReference $record, string $roleId): void
    {
        $this->assignUserRole->execute($this->resolveUser($record), $roleId);
    }

    public function revokeRole(RecordReference $record, string $roleName): void
    {
        $this->revokeUserRole->execute($this->resolveUser($record), $roleName);
    }

    public function syncUserRoles(RecordReference $record, array $roleIds, string $guard, bool $restrictToCurrentTeam, string $scopeMode, ?array $allowedScopeIds, bool $includeSuperAdmin): void
    {
        $this->userRoles->execute($record, $roleIds, $guard, $restrictToCurrentTeam, $scopeMode, $allowedScopeIds, $includeSuperAdmin);
    }

    public function syncUserPermissions(RecordReference $record, array $permissionIds, string $guard): void
    {
        $this->userPermissions->execute($record, $permissionIds, $guard);
    }

    public function saveRoleDefinition(?string $id, string $name, string $guard, string | int | null $scope, array $permissionNames): RoleRecord
    {
        $role = $this->saveRole->execute($id, $name, $guard, $scope, $permissionNames);
        $scopeValue = $role->getAttribute($this->registrar->teamsKey);

        return new RoleRecord(
            (string) $role->getKey(),
            (string) $role->name,
            (string) $role->guard_name,
            $this->registrar->teams && (is_string($scopeValue) || is_int($scopeValue)) ? $scopeValue : null,
        );
    }

    public function savePermissionDefinition(?string $id, string $name, string $guard): string
    {
        return (string) $this->savePermission->execute($id, $name, $guard)->getKey();
    }

    public function deleteRoleDefinition(string $id): bool
    {
        return $this->deleteRole->execute($id);
    }

    public function deletePermissionDefinition(string $id): bool
    {
        return $this->deletePermission->execute($id);
    }

    public function deleteScope(string $id): bool
    {
        return $this->deleteScope->execute($id);
    }

    private function resolveUser(RecordReference $record): Model
    {
        try {
            $user = EloquentRecordReference::resolve($record);
        } catch (ModelNotFoundException) {
            throw new AuthorizationException('The selected user does not exist.');
        }

        if (! $user instanceof Model) {
            throw new AuthorizationException('The selected user does not exist.');
        }

        return $user;
    }
}
