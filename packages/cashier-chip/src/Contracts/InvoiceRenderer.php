<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Contracts;

use AIArmada\CashierChip\Invoice;

interface InvoiceRenderer
{
    /**
     * Render the invoice as a PDF.
     */
    public function render(Invoice $invoice, array $data = [], array $options = []): string;

    /**
     * Get the paper size for the invoice.
     */
    public function paperSize(): string;
}
