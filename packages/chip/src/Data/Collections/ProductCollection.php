<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data\Collections;

use AIArmada\Chip\Data\ProductData;
use Akaunting\Money\Money;
use Spatie\LaravelData\DataCollection;

/**
 * Type-safe collection of ProductData objects.
 *
 * @extends DataCollection<int, ProductData>
 */
final class ProductCollection extends DataCollection
{
    public function __construct(
        iterable $items = [],
    ) {
        parent::__construct(ProductData::class, $items);
    }

    /**
     * Calculate the total price of all products in the collection.
     */
    public function getTotalPrice(string $currency = 'MYR'): Money
    {
        $totalCents = 0;

        foreach ($this->items as $product) {
            $totalCents += $product->getTotalPriceInCents();
        }

        return Money::{$currency}($totalCents);
    }

    /**
     * Calculate the total price in cents for API communication.
     */
    public function getTotalPriceInCents(): int
    {
        return (int) array_reduce(
            iterator_to_array($this->items),
            fn (int $carry, ProductData $product) => $carry + $product->getTotalPriceInCents(),
            0
        );
    }

    /**
     * Get the subtotal (sum of all product prices × quantities) in cents.
     */
    public function getSubtotalInCents(): int
    {
        return (int) array_reduce(
            iterator_to_array($this->items),
            fn (int $carry, ProductData $product) => $carry + ($product->getPriceInCents() * (float) $product->quantity),
            0
        );
    }

    /**
     * Get the total discount in cents.
     */
    public function getTotalDiscountInCents(): int
    {
        return (int) array_reduce(
            iterator_to_array($this->items),
            fn (int $carry, ProductData $product) => $carry + ($product->getDiscountInCents() * (float) $product->quantity),
            0
        );
    }
}
