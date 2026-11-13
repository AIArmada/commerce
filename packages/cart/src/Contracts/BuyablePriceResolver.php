<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use AIArmada\Cart\Support\BuyablePriceData;
use AIArmada\CommerceSupport\Contracts\Buyable;

/**
 * Optional pricing seam used by Cart to quote catalog buyables.
 *
 * Cart never depends on a pricing package directly. When an implementation is
 * bound in the container, it resolves the unit price with the same policy the
 * checkout pricing step uses.
 */
interface BuyablePriceResolver
{
    /**
     * @param  array<string, mixed>  $context  customer, quantity, and currency context
     */
    public function resolve(Buyable $buyable, int $quantity, array $context = []): BuyablePriceData;
}
