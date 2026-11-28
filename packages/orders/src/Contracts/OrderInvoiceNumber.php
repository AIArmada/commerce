<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderInvoiceNumber
{
    public function assign(string $orderId): string;
}
