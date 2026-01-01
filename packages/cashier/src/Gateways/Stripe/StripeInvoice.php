<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\InvoiceLineItemContract;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Laravel\Cashier\Invoice;
use Stripe\Invoice as StripeInvoiceObject;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrapper for Stripe invoice.
 */
class StripeInvoice implements InvoiceContract
{
    /**
     * The Stripe invoice instance.
     */
    protected StripeInvoiceObject | Invoice $invoice;

    /**
     * Create a new Stripe invoice wrapper.
     */
    public function __construct(StripeInvoiceObject | Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Get the invoice ID.
     */
    public function id(): string
    {
        return $this->getStripeInvoice()->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'stripe';
    }

    /**
     * Get the invoice number.
     */
    public function number(): ?string
    {
        return $this->getStripeInvoice()->number;
    }

    /**
     * Get the invoice date.
     */
    public function date(): CarbonInterface
    {
        return Carbon::createFromTimestamp($this->getStripeInvoice()->created);
    }

    /**
     * Get the due date.
     */
    public function dueDate(): ?CarbonInterface
    {
        $dueDate = $this->getStripeInvoice()->due_date;

        return $dueDate ? Carbon::createFromTimestamp($dueDate) : null;
    }

    /**
     * Get the invoice status.
     */
    public function status(): string
    {
        return $this->getStripeInvoice()->status;
    }

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int
    {
        return $this->getStripeInvoice()->total;
    }

    /**
     * Get the formatted total.
     */
    public function total(): string
    {
        if ($this->invoice instanceof Invoice) {
            return $this->invoice->total();
        }

        return $this->formatAmount($this->rawTotal());
    }

    /**
     * Get the subtotal in cents.
     */
    public function rawSubtotal(): int
    {
        return $this->getStripeInvoice()->subtotal;
    }

    /**
     * Get the formatted subtotal.
     */
    public function subtotal(): string
    {
        if ($this->invoice instanceof Invoice) {
            return $this->invoice->subtotal();
        }

        return $this->formatAmount($this->rawSubtotal());
    }

    /**
     * Get the tax amount in cents.
     */
    public function rawTax(): int
    {
        return $this->getStripeInvoice()->tax ?? 0;
    }

    /**
     * Get the formatted tax.
     */
    public function tax(): string
    {
        if ($this->invoice instanceof Invoice) {
            return $this->invoice->tax();
        }

        return $this->formatAmount($this->rawTax());
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return mb_strtoupper($this->getStripeInvoice()->currency);
    }

    /**
     * Get the invoice line items.
     *
     * @return Collection<int, InvoiceLineItemContract>
     */
    public function items(): Collection
    {
        $stripeInvoice = $this->getStripeInvoice();

        $data = $stripeInvoice->lines->data;

        $items = collect(is_array($data) ? $data : [])
            ->map(static fn ($item): InvoiceLineItemContract => new StripeInvoiceLineItem($item))
            ->values();

        /** @var Collection<int, InvoiceLineItemContract> $items */
        return $items;
    }

    /**
     * Determine if the invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->getStripeInvoice()->status === 'paid';
    }

    /**
     * Determine if the invoice is open/pending.
     */
    public function isOpen(): bool
    {
        return $this->getStripeInvoice()->status === 'open';
    }

    /**
     * Determine if the invoice is void.
     */
    public function isVoid(): bool
    {
        return $this->getStripeInvoice()->status === 'void';
    }

    /**
     * Determine if the invoice is a draft.
     */
    public function isDraft(): bool
    {
        return $this->getStripeInvoice()->status === 'draft';
    }

    /**
     * Get the customer.
     */
    public function customer(): mixed
    {
        return $this->getStripeInvoice()->customer;
    }

    /**
     * Get the hosted invoice URL.
     */
    public function hostedUrl(): ?string
    {
        return $this->getStripeInvoice()->hosted_invoice_url;
    }

    /**
     * Get the PDF download URL.
     */
    public function pdfUrl(): ?string
    {
        return $this->getStripeInvoice()->invoice_pdf;
    }

    /**
     * Download the invoice as PDF.
     *
     * @param  array<string, mixed>  $data
     */
    public function download(array $data = []): Response
    {
        if ($this->invoice instanceof Invoice) {
            /** @var Response $response */
            $response = $this->invoice->download($data);

            return $response;
        }

        // Fallback to redirect to PDF URL
        return redirect()->to($this->pdfUrl());
    }

    /**
     * View the invoice in the browser.
     *
     * @param  array<string, mixed>  $data
     */
    public function view(array $data = []): Response
    {
        if ($this->invoice instanceof Invoice) {
            return response($this->invoice->view($data));
        }

        // Fallback to redirect to hosted URL
        return redirect()->to($this->hostedUrl());
    }

    /**
     * Get the underlying gateway invoice object.
     */
    public function asGatewayInvoice(): StripeInvoiceObject
    {
        return $this->getStripeInvoice();
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
            'pdf_url' => $this->pdfUrl(),
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get the Stripe invoice object.
     */
    protected function getStripeInvoice(): StripeInvoiceObject
    {
        if ($this->invoice instanceof Invoice) {
            return $this->invoice->asStripeInvoice();
        }

        return $this->invoice;
    }

    /**
     * Format an amount.
     */
    protected function formatAmount(int $amount): string
    {
        return number_format($amount / 100, 2) . ' ' . mb_strtoupper($this->currency());
    }
}
