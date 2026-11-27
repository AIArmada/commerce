<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data\Collections;

use AIArmada\Chip\Data\ProductData;
use Akaunting\Money\Money;
use Countable;
use InvalidArgumentException;

final readonly class ProductCollection implements Countable
{
    /** @var list<ProductData> */
    private array $items;

    /** @param iterable<ProductData> $items */
    public function __construct(iterable $items = [])
    {
        $products = [];

        foreach ($items as $product) {
            if (! $product instanceof ProductData) {
                throw new InvalidArgumentException('Every item must be a CHIP product.');
            }

            $products[] = $product;
        }

        $this->items = $products;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getTotalPrice(string $currency = 'MYR'): Money
    {
        return Money::{$currency}($this->getTotalPriceInCents());
    }

    public function getTotalPriceInCents(): int
    {
        return array_reduce(
            $this->items,
            static fn (int $carry, ProductData $product): int => $carry + $product->getTotalPriceInCents(),
            0,
        );
    }

    public function getSubtotalInCents(): int
    {
        return array_reduce(
            $this->items,
            static fn (int $carry, ProductData $product): int => $carry + $product->getSubtotalInCents(),
            0,
        );
    }

    public function getTotalDiscountInCents(): int
    {
        return array_reduce(
            $this->items,
            static fn (int $carry, ProductData $product): int => $carry + $product->getDiscountTotalInCents(),
            0,
        );
    }
}
