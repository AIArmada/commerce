<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Actions;

use AIArmada\Affiliates\Features\Commissions\Api\PromotionUsage;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;

final readonly class ConsumePromotionUsage implements PromotionUsage
{
    public function consume(string $promotionId): bool
    {
        return AffiliateCommissionPromotion::query()->active()->whereKey($promotionId)->increment('current_uses') === 1;
    }
}
