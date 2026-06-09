<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Contracts;

interface SegmentPriceResolverInterface
{
    /**
     * Resolve a segment-specific price for a priceable item.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $priceableType, string $priceableId, int $quantity, array $context): ?int;
}
