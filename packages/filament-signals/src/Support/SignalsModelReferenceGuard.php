<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class SignalsModelReferenceGuard
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    public function findOrFail(
        string $modelClass,
        string $id,
        bool $includeGlobal = false,
        ?string $message = null,
    ): Model {
        if (! method_exists($modelClass, 'ownerScopeConfig')) {
            throw new InvalidArgumentException(sprintf('%s does not expose owner scope configuration.', $modelClass));
        }

        $config = $modelClass::ownerScopeConfig();

        if ($config->enabled) {
            /** @var TModel $model */
            $model = OwnerWriteGuard::findOrFailForOwner(
                $modelClass,
                $id,
                includeGlobal: $includeGlobal,
                message: $message,
            );

            return $model;
        }

        /** @var TModel|null $model */
        $model = $modelClass::query()->whereKey($id)->first();

        if ($model instanceof $modelClass) {
            return $model;
        }

        throw new AuthorizationException($message ?? 'Referenced record is not accessible.');
    }
}
