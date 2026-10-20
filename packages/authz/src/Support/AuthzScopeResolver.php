<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use AIArmada\Authz\Exceptions\ScopeResolutionException;
use AIArmada\Authz\Models\AuthzScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

final class AuthzScopeResolver
{
    /**
     * Resolve a scope reference to an Authz scope identifier.
     *
     * Explicit null is the deliberate global scope. Every other value must
     * resolve to a scope identifier or throw: a scope that cannot be
     * resolved must never fall back to the global permission context.
     *
     * @throws ScopeResolutionException when a non-null scope cannot resolve.
     */
    public static function resolveId(mixed $scope): string | int | null
    {
        if ($scope === null) {
            return null;
        }

        if ($scope instanceof AuthzScope) {
            if ($scope->getKey() === null || ! $scope->exists) {
                throw ScopeResolutionException::forScope($scope, 'the Authz scope record does not exist');
            }

            return $scope->getKey();
        }

        if ($scope instanceof Model) {
            return self::resolveModelId($scope);
        }

        if (is_string($scope) && $scope !== '') {
            return $scope;
        }

        if (is_int($scope)) {
            return $scope;
        }

        throw ScopeResolutionException::forScope(
            $scope,
            'expected a persisted model, an Authz scope record, or a non-empty scope identifier',
        );
    }

    private static function resolveModelId(Model $scope): string | int
    {
        $scopeableType = $scope->getMorphClass();
        $scopeableId = $scope->getKey();

        if ($scopeableType === '' || $scopeableId === null || $scopeableId === '') {
            throw ScopeResolutionException::forScope($scope, 'the model scope is not persisted');
        }

        $attributes = [
            'scopeable_type' => $scopeableType,
            'scopeable_id' => $scopeableId,
        ];

        $existingId = AuthzScope::query()->where($attributes)->value('id');

        if ($existingId !== null) {
            return $existingId;
        }

        if (! config('authz.scopes.auto_create', true)) {
            throw ScopeResolutionException::forScope(
                $scope,
                'no Authz scope exists for the model and automatic scope creation is disabled',
            );
        }

        $label = null;

        if (method_exists($scope, 'getAuthzScopeLabel')) {
            $label = $scope->getAuthzScopeLabel();
        }

        try {
            $authzScope = AuthzScope::query()->firstOrCreate($attributes, ['label' => $label]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existingId = AuthzScope::query()->where($attributes)->value('id');

            if ($existingId === null) {
                throw ScopeResolutionException::forScope($scope, 'the Authz scope could not be created', $exception);
            }

            return $existingId;
        }

        if ($label !== null && $authzScope->label !== $label) {
            $authzScope->forceFill(['label' => $label])->save();
        }

        return $authzScope->getKey();
    }
}
