<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutReversal
{
    public function reverse(string $payoutId): bool;
}
