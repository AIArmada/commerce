<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Contracts;

use AIArmada\Pricing\Data\PriceResultData;

/**
 * Contract for price calculation services.
 */
interface PriceCalculatorInterface
{
    /**
     * Calculate the final price for a priceable item.
     *
     * @param  Priceable  $item  The item to calculate price for
     * @param  int  $quantity  The quantity being purchased
     * @param  array<string, mixed>  $context  Additional context (customer_id, effective_at, etc.)
     */
    public function calculate(Priceable $item, int $quantity = 1, array $context = []): PriceResultData;

    /**
     * Calculate prices for many lines sharing one context.
     *
     * @param  array<int, array{item: Priceable, quantity?: int}>  $lines
     * @param  array<string, mixed>  $context
     * @return array<int, PriceResultData>
     */
    public function calculateMany(array $lines, array $context = []): array;
}
