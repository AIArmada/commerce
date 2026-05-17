<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Data;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Spatie\LaravelData\Data;

/**
 * Data Transfer Object representing a calculated price result.
 */
final class PriceResultData extends Data
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
        return MoneyFormatter::formatMinor($this->discountAmount, $this->currency);
    }

    /**
     * Get the final price as formatted string.
     */
    public function getFormattedFinalPrice(): string
    {
        return MoneyFormatter::formatMinor($this->finalPrice, $this->currency);
    }

    /**
     * Get the original price as formatted string.
     */
    public function getFormattedOriginalPrice(): string
    {
        return MoneyFormatter::formatMinor($this->originalPrice, $this->currency);
    }
}
