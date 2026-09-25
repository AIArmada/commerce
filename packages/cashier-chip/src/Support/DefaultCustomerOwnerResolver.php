<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Support;

use AIArmada\CashierChip\Contracts\CustomerOwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Default owner resolver for billable customers.
 *
 * Ownership is proven in order:
 * - the model's own owner tuple when it defines one (global-only rows
 *   under explicit global context),
 * - the owner IS the customer (same class and key),
 * - an owned CHIP customer link,
 * - an owned subscription.
 *
 * Anything else fails closed with no rows. Models without an owner tuple
 * return all rows under explicit global context.
 */
final class DefaultCustomerOwnerResolver implements CustomerOwnerResolverInterface
{
    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeQuery(Builder $query, ?Model $owner): Builder
    {
        $model = $query->getModel();

        if ($model !== null && method_exists($model, 'ownerScopeConfig')) {
            $config = $model->ownerScopeConfig();

            $query->withoutGlobalScope(OwnerScope::class);

            return OwnerQuery::applyToEloquentBuilder(
                $query,
                $owner,
                false,
                $config->ownerTypeColumn,
                $config->ownerIdColumn,
            );
        }

        if ($owner === null) {
            return $query;
        }

        if ($model !== null && is_a($owner, $model::class)) {
            return $query->whereKey($owner->getKey());
        }

        $hasLink = $model !== null && method_exists($model, 'chipCustomerLink');
        $hasSubscriptions = $model !== null
            && (method_exists($model, 'subscriptions') || method_exists($model, 'chipSubscriptions'));

        if (! $hasLink && ! $hasSubscriptions) {
            return $query->whereRaw('1 = 0');
        }

        $includeGlobal = (bool) config('cashier-chip.features.owner.include_global', false);

        return $query->where(function (Builder $constrained) use ($owner, $includeGlobal, $hasLink, $hasSubscriptions, $model): void {
            if ($hasLink) {
                $constrained->orWhereHas('chipCustomerLink', function (Builder $linkQuery) use ($owner, $includeGlobal): void {
                    $linkQuery->withoutGlobalScope(OwnerScope::class);

                    OwnerQuery::applyToEloquentBuilder($linkQuery, $owner, $includeGlobal);
                });
            }

            if ($hasSubscriptions && $model !== null) {
                $relation = method_exists($model, 'subscriptions') ? 'subscriptions' : 'chipSubscriptions';

                $constrained->orWhereHas($relation, function (Builder $subscriptionQuery) use ($owner, $includeGlobal): void {
                    $subscriptionQuery->withoutGlobalScope(OwnerScope::class);

                    OwnerQuery::applyToEloquentBuilder($subscriptionQuery, $owner, $includeGlobal);
                });
            }
        });
    }

    public function canAccess(Model $record, ?Model $owner): bool
    {
        return $this->scopeQuery($record->newQuery()->whereKey($record->getKey()), $owner)->exists();
    }
}
