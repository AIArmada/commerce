<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

interface DynamicCheckoutBuilderContract extends CheckoutBuilderContract
{
    public function product(string $name, int $priceInCents, int $quantity = 1): static;
}
