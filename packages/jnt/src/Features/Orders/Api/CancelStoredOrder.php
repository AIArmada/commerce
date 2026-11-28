<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

interface CancelStoredOrder
{
    public function execute(string $orderId, string $reason): void;
}
