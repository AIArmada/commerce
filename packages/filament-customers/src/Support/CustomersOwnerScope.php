<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CustomersOwnerScope
{
    public static function resolveOwner(): ?Model
    {
        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        /** @var OwnerResolverInterface $resolver */
        $resolver = app(OwnerResolverInterface::class);

        return $resolver->resolve();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyToOwnedQuery(Builder $query, bool $includeGlobal = false): Builder
    {
        $owner = self::resolveOwner();

        if (method_exists($query->getModel(), 'scopeForOwner')) {
            /** @var Builder<TModel> $scoped */
            $scoped = $query->getModel()->scopeForOwner($query, $owner, $includeGlobal);

            return $scoped;
        }

        if ($owner === null) {
            return $query->whereNull('owner_type')->whereNull('owner_id');
        }

        return $query->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }
}
