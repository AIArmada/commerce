<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface CancellationRefundReservations
{
    public function reserveForCancellation(string $orderId, string $reason): void;
}
