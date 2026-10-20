<?php

declare(strict_types=1);

namespace AIArmada\Authz;

use AIArmada\Authz\Exceptions\ScopeResolutionException;
use AIArmada\Authz\Exceptions\ScopeRestorationException;
use AIArmada\Authz\Services\PermissionKeyBuilder;
use AIArmada\Authz\Support\AuthzScopeContext;
use AIArmada\Authz\Support\AuthzScopeResolver;
use AIArmada\Authz\Support\AuthzScopeTeamResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContextTeamResolver;
use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

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

    /**
     * Resolve a scope reference to identifier in the active team namespace.
     *
     * @throws ScopeResolutionException when a non-null scope cannot resolve.
     */
    public function resolveScopeId(mixed $scope): string | int | null
    {
        return match ($this->teamResolverMode()) {
            'owner-context' => $this->resolveOwnerContextScope($scope)?->getKey(),
            'authz-scope' => AuthzScopeResolver::resolveId($scope),
            default => $this->resolveGenericTeamId($scope),
        };
    }

    /**
     * Run a callback inside the requested scope.
     *
     * Explicit null runs inside the deliberate global scope. A non-null scope
     * that cannot resolve throws before any callback or team switch happens.
     *
     * @throws ScopeResolutionException when a non-null scope cannot resolve.
     */
    public function withScope(mixed $scope, callable $callback, ?Authorizable $user = null): mixed
    {
        return match ($this->teamResolverMode()) {
            'owner-context' => $this->runWithOwnerContextScope($this->resolveOwnerContextScope($scope), $callback, $user),
            'authz-scope' => $this->runWithAuthzScope(AuthzScopeResolver::resolveId($scope), $callback, $user),
            default => $this->runWithGenericTeamResolver($this->resolveGenericTeamId($scope), $callback, $user),
        };
    }

    /**
     * Check a boolean ability inside the requested scope.
     *
     * Fails closed when the helper itself cannot resolve the scope or the
     * active resolver cannot honor the requested team. Exceptions raised by
     * the ability/policy callback propagate unchanged in every resolver mode,
     * including an exception whose class is ScopeResolutionException; the
     * previous team/owner/scope state is restored before they surface.
     */
    public function userCanInScope(Authorizable $user, string $ability, mixed $scope): bool
    {
        $mode = $this->teamResolverMode();

        if ($mode === 'owner-context') {
            try {
                $owner = $this->resolveOwnerContextScope($scope);
            } catch (ScopeResolutionException) {
                return false;
            }

            return (bool) $this->runWithOwnerContextScope($owner, fn (): bool => $user->can($ability), $user);
        }

        if ($mode === 'authz-scope') {
            try {
                $scopeId = AuthzScopeResolver::resolveId($scope);
            } catch (ScopeResolutionException) {
                return false;
            }

            return (bool) $this->runWithAuthzScope($scopeId, fn (): bool => $user->can($ability), $user);
        }

        try {
            $restore = $this->applyGenericTeamScope($this->resolveGenericTeamId($scope), $user);
        } catch (ScopeResolutionException) {
            return false;
        }

        try {
            $result = (bool) $user->can($ability);
        } catch (Throwable $failure) {
            $restore($failure);

            throw $failure;
        }

        $restore();

        return $result;
    }

    public function userHasPermissionAcrossScopes(Authorizable $user, string $ability): bool
    {
        return (bool) $this->withoutTeams(fn (): bool => $user->can($ability), $user);
    }

    public function clearCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->resetScopeState();
    }

    protected function runWithAuthzScope(string | int | null $scopeId, callable $callback, ?Authorizable $user): mixed
    {
        $this->resetScopeState($user);

        try {
            return AuthzScopeContext::withScope($scopeId, $callback);
        } finally {
            $this->resetScopeState($user);
        }
    }

    protected function runWithOwnerContextScope(?Model $owner, callable $callback, ?Authorizable $user): mixed
    {
        $this->resetScopeState($user);

        try {
            return OwnerContext::withOwner($owner, $callback);
        } finally {
            $this->resetScopeState($user);
        }
    }

    /**
     * Run a callback under a team id managed through the Spatie team API.
     *
     * Used for Spatie's default resolver and custom resolvers that store the
     * active team on the resolver itself. The previous team id is restored
     * even when the callback throws, and a resolver that cannot honor the
     * requested team fails before the callback runs: it must never execute
     * under the previous team. Callback exceptions propagate unchanged, and a
     * callback failure is preserved in the chain when the exit restore cannot
     * be honored.
     */
    protected function runWithGenericTeamResolver(string | int | null $teamId, callable $callback, ?Authorizable $user): mixed
    {
        $restore = $this->applyGenericTeamScope($teamId, $user);

        try {
            $result = $callback();
        } catch (Throwable $failure) {
            $restore($failure);

            throw $failure;
        }

        $restore();

        return $result;
    }

    /**
     * Apply a team id through the Spatie team API and return a restore closure.
     *
     * The setup rejection is signalled separately from scoped work: this
     * method throws when the active resolver cannot honor the requested team,
     * while exceptions raised afterwards by the caller's callback are not
     * classified as a denied scope. A resolver that changes state and then
     * fails is rolled back to the captured team id, and the caller's
     * request-local permission state is invalidated, before the original
     * failure propagates. A rollback the resolver cannot honor throws
     * ScopeRestorationException so the caller never continues under an
     * unknown scope, with the original setup or callback failure preserved in
     * the chain. The returned closure restores the previous team id and the
     * caller's request-local permission state.
     *
     * @return Closure(?Throwable=): void
     *
     * @throws ScopeResolutionException when the resolver cannot honor the team.
     * @throws ScopeRestorationException when a failed switch cannot be rolled back.
     */
    protected function applyGenericTeamScope(string | int | null $teamId, ?Authorizable $user): Closure
    {
        $this->resetScopeState($user);

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        try {
            $this->switchGenericTeamId($registrar, $teamId);
        } catch (Throwable $failure) {
            try {
                $this->switchGenericTeamId($registrar, $previousTeamId);
            } catch (Throwable $restorationFailure) {
                $this->resetScopeState($user);

                throw ScopeRestorationException::forScope($previousTeamId, $restorationFailure, $failure);
            }

            $this->resetScopeState($user);

            throw $failure;
        }

        return function (?Throwable $callbackFailure = null) use ($registrar, $previousTeamId, $user): void {
            try {
                $this->switchGenericTeamId($registrar, $previousTeamId);
            } catch (Throwable $restorationFailure) {
                throw ScopeRestorationException::forScope($previousTeamId, $restorationFailure, $callbackFailure);
            } finally {
                $this->resetScopeState($user);
            }
        };
    }

    /**
     * Switch the Spatie team id and verify the active resolver honored it.
     *
     * @throws ScopeResolutionException when the resolver cannot honor the team id.
     */
    protected function switchGenericTeamId(PermissionRegistrar $registrar, string | int | null $teamId): void
    {
        $registrar->setPermissionsTeamId($teamId);

        if ((string) $registrar->getPermissionsTeamId() !== (string) $teamId) {
            throw ScopeResolutionException::forScope(
                $teamId,
                'the active team resolver cannot honor the requested scope',
            );
        }
    }

    /**
     * Resolve a scope reference into a team id for the generic Spatie team API.
     *
     * Explicit null is the deliberate global team. Persisted models resolve to
     * their key, matching Spatie's own team-id convention.
     *
     * @throws ScopeResolutionException when a non-null scope cannot resolve.
     */
    protected function resolveGenericTeamId(mixed $scope): string | int | null
    {
        if ($scope === null) {
            return null;
        }

        if ($scope instanceof Model) {
            $key = $scope->getKey();

            if ($key === null || $key === '') {
                throw ScopeResolutionException::forScope($scope, 'the team scope model is not persisted');
            }

            return $key;
        }

        if (is_string($scope)) {
            if ($scope === '') {
                throw ScopeResolutionException::forScope($scope, 'the team scope identifier is an empty string');
            }

            return $scope;
        }

        if (is_int($scope)) {
            return $scope;
        }

        throw ScopeResolutionException::forScope(
            $scope,
            'expected a persisted team model, a non-empty team identifier, or null for explicit global',
        );
    }

    /**
     * Resolve a scope reference into an owner-context owner.
     *
     * Explicit null selects the deliberate global context. Scalars are owner
     * identifiers in the configured owner team namespace; they are never
     * resolved as Authz scope records.
     *
     * @throws ScopeResolutionException when a non-null scope cannot resolve.
     */
    protected function resolveOwnerContextScope(mixed $scope): ?Model
    {
        if ($scope === null) {
            return null;
        }

        if ($scope instanceof Model) {
            if ($scope->getKey() === null || $scope->getKey() === '') {
                throw ScopeResolutionException::forScope($scope, 'the owner scope model is not persisted');
            }

            return $scope;
        }

        if (is_string($scope) || is_int($scope)) {
            if (is_string($scope) && $scope === '') {
                throw ScopeResolutionException::forScope($scope, 'the owner scope identifier is an empty string');
            }

            return $this->resolveOwnerFromIdentifier($scope);
        }

        throw ScopeResolutionException::forScope(
            $scope,
            'expected a persisted owner model, a non-empty owner identifier, or null for explicit global',
        );
    }

    /**
     * @throws ScopeResolutionException when the owner namespace cannot resolve.
     */
    protected function resolveOwnerFromIdentifier(string | int $identifier): Model
    {
        $teamType = config('commerce-support.owner.team_type');

        if (! is_string($teamType) || $teamType === '') {
            throw ScopeResolutionException::forScope(
                $identifier,
                'commerce-support.owner.team_type must be configured to resolve an owner identifier',
            );
        }

        try {
            $owner = OwnerContext::fromTypeAndId($teamType, $identifier);
        } catch (InvalidArgumentException $exception) {
            throw ScopeResolutionException::forScope($identifier, $exception->getMessage(), $exception);
        }

        if (! $owner instanceof Model) {
            throw ScopeResolutionException::forScope($identifier, 'the owner identifier could not be resolved');
        }

        return $owner;
    }

    /**
     * Determine which team-resolver strategy the registrar is using.
     *
     * The registrar builds its team resolver from permission.team_resolver,
     * so that configured class is the authoritative dispatch source: the
     * built-in Authz and owner-context resolvers read their package context,
     * while Spatie's default resolver and custom resolvers are driven through
     * the Spatie team API.
     */
    protected function teamResolverMode(): string
    {
        $resolver = config('permission.team_resolver');

        if (! is_string($resolver) || $resolver === '') {
            return 'generic';
        }

        if (is_a($resolver, OwnerContextTeamResolver::class, true)) {
            return 'owner-context';
        }

        if (is_a($resolver, AuthzScopeTeamResolver::class, true)) {
            return 'authz-scope';
        }

        return 'generic';
    }

    /**
     * Reset request-local permission state around a team-scope switch.
     *
     * Spatie applies team scoping at query time, so a global shared-cache
     * flush is unnecessary here; clearing the user's loaded relations ensures
     * the next check queries the switched team instead of reusing relations
     * loaded under the previous team.
     */
    protected function resetScopeState(?Authorizable $user = null): void
    {
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
        $this->resetScopeState($user);

        try {
            return $callback();
        } finally {
            $registrar->teams = $teams;
            $this->resetScopeState($user);
        }
    }
}
