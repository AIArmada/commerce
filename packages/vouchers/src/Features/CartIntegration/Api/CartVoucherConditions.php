<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Api;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartConditionConvertible;

interface CartVoucherConditions
{
    /** @return array<string, CartConditionConvertible> */
    public function forCart(Cart $cart): array;
}
