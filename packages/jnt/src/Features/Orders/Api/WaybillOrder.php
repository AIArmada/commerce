<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

final readonly class WaybillOrder
{
    public function __construct(public string $orderId, public ?string $trackingNumber) {}
}
