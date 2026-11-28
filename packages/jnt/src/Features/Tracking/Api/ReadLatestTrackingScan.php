<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Api;

use DateTimeImmutable;

interface ReadLatestTrackingScan
{
    public function forOrder(string $orderId, ?string $ownerType, ?string $ownerId): ?DateTimeImmutable;
}
