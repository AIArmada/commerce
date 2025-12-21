<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final class OwnerWriteGuard
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    public static function findOrFailForOwner(
        string $modelClass,
        int|string $id,
        ?Model $owner = null,
        bool $includeGlobal = false,
        ?string $message = null,
    ): Model {
        /** @var \Illuminate\Database\Eloquent\Builder<TModel> $query */
        $query = $modelClass::query();

        if (method_exists($modelClass, 'scopeForOwner')) {
            $query->forOwner($owner, $includeGlobal);
        }

        $model = $query->whereKey($id)->first();

        if ($model !== null) {
            return $model;
        }

        throw new AuthorizationException($message ?? 'Referenced record is not accessible in the current owner scope.');
    }
}
