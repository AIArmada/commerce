<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\Chip\DataObjects\Product;
use AIArmada\Chip\DataObjects\Purchase;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JsonSerializable;
use NumberFormatter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @implements Arrayable<string, mixed>
 */
class Invoice implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The billable model instance.
     */
    protected Model $owner;

    /**
     * The CHIP purchase data.
     */
    protected Purchase $purchase;

    /**
     * Create a new invoice instance.
     */
    public function __construct(Model $owner, Purchase $purchase)
    {
        $this->owner = $owner;
        $this->purchase = $purchase;
    }

    /**
     * Get the invoice ID (purchase ID).
     */
    public function id(): string
    {
        return $this->purchase->id;
    }

    /**
     * Get the invoice number.
     */
    public function number(): ?string
    {
        return $this->purchase->reference ?? $this->purchase->id;
    }

    /**
     * Get the invoice date.
     */
    public function date(): ?Carbon
    {
        return $this->purchase->getCreatedAt();
    }

    /**
     * Get the invoice due date.
     */
    public function dueDate(): ?Carbon
    {
        return $this->purchase->getDueDate();
    }

    /**
     * Get the invoice currency.
     */
    public function currency(): string
    {
        return $this->purchase->getCurrency();
    }

    /**
     * Get the raw total amount in cents.
     */
    public function rawTotal(): int
    {
        return $this->purchase->getAmountInCents();
    }

    /**
     * Get the total amount formatted.
     */
    public function total(): string
    {
        return $this->formatAmount($this->rawTotal());
    }

    /**
     * Get the subtotal (total before tax).
     */
    public function subtotal(): string
    {
        // CHIP doesn't separate tax, so subtotal equals total
        return $this->total();
    }

    /**
     * Determine if the invoice has tax.
     */
    public function hasTax(): bool
    {
        return false;
    }

    /**
     * Get the tax amount.
     */
    public function tax(): string
    {
        return $this->formatAmount(0);
    }

    /**
     * Determine if the invoice has a discount.
     */
    public function hasDiscount(): bool
    {
        return false;
    }

    /**
     * Get the discount amount.
     */
    public function discount(): string
    {
        return $this->formatAmount(0);
    }

    /**
     * Get the line items for the invoice.
     *
     * @return Collection<int, InvoiceLineItem>
     */
    public function invoiceItems(): Collection
    {
        $products = $this->purchase->purchase->products ?? [];

        return collect($products)->map(function (Product $product, int $index) {
            return new InvoiceLineItem($this, $product, $index);
        });
    }

    /**
     * Get the invoice status.
     */
    public function status(): ?string
    {
        return $this->purchase->status;
    }

    /**
     * Determine if the invoice is paid.
     */
    public function paid(): bool
    {
        return $this->purchase->isPaid();
    }

    /**
     * Determine if the invoice is open (unpaid).
     */
    public function open(): bool
    {
        return in_array($this->purchase->status, ['created', 'pending', 'pending_execute', 'pending_capture'], true);
    }

    /**
     * Determine if the invoice is voided.
     */
    public function voided(): bool
    {
        return $this->purchase->isCancelled();
    }

    /**
     * Get the checkout URL for the invoice.
     */
    public function checkoutUrl(): ?string
    {
        return $this->purchase->checkout_url;
    }

    /**
     * Get the customer name.
     */
    public function customerName(): ?string
    {
        return $this->purchase->client->full_name
            ?? $this->owner->chipName()
            ?? null;
    }

    /**
     * Get the customer email.
     */
    public function customerEmail(): ?string
    {
        return $this->purchase->client->email
            ?? $this->owner->chipEmail()
            ?? null;
    }

    /**
     * Get the customer phone.
     */
    public function customerPhone(): ?string
    {
        return $this->purchase->client->phone ?? null;
    }

    /**
     * Get the billable model.
     */
    public function owner(): Model
    {
        return $this->owner;
    }

    /**
     * Get the underlying CHIP purchase.
     */
    public function asChipPurchase(): Purchase
    {
        return $this->purchase;
    }

    /**
     * Get the View instance for the invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function view(array $data = []): \Illuminate\Contracts\View\View
    {
        return \Illuminate\Support\Facades\View::make('cashier-chip::invoice', array_merge($data, [
            'invoice' => $this,
            'owner' => $this->owner,
        ]));
    }

    /**
     * Capture the invoice as a PDF and return the raw bytes.
     *
     * @param  array<string, mixed>  $data
     */
    public function pdf(array $data = []): string
    {
        $renderer = app(Contracts\InvoiceRenderer::class);

        if ($renderer === null) {
            throw new RuntimeException(
                'An invoice renderer is required. Please install dompdf/dompdf or configure a custom renderer.'
            );
        }

        return $renderer->render($this, $data, [
            'paper' => config('cashier-chip.invoices.paper', 'A4'),
        ]);
    }

    /**
     * Create an invoice download response.
     *
     * @param  array<string, mixed>  $data
     */
    public function download(array $data = []): Response
    {
        $filename = "invoice-{$this->number()}.pdf";

        return new Response($this->pdf($data), 200, [
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
            'X-Vapor-Base64-Encode' => 'True',
        ]);
    }

    /**
     * Create an invoice download response for inline display.
     *
     * @param  array<string, mixed>  $data
     */
    public function downloadAs(string $filename, array $data = []): Response
    {
        return new Response($this->pdf($data), 200, [
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
            'X-Vapor-Base64-Encode' => 'True',
        ]);
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'number' => $this->number(),
            'date' => $this->date()?->toIso8601String(),
            'due_date' => $this->dueDate()?->toIso8601String(),
            'currency' => $this->currency(),
            'total' => $this->total(),
            'subtotal' => $this->subtotal(),
            'tax' => $this->tax(),
            'discount' => $this->discount(),
            'status' => $this->status(),
            'paid' => $this->paid(),
            'checkout_url' => $this->checkoutUrl(),
            'customer_name' => $this->customerName(),
            'customer_email' => $this->customerEmail(),
            'items' => $this->invoiceItems()->toArray(),
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Prepare the object for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Format the given amount into a displayable currency.
     */
    protected function formatAmount(int $amount): string
    {
        $currency = $this->currency();
        $locale = config('cashier-chip.currency_locale', 'ms_MY');

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($amount / 100, $currency);
    }
}
