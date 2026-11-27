<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\Promotions\Models\Promotion;

final readonly class DeletePromotionTargets
{
    public function handle(Promotion $promotion): void
    {
        $promotion->getConnection()->table((string) config('promotions.database.tables.promotionables', 'promotionables'))
            ->where('promotion_id', $promotion->getKey())
            ->delete();
    }
}
