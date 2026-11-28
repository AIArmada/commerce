<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

interface SyncStoredTracking
{
    public function execute(string $orderId): void;
}
