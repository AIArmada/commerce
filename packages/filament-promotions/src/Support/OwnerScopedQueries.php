<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Support;

use AIArmada\Promotions\Support\PromotionsOwnerScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Helper for owner-scoped queries in Filament resources.
 */
final class OwnerScopedQueries
{
    /**
     * Scope a promotion query based on owner configuration.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopePromotion(Builder $query): Builder
    {
        return PromotionsOwnerScope::applyToOwnedQuery($query);
    }
}
