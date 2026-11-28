<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Integrations;

use AIArmada\Orders\Contracts\PackageShipmentVerifier;
use AIArmada\Orders\Models\Order;

final readonly class PackageShipmentVerification implements PackageShipmentVerifier
{
    public function __construct(private OrderShipmentUnits $units) {}

    public function hasActivePackageShipment(string $orderId): bool
    {
        return OrderShipmentUnits::hasActivePackageShipment(Order::query()->findOrFail($orderId));
    }

    public function allItemsShipped(string $orderId, ?string $shipmentId): bool
    {
        return $this->units->allItemsShipped(Order::query()->findOrFail($orderId), $shipmentId);
    }
}
