<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Api;

interface DeleteOrderTracking
{
    public function handle(string $orderId, ?string $ownerType, ?string $ownerId): void;
}
