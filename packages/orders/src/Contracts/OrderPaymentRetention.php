<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderPaymentRetention
{
    /** @param callable(): bool $deleteOrder */
    public function deleteOrder(string $orderId, bool $force, callable $deleteOrder): bool;
}
