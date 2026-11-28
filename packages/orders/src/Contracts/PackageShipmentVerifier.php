<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface PackageShipmentVerifier
{
    public function hasActivePackageShipment(string $orderId): bool;

    public function allItemsShipped(string $orderId, ?string $shipmentId): bool;
}
