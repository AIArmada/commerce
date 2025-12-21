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
        $includeGlobal = $includeGlobal && (bool) config('customers.features.owner.include_global', false);

        if (method_exists($query->getModel(), 'scopeForOwner')) {
            /** @phpstan-ignore-next-line dynamic scope */
            return $query->forOwner($owner, $includeGlobal);
        }

        return OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
    }
}
