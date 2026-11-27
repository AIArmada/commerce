<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Promotions\Events\PromotionDeactivated;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;

final class DeactivatePromotion
{
    public function handle(Promotion $promotion): Promotion
    {
        return $promotion->getConnection()->transaction(function () use ($promotion): Promotion {
            if (config('promotions.features.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Promotion::class, $promotion->getKey());
            }

            $locked = Promotion::query()->whereKey($promotion->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                return $locked;
            }

            $locked->update([
                'is_active' => false,
                'deactivated_at' => CarbonImmutable::now(),
            ]);

            PromotionDeactivated::dispatch($locked);

            return $locked;
        });
    }
}
