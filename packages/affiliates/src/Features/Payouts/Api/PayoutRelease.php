<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutRelease
{
    public function release(string $payoutId): void;
}
