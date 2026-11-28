<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Data;

final readonly class PriceResultData
{
    public function __construct(
        public int $originalPrice,
        public int $finalPrice,
        public int $discountAmount,
        public ?string $discountSource = null,
        public ?float $discountPercentage = null,
        public ?string $priceListName = null,
        public ?string $tierDescription = null,
        public ?string $promotionName = null,
        public string $currency = 'MYR',
        /** @var array<int, array<string, mixed>> */
        public array $breakdown = [],
    ) {}

    public function hasDiscount(): bool
    {
        return $this->discountAmount > 0;
    }
}
