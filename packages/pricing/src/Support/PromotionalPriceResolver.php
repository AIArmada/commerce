<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Pricing\Actions\ApplyPromotionalAdjustment;
use Carbon\CarbonImmutable;

final class PromotionalPriceResolver
{
    public function __construct(
        private readonly ApplyPromotionalAdjustment $adjustment,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('pricing.features.promotional.enabled', true);
    }

    /**
     * @return array{price: int, name: string}|null
     */
    public function resolve(string $promotionableType, string $promotionableId, int $basePrice, int $quantity, CarbonImmutable $effectiveAt): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return $this->adjustment->apply($promotionableType, $promotionableId, $basePrice, $quantity, $effectiveAt);
    }
}
