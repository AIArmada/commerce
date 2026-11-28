<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;

final readonly class IncrementPromotionUsage
{
    public function handle(Promotion $promotion): bool
    {
        $query = Promotion::query()->whereKey($promotion->getKey())
            ->where(function (Builder $builder): void {
                $builder->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            });

        if (config('promotions.features.owner.enabled', false)) {
            $owned = OwnerWriteGuard::findOrFailForOwner(Promotion::class, $promotion->getKey());
            $query->where('owner_type', $owned->owner_type)
                ->where('owner_id', $owned->owner_id);
        }

        if ($query->increment('usage_count') !== 1) {
            return false;
        }

        $promotion->refresh();

        return true;
    }
}
