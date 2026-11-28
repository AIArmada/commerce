<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderPaymentLifecycle
{
    public function markPaid(string $orderId): void;

    public function markRefunded(string $orderId): void;

    public function markPaymentFailed(string $orderId): void;
}
