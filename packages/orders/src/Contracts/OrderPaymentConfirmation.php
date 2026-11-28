<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderPaymentConfirmation
{
    /** @param array<string, mixed> $metadata */
    public function confirm(string $orderId, string $transactionId, string $gateway, int $amount, array $metadata = []): void;

    public function settleOutstanding(string $orderId, string $transactionId, string $gateway): void;
}
