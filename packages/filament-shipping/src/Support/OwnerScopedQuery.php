<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Support;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class OwnerScopedQuery
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, ?bool $includeGlobal = null): Builder
    {
        try {
            return OwnerUiScope::apply($query, includeGlobal: $includeGlobal);
        } catch (NoCurrentOwnerException | ModelNotFoundException) {
            return $query->withoutGlobalScope(OwnerScope::class)->whereRaw('0 = 1');
        }
    }
}
