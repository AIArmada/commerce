<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\AuthzScope;
use RuntimeException;

final readonly class DeleteAuthzScope
{
    public function __construct(private DeleteScopeAssignments $assignments) {}

    public function execute(string $id): bool
    {
        return (new AuthzScope)->getConnection()->transaction(function () use ($id): bool {
            $scope = AuthzScope::query()->whereKey($id)->lockForUpdate()->first();

            if ($scope === null) {
                return false;
            }

            $this->assignments->execute($scope);

            if (! $scope->delete()) {
                throw new RuntimeException('Authorization scope deletion was rejected.');
            }

            return true;
        });
    }
}
