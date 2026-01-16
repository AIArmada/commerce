<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Support;

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
        $ownerEnabled = config('promotions.features.owner.enabled', false);

        if (! $ownerEnabled) {
            return $query;
        }

        $includeGlobal = config('promotions.features.owner.include_global', true);

        if ($includeGlobal) {
            return $query;
        }

        return $query->whereNotNull('owner_id');
    }
}
