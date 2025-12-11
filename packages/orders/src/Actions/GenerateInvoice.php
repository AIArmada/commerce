<?php

declare(strict_types=1);

namespace AIArmada\Orders\Actions;

use AIArmada\Orders\Models\Order;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Generate PDF invoice for an order.
 */
class GenerateInvoice
{
    /**
     * Generate and return a PDF invoice as a string.
     */
    public function handle(Order $order): string
    {
        $invoiceNumber = $this->generateInvoiceNumber($order);

        $pdf = Pdf::view('orders::pdf.invoice', [
            'order' => $order,
            'items' => $order->items,
            'billingAddress' => $order->billingAddress,
            'shippingAddress' => $order->shippingAddress,
            'payments' => $order->payments()->where('status', 'completed')->get(),
            'invoiceNumber' => $invoiceNumber,
            'invoiceDate' => now(),
        ])
            ->format('a4')
            ->margins(15, 15, 15, 15)
            ->name("invoice-{$order->order_number}.pdf");

        return $pdf->toString();
    }

    /**
     * Generate and save invoice to a path.
     */
    public function save(Order $order, string $path): string
    {
        $content = $this->handle($order);
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Generate and download invoice.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download(Order $order)
    {
        $invoiceNumber = $this->generateInvoiceNumber($order);

        return Pdf::view('orders::pdf.invoice', [
            'order' => $order,
            'items' => $order->items,
            'billingAddress' => $order->billingAddress,
            'shippingAddress' => $order->shippingAddress,
            'payments' => $order->payments()->where('status', 'completed')->get(),
            'invoiceNumber' => $invoiceNumber,
            'invoiceDate' => now(),
        ])
            ->format('a4')
            ->margins(15, 15, 15, 15)
            ->name("invoice-{$order->order_number}.pdf")
            ->download();
    }

    /**
     * Generate invoice number.
     */
    protected function generateInvoiceNumber(Order $order): string
    {
        $prefix = config('orders.invoice.prefix', 'INV');
        $separator = config('orders.invoice.separator', '-');

        return $prefix . $separator . now()->format('Ymd') . $separator . mb_strtoupper(Str::random(6));
    }
}
