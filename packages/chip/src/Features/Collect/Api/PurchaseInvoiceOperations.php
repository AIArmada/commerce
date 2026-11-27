<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Api;

interface PurchaseInvoiceOperations
{
    public function markPaid(string $purchaseId): void;

    public function resend(string $purchaseId): void;
}
