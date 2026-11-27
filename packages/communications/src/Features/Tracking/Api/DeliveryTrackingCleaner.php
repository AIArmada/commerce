<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Tracking\Api;

interface DeliveryTrackingCleaner
{
    public function cleanup(string $deliveryId): void;
}
