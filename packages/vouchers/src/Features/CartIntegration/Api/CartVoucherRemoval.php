<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Api;

use AIArmada\Cart\Cart;

interface CartVoucherRemoval
{
    public function handle(Cart $cart, string $code): void;
}
