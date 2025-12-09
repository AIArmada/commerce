<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\InvoiceLineItemContract;
use AIArmada\Chip\Data\PurchaseData;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrapper for CHIP invoice (purchase).
 */
class ChipInvoice implements InvoiceContract
{
    /**
     * Create a new CHIP invoice wrapper.
     */
    public function __construct(
        protected PurchaseData $purchase
    ) {}

    /**
     * Get the invoice ID.
     */
    public function id(): string
    {
        return $this->purchase->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'chip';
    }

    /**
     * Get the invoice number.
     */
    public function number(): ?string
    {
        return $this->purchase->purchase['notes'] ?? $this->purchase->id;
    }

    /**
     * Get the invoice date.
     */
    public function date(): CarbonInterface
    {
        $created = $this->purchase->createdOn ?? $this->purchase->created ?? now();

        if (is_string($created)) {
            return Carbon::parse($created);
        }

        return $created instanceof CarbonInterface ? $created : Carbon::now();
    }

    /**
     * Get the due date.
     */
    public function dueDate(): ?CarbonInterface
    {
        $due = $this->purchase->dueOn ?? $this->purchase->due ?? null;

        if ($due && is_string($due)) {
            return Carbon::parse($due);
        }

        return $due instanceof CarbonInterface ? $due : null;
    }

    /**
     * Get the invoice status.
     */
    public function status(): string
    {
        return $this->purchase->status ?? 'pending';
    }

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int
    {
        $total = $this->purchase->purchase['total'] ?? 0;

        return (int) ($total * 100);
    }

    /**
     * Get the formatted total.
     */
    public function total(): string
    {
        return number_format($this->rawTotal() / 100, 2) . ' ' . mb_strtoupper($this->currency());
    }

    /**
     * Get the subtotal in cents.
     */
    public function rawSubtotal(): int
    {
        $subtotal = $this->purchase->purchase['subtotal_override'] ?? $this->purchase->purchase['total'] ?? 0;

        return (int) ($subtotal * 100);
    }

    /**
     * Get the formatted subtotal.
     */
    public function subtotal(): string
    {
        return number_format($this->rawSubtotal() / 100, 2) . ' ' . mb_strtoupper($this->currency());
    }

    /**
     * Get the tax amount in cents.
     */
    public function rawTax(): int
    {
        $tax = $this->purchase->purchase['total_tax_override'] ?? 0;

        return (int) ($tax * 100);
    }

    /**
     * Get the formatted tax.
     */
    public function tax(): string
    {
        return number_format($this->rawTax() / 100, 2) . ' ' . mb_strtoupper($this->currency());
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return mb_strtoupper($this->purchase->purchase['currency'] ?? 'MYR');
    }

    /**
     * Get the invoice line items.
     *
     * @return Collection<int, InvoiceLineItemContract>
     */
    public function items(): Collection
    {
        $products = $this->purchase->purchase['products'] ?? [];

        return collect($products)->map(function ($product, $index) {
            return new ChipInvoiceLineItem($product, $index);
        });
    }

    /**
     * Determine if the invoice is paid.
     */
    public function isPaid(): bool
    {
        return in_array($this->purchase->status, ['paid', 'success', 'cleared', 'settled']);
    }

    /**
     * Determine if the invoice is open/pending.
     */
    public function isOpen(): bool
    {
        return in_array($this->purchase->status, ['created', 'sent', 'viewed', 'pending', 'pending_execute', 'pending_capture']);
    }

    /**
     * Determine if the invoice is void.
     */
    public function isVoid(): bool
    {
        return in_array($this->purchase->status, ['cancelled', 'expired', 'error', 'blocked']);
    }

    /**
     * Determine if the invoice is a draft.
     */
    public function isDraft(): bool
    {
        return false; // CHIP doesn't have draft status
    }

    /**
     * Get the customer.
     */
    public function customer(): mixed
    {
        return $this->purchase->client ?? null;
    }

    /**
     * Get the hosted invoice URL.
     */
    public function hostedUrl(): ?string
    {
        return $this->purchase->checkoutUrl ?? $this->purchase->checkout_url ?? null;
    }

    /**
     * Get the PDF download URL.
     */
    public function pdfUrl(): ?string
    {
        // CHIP doesn't provide direct PDF URLs
        return null;
    }

    /**
     * Download the invoice as PDF.
     *
     * @param  array<string, mixed>  $data
     */
    public function download(array $data = []): Response
    {
        // Would need to generate PDF locally
        return redirect()->to($this->hostedUrl() ?? '/');
    }

    /**
     * View the invoice in the browser.
     *
     * @param  array<string, mixed>  $data
     */
    public function view(array $data = []): Response
    {
        return redirect()->to($this->hostedUrl() ?? '/');
    }

    /**
     * Get the underlying gateway invoice object.
     */
    public function asGatewayInvoice(): PurchaseData
    {
        return $this->purchase;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'gateway' => $this->gateway(),
            'number' => $this->number(),
            'status' => $this->status(),
            'currency' => $this->currency(),
            'total' => $this->total(),
            'raw_total' => $this->rawTotal(),
            'subtotal' => $this->subtotal(),
            'raw_subtotal' => $this->rawSubtotal(),
            'tax' => $this->tax(),
            'raw_tax' => $this->rawTax(),
            'is_paid' => $this->isPaid(),
            'is_open' => $this->isOpen(),
            'date' => $this->date()->toIso8601String(),
            'due_date' => $this->dueDate()?->toIso8601String(),
            'hosted_url' => $this->hostedUrl(),
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
