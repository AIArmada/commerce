<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface RestoreOrderStock
{
    public function restore(string $orderId, string $orderNumber): int;
}
