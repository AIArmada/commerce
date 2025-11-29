<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Invoices;

use AIArmada\CashierChip\Contracts\InvoiceRenderer;
use AIArmada\CashierChip\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class DompdfInvoiceRenderer implements InvoiceRenderer
{
    /**
     * Render the invoice as a PDF.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function render(Invoice $invoice, array $data = [], array $options = []): string
    {
        $dompdfOptions = new Options;
        $dompdfOptions->setChroot(base_path());
        $dompdfOptions->setIsRemoteEnabled(true);
        $dompdfOptions->setDefaultFont('sans-serif');

        $dompdf = new Dompdf($dompdfOptions);

        $html = View::make('cashier-chip::invoice', array_merge($data, [
            'invoice' => $invoice,
            'owner' => $invoice->owner(),
        ]))->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper($options['paper'] ?? $this->paperSize(), 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Get the paper size for the invoice.
     */
    public function paperSize(): string
    {
        return config('cashier-chip.invoices.paper', 'A4');
    }
}
