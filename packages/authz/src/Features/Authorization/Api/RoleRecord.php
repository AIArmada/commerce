<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Api;

final readonly class RoleRecord
{
    public function __construct(
        public string $id,
        public string $name,
        public string $guard,
        public string | int | null $scope,
    ) {}
}
