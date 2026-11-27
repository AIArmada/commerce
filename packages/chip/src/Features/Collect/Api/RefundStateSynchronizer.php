<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Api;

use AIArmada\Chip\Data\PaymentData;

interface RefundStateSynchronizer
{
    public function handle(PaymentData $refundPayment, ?string $purchaseId = null): void;
}
