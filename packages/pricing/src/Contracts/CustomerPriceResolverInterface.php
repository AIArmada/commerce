<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Contracts;

interface CustomerPriceResolverInterface
{
    /**
     * Resolve a customer-specific price for a priceable item.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $priceableType, string $priceableId, int $quantity, array $context): ?int;
}
