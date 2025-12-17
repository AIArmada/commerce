<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PricingOwnerScope
{
    public static function isEnabled(): bool
    {
        return (bool) config('pricing.features.owner.enabled', false);
    }

    public static function includeGlobal(): bool
    {
        return (bool) config('pricing.features.owner.include_global', true);
    }

    public static function resolveOwner(): ?Model
    {
        if (! self::isEnabled()) {
            return null;
        }

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
    public static function applyToOwnedQuery(Builder $query): Builder
    {
        if (! self::isEnabled()) {
            return $query;
        }

        $owner = self::resolveOwner();

        if ($owner === null) {
            return $query->whereNull('owner_type')->whereNull('owner_id');
        }

        /** @phpstan-ignore-next-line dynamic scope from HasOwner trait */
        return $query->forOwner($owner, self::includeGlobal());
    }
}
