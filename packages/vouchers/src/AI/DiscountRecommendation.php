<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI;

use AIArmada\Vouchers\AI\Enums\DiscountStrategy;

/**
 * Value object representing a discount optimization recommendation.
 *
 * @property-read int $recommendedDiscountCents Recommended discount amount in cents
 * @property-read DiscountStrategy $recommendedStrategy Recommended discount type
 * @property-read float $expectedConversionLift Expected increase in conversion rate
 * @property-read float $expectedMarginImpact Expected impact on margin
 * @property-read float $expectedROI Expected return on investment
 * @property-read array<int, array<string, mixed>> $alternatives Alternative recommendations
 */
final readonly class DiscountRecommendation
{
    /**
     * @param  array<int, array<string, mixed>>  $alternatives
     */
    public function __construct(
        public int $recommendedDiscountCents,
        public DiscountStrategy $recommendedStrategy,
        public float $expectedConversionLift,
        public float $expectedMarginImpact,
        public float $expectedROI,
        public array $alternatives = [],
    ) {}

    /**
     * Create a recommendation for no discount.
     */
    public static function noDiscount(): self
    {
        return new self(
            recommendedDiscountCents: 0,
            recommendedStrategy: DiscountStrategy::Percentage,
            expectedConversionLift: 0.0,
            expectedMarginImpact: 0.0,
            expectedROI: 0.0,
        );
    }

    /**
     * Create a percentage discount recommendation.
     */
    public static function percentage(
        int $amountCents,
        float $conversionLift = 0.1,
        float $roi = 2.0,
    ): self {
        return new self(
            recommendedDiscountCents: $amountCents,
            recommendedStrategy: DiscountStrategy::Percentage,
            expectedConversionLift: $conversionLift,
            expectedMarginImpact: -($amountCents / 100),
            expectedROI: $roi,
        );
    }

    /**
     * Create a fixed amount discount recommendation.
     */
    public static function fixedAmount(
        int $amountCents,
        float $conversionLift = 0.08,
        float $roi = 1.5,
    ): self {
        return new self(
            recommendedDiscountCents: $amountCents,
            recommendedStrategy: DiscountStrategy::FixedAmount,
            expectedConversionLift: $conversionLift,
            expectedMarginImpact: -($amountCents / 100),
            expectedROI: $roi,
        );
    }

    /**
     * Check if a discount is recommended.
     */
    public function hasDiscount(): bool
    {
        return $this->recommendedDiscountCents > 0;
    }

    /**
     * Check if the recommendation is profitable.
     */
    public function isProfitable(): bool
    {
        return $this->expectedROI > 1.0;
    }

    /**
     * Check if this is a high-value recommendation.
     */
    public function isHighValue(): bool
    {
        return $this->expectedROI >= 2.0 && $this->expectedConversionLift >= 0.15;
    }

    /**
     * Get the discount as a formatted currency string.
     */
    public function getFormattedDiscount(string $currencySymbol = '$'): string
    {
        $amount = $this->recommendedDiscountCents / 100;

        return $currencySymbol.number_format($amount, 2);
    }

    /**
     * Get the expected net gain in cents.
     */
    public function getExpectedNetGainCents(int $cartValueCents): int
    {
        $incrementalRevenue = (int) ($cartValueCents * $this->expectedConversionLift);
        $discountCost = $this->recommendedDiscountCents;

        return $incrementalRevenue - $discountCost;
    }

    /**
     * Check if there are alternatives available.
     */
    public function hasAlternatives(): bool
    {
        return count($this->alternatives) > 0;
    }

    /**
     * Get the best alternative recommendation.
     *
     * @return array<string, mixed>|null
     */
    public function getBestAlternative(): ?array
    {
        if (! $this->hasAlternatives()) {
            return null;
        }

        return $this->alternatives[0] ?? null;
    }

    /**
     * Get a summary of the recommendation.
     */
    public function getSummary(): string
    {
        if (! $this->hasDiscount()) {
            return 'No discount recommended';
        }

        $discount = $this->getFormattedDiscount();
        $lift = round($this->expectedConversionLift * 100);
        $roi = round($this->expectedROI, 1);

        return "{$this->recommendedStrategy->getLabel()}: {$discount} (lift: {$lift}%, ROI: {$roi}x)";
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'recommended_discount_cents' => $this->recommendedDiscountCents,
            'recommended_strategy' => $this->recommendedStrategy->value,
            'expected_conversion_lift' => $this->expectedConversionLift,
            'expected_margin_impact' => $this->expectedMarginImpact,
            'expected_roi' => $this->expectedROI,
            'alternatives' => $this->alternatives,
            'has_discount' => $this->hasDiscount(),
            'is_profitable' => $this->isProfitable(),
        ];
    }
}
