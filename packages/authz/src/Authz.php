<?php

declare(strict_types=1);

namespace AIArmada\Authz;

use AIArmada\Authz\Services\PermissionKeyBuilder;
use AIArmada\Authz\Support\AuthzScopeResolver;
use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

class Authz
{
    protected ?Closure $customPermissionKeyBuilder = null;

    public function __construct(
        protected PermissionKeyBuilder $keyBuilder
    ) {}

    public function buildPermissionKeyUsing(Closure $callback): static
    {
        $this->customPermissionKeyBuilder = $callback;

        return $this;
    }

    public function buildPermissionKey(string $subject, string $action): string
    {
        if ($this->customPermissionKeyBuilder !== null) {
            return ($this->customPermissionKeyBuilder)($subject, $action);
        }

        return $this->keyBuilder->build($subject, $action);
    }

    public function getCustomPermissions(): array
    {
        $custom = (array) config('authz.custom_permissions', []);
        $result = [];

        foreach ($custom as $key => $label) {
            if (is_int($key)) {
                $result[$label] = str($label)->headline()->toString();
            } else {
                $result[$key] = $label;
            }
        }

        return $result;
    }

    public function resolveScopeId(mixed $scope): string | int | null
    {
        return AuthzScopeResolver::resolveId($scope);
    }

    public function withScope(mixed $scope, callable $callback, ?Authorizable $user = null): mixed
    {
        $previousScope = getPermissionsTeamId();
        $scopeId = $this->resolveScopeId($scope);

        setPermissionsTeamId($scopeId);
        $this->flushPermissionCache($user);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previousScope);
            $this->flushPermissionCache($user);
        }
    }

    public function userCanInScope(Authorizable $user, string $ability, mixed $scope): bool
    {
        return (bool) $this->withScope($scope, fn (): bool => $user->can($ability), $user);
    }

    public function userHasPermissionAcrossScopes(Authorizable $user, string $ability): bool
    {
        return (bool) $this->withoutTeams(fn (): bool => $user->can($ability), $user);
    }

    public function clearCache(): void
    {
        $this->flushPermissionCache();
    }

    protected function flushPermissionCache(?Authorizable $user = null): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $authUser = $user ?? Auth::user();

        if ($authUser instanceof Model) {
            $authUser->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    protected function withoutTeams(callable $callback, ?Authorizable $user = null): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $teams = $registrar->teams;

        $registrar->teams = false;
        $this->flushPermissionCache($user);

        try {
            return $callback();
        } finally {
            $registrar->teams = $teams;
            $this->flushPermissionCache($user);
        }
    }
}
