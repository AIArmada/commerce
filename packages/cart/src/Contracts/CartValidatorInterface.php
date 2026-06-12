<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;

interface CartValidatorInterface
{
    public function validateCart(Cart $cart): CartValidationResult;

    public function validateItem(CartItem $item, Cart $cart): CartValidationResult;

    public function getType(): string;

    public function getPriority(): int;
}
