<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
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
        if (! (bool) config('cashier-chip.features.owner.enabled', true)) {
            return $query;
        }

        $model = $query->getModel();
        $owner ??= self::resolveOwner();
        $includeGlobal ??= (bool) config('cashier-chip.features.owner.include_global', false);

        if (! method_exists($model, 'scopeForOwner')) {
            return $query->whereKey([]);
        }

        /** @phpstan-ignore-next-line dynamic scope */
        return $query->forOwner($owner, $includeGlobal);
    }

    public static function resolveOwner(): ?Model
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', true)) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
