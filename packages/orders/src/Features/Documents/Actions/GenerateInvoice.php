<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Documents\Actions;

use AIArmada\Orders\Contracts\OrderInvoiceNumber;
use AIArmada\Orders\Features\Documents\Actions\Concerns\BuildsOrderDocs;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Runtime\OrderInvoiceDownload;
use Carbon\CarbonImmutable;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generate PDF invoice for an order.
 */
final class GenerateInvoice implements OrderInvoiceDownload
{
    use BuildsOrderDocs;

    /**
     * Generate and save invoice to a path.
     */
    public function save(Order $order, string $path): string
    {
        $this->buildPdf($order)->save($path);

        return $path;
    }

    /**
     * Generate and download invoice.
     */
    public function download(string $orderId): PdfBuilder | StreamedResponse
    {
        $order = Order::query()
            ->forOwner(includeGlobal: (bool) config('orders.owner.include_global', false))
            ->findOrFail($orderId);

        if (! $this->hasPdfRuntime()) {
            return $this->downloadOrderHtmlFallback(
                order: $order,
                view: 'orders::pdf.invoice',
                filename: "invoice-{$order->order_number}.html",
                data: $this->documentData($order),
            );
        }

        return $this->buildPdf($order)->download();
    }

    /**
     * Build the PDF builder instance.
     */
    protected function buildPdf(Order $order): PdfBuilder
    {
        return $this->buildOrderPdf(
            order: $order,
            view: 'orders::pdf.invoice',
            filename: "invoice-{$order->order_number}.pdf",
            data: $this->documentData($order),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Order $order): array
    {
        return $this->runWithinOrderOwnerScope($order, function () use ($order): array {
            return [
                'invoiceNumber' => app(OrderInvoiceNumber::class)->assign((string) $order->getKey()),
                'invoiceDate' => $order->paid_at ?? $order->created_at ?? CarbonImmutable::now(),
                'documentTitle' => 'Invoice',
                'documentNumberLabel' => 'Invoice No:',
                'documentDateLabel' => 'Invoice Date:',
                'documentFooterGreeting' => 'Thank you for your business!',
                'documentFooterNote' => 'For questions about this invoice, please contact us.',
            ];
        });
    }
}
