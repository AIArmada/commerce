<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Exceptions\ScopeResolutionException;
use AIArmada\Authz\Features\Authorization\Models\AuthzScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class EnsureScopeForModel
{
    public function execute(Model $scopeable, ?string $label): AuthzScope
    {
        if (! $scopeable->exists || $scopeable->getKey() === null || $scopeable->getKey() === '') {
            throw ScopeResolutionException::forScope($scopeable, 'the model scope is not persisted');
        }

        $identity = [
            'scopeable_type' => $scopeable->getMorphClass(),
            'scopeable_id' => $scopeable->getKey(),
        ];

        try {
            $scope = AuthzScope::query()->firstOrCreate($identity, ['label' => $label]);
        } catch (UniqueConstraintViolationException $exception) {
            $scope = AuthzScope::query()->where($identity)->first();

            if ($scope === null) {
                throw ScopeResolutionException::forScope($scopeable, 'the Authz scope could not be created', $exception);
            }
        }

        return $scope;
    }
}
