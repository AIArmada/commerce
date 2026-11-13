<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

/**
 * Immutable quote for one catalog buyable line.
 */
final readonly class BuyablePriceData
{
    /**
     * @param  array<int, array<string, mixed>>  $breakdown
     */
    public function __construct(
        public int $unitPrice,
        public string $currency,
        public ?int $originalUnitPrice = null,
        public int $discountAmount = 0,
        public ?string $discountSource = null,
        public ?string $priceListName = null,
        public ?string $tierDescription = null,
        public ?string $promotionName = null,
        public array $breakdown = [],
    ) {}
}
