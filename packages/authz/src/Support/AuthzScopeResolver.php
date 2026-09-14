<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use AIArmada\Authz\Models\AuthzScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

final class AuthzScopeResolver
{
    public static function resolveId(mixed $scope): string | int | null
    {
        if ($scope === null) {
            return null;
        }

        if ($scope instanceof AuthzScope) {
            return $scope->getKey();
        }

        if ($scope instanceof Model) {
            $scopeableType = $scope->getMorphClass();
            $scopeableId = $scope->getKey();

            if ($scopeableType === '' || $scopeableId === null) {
                return null;
            }

            $label = null;

            if (method_exists($scope, 'getAuthzScopeLabel')) {
                $label = $scope->getAuthzScopeLabel();
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
                return null;
            }

            try {
                $authzScope = AuthzScope::query()->firstOrCreate($attributes, ['label' => $label]);
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }

                return AuthzScope::query()->where($attributes)->value('id');
            }

            if ($label !== null && $authzScope->label !== $label) {
                $authzScope->forceFill(['label' => $label])->save();
            }

            return $authzScope->getKey();
        }

        if (is_string($scope) || is_int($scope)) {
            return $scope;
        }

        return null;
    }
}
