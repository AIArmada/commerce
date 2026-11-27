<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Shipments\Api;

interface ReadReturnShipment
{
    public function find(string $shipmentId): ReturnShipment;

    /** @return list<ReturnShipmentLine> */
    public function packageLines(string $shipmentId): array;

    /** @return array<int, ReturnShipmentLine> */
    public function unitLines(string $shipmentId, string $orderItemId, int $ordinal): array;
}
