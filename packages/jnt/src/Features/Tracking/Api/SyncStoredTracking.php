<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Api;

interface SyncStoredTracking
{
    public function execute(string $orderId): void;
}
