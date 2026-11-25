<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Api;

use AIArmada\CommerceSupport\Data\RecordReference;

interface RoleAdministrationApi
{
    /** @return list<string> */
    public function permissionNames(string $guard): array;

    /** @param list<string> $permissions */
    public function ensurePermissions(string $guard, array $permissions): void;

    /** @param list<string> $permissions */
    public function ensureRole(string $name, string $guard, string | int | null $scope, array $permissions, bool $prune = false): RoleRecord;

    public function resolveRecordScope(RecordReference $record): string | int | null;

    public function assignExistingRole(RecordReference $record, string $name, string $guard, string | int | null $scope): bool;

    /**
     * @param  list<string>  $roleIds
     * @param  list<string>|null  $allowedScopeIds
     */
    public function syncUserRoles(RecordReference $record, array $roleIds, string $guard, bool $restrictToCurrentTeam, string $scopeMode, ?array $allowedScopeIds, bool $includeSuperAdmin): void;

    /** @param list<string> $permissionIds */
    public function syncUserPermissions(RecordReference $record, array $permissionIds, string $guard): void;

    /** @param list<string> $permissionNames */
    public function saveRoleDefinition(?string $id, string $name, string $guard, string | int | null $scope, array $permissionNames): RoleRecord;

    public function savePermissionDefinition(?string $id, string $name, string $guard): string;

    public function deleteRoleDefinition(string $id): bool;

    public function deletePermissionDefinition(string $id): bool;

    public function deleteScope(string $id): bool;
}
