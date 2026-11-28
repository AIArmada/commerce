<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderDocuments
{
    public function issue(string $orderId, string $type, string $transactionId, string $gateway): ?string;
}
