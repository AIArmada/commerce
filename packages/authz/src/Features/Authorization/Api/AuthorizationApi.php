<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Api;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;

interface AuthorizationApi
{
    public function buildPermissionKeyUsing(Closure $callback): static;

    public function buildPermissionKey(string $subject, string $action): string;

    /** @return array<string, string> */
    public function getCustomPermissions(): array;

    public function resolveScopeId(mixed $scope): string | int | null;

    public function withScope(mixed $scope, callable $callback, ?Authorizable $user = null): mixed;

    public function userCanInScope(Authorizable $user, string $ability, mixed $scope): bool;

    public function userHasPermissionAcrossScopes(Authorizable $user, string $ability): bool;

    public function clearCache(): void;
}
