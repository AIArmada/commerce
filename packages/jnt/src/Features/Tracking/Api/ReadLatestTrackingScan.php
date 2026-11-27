<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Api;

interface ReadLatestTrackingScan
{
    public function forOrder(string $orderId, ?string $ownerType, ?string $ownerId): ?string;
}
