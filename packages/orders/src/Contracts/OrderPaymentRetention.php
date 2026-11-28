<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderPaymentRetention
{
    public function removeForOrder(string $orderId, bool $force = false): void;
}
