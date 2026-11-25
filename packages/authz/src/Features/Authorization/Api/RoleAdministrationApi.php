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
}
