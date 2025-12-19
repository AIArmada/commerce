<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CashierChipOwnerScope
{
    /**
     * Apply owner scoping to a query when the model supports it.
     *
     * Fail-closed when an owner resolver is bound but the model does not support
     * owner scoping (prevents accidental cross-tenant leaks).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, ?Model $owner = null, ?bool $includeGlobal = null): Builder
    {
        $model = $query->getModel();

        if (! method_exists($model, 'scopeForOwner')) {
            $resolvedOwner = $owner ?? self::resolveOwner();

            if ($resolvedOwner !== null || app()->bound(OwnerResolverInterface::class)) {
                return $query->whereKey([]);
            }

            return $query;
        }

        /** @var Builder<TModel> $scoped */
        $scoped = $model->scopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    public static function resolveOwner(): ?Model
    {
        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        return app(OwnerResolverInterface::class)->resolve();
    }
}
