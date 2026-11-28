<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

interface ReadTrackingOrderOwner
{
    public function findForEvent(string $orderId): ?TrackingOrderOwner;

    public function findForLog(string $orderId): ?TrackingOrderOwner;
}
