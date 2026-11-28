<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

interface ReadNotificationOrder
{
    public function find(string $id, ?string $ownerType, string | int | null $ownerId): ?NotificationOrder;
}
