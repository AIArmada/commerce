<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Api;

interface DeliveryDependentsCleaner
{
    public function cleanup(string $deliveryId): void;

    public function cleanupCommunication(string $communicationId): void;
}
