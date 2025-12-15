<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Data;

use Spatie\LaravelData\Data;

/**
 * Data Transfer Object representing a calculated price result.
 */
class PriceResultData extends Data
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
        /** @var array<int, array<string, mixed>> */
        public array $breakdown = [],
    ) {}

    /**
     * Check if the final price has a discount.
     */
    public function hasDiscount(): bool
    {
        return $this->discountAmount > 0;
    }

    /**
     * Get the savings as formatted string.
     */
    public function getFormattedSavings(): string
    {
        return 'RM ' . number_format($this->discountAmount / 100, 2);
    }

    /**
     * Get the final price as formatted string.
     */
    public function getFormattedFinalPrice(): string
    {
        return 'RM ' . number_format($this->finalPrice / 100, 2);
    }

    /**
     * Get the original price as formatted string.
     */
    public function getFormattedOriginalPrice(): string
    {
        return 'RM ' . number_format($this->originalPrice / 100, 2);
    }
}
