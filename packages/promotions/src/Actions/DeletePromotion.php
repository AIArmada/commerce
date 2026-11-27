<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Promotions\Models\Promotion;

final readonly class DeletePromotion
{
    public function handle(Promotion $promotion): bool
    {
        return $promotion->getConnection()->transaction(function () use ($promotion): bool {
            if (config('promotions.features.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Promotion::class, $promotion->getKey());
            }

            $locked = Promotion::query()->whereKey($promotion->getKey())->lockForUpdate()->first();

            return $locked instanceof Promotion && $locked->delete() === true;
        });
    }
}
