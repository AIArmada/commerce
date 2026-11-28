<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderAccountingTotals
{
    public function adjust(string $orderId, string $column, int $delta): void;
}
