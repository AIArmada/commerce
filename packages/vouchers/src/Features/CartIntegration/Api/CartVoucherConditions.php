<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Api;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;

interface CartVoucherConditions
{
    /** @return array<string, VoucherCondition> */
    public function forCart(Cart $cart): array;
}
