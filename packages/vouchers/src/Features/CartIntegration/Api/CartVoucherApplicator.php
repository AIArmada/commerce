<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Api;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartConditionConvertible;

interface CartVoucherApplicator
{
    public function handle(Cart $cart, string $code, int $order = 100, ?string $expectedVoucherId = null): CartConditionConvertible;
}
