<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Invoices;

use AIArmada\CashierChip\Contracts\InvoiceRenderer;
use AIArmada\CashierChip\Data\InvoiceRenderData;
use AIArmada\Docs\Data\DocData;
use AIArmada\Docs\Services\DocService;

class DocsInvoiceRenderer implements InvoiceRenderer
{
    public function __construct(
        protected DocService $docService
    ) {}

    /**
     * Render the invoice as a PDF using the docs package.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function render(InvoiceRenderData $invoice, array $data = [], array $options = []): string
    {
        $docData = DocData::from([
            'doc_number' => $invoice->number,
            'doc_type' => 'invoice',
            'issue_date' => $invoice->date,
            'due_date' => $invoice->dueDate,
            'items' => $invoice->items,
            'total_minor' => $invoice->totalMinor,
            'currency' => $invoice->currency,
            'customer_data' => [
                'name' => $invoice->customerName,
                'email' => $invoice->customerEmail,
                'phone' => $invoice->customerPhone,
            ],
            'metadata' => array_merge($data, [
                'chip_purchase_id' => $invoice->id,
                'payment_status' => $invoice->status,
            ]),
            'generate_pdf' => false,
        ]);

        $doc = $this->docService->create($docData);

        return $this->docService->generatePdf($doc, false);
    }

    /**
     * Get the paper size for the invoice.
     */
    public function paperSize(): string
    {
        return config('cashier-chip.invoices.paper', 'A4');
    }
}
