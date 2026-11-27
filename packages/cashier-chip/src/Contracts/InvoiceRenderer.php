<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Contracts;

use AIArmada\CashierChip\Data\InvoiceRenderData;

interface InvoiceRenderer
{
    /**
     * Render the invoice as a PDF.
     */
    public function render(InvoiceRenderData $invoice, array $data = [], array $options = []): string;

    /**
     * Get the paper size for the invoice.
     */
    public function paperSize(): string;
}
