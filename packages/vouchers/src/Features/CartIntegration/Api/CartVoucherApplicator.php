<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Api;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Stacking\Contracts\StackingPolicyInterface;

interface CartVoucherApplicator
{
    public function handle(Cart $cart, string $code, ?StackingPolicyInterface $policy = null, int $order = 100, ?string $expectedVoucherId = null): VoucherCondition;
}
