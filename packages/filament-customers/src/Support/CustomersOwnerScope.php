<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CustomersOwnerScope
{
    public static function resolveOwner(): ?Model
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyToOwnedQuery(Builder $query, bool $includeGlobal = false): Builder
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolveOwner();
        // Filament surfaces must be owner-only by default.
        // If no owner is resolved, we treat rows with owner=null as global-only.
        // If an owner is resolved, we never implicitly include global rows.
        $includeGlobal = false;

        return OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
    }
}
