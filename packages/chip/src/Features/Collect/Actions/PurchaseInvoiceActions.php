<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Actions;

use AIArmada\Chip\Features\Collect\Api\PurchaseInvoiceOperations;

final readonly class PurchaseInvoiceActions implements PurchaseInvoiceOperations
{
    public function __construct(
        private MarkPurchaseAsPaid $markPaid,
        private ResendPurchaseInvoice $resend,
    ) {}

    public function markPaid(string $purchaseId): void
    {
        $this->markPaid->handle($purchaseId);
    }

    public function resend(string $purchaseId): void
    {
        $this->resend->handle($purchaseId);
    }
}
