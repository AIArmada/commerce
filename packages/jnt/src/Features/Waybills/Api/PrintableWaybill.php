<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Waybills\Api;

interface PrintableWaybill
{
    public function url(string $orderId, string | int | null $userId): ?string;
}
