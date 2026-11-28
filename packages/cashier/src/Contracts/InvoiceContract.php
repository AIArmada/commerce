<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;

/**
 * Contract for invoice representations.
 */
interface InvoiceContract extends Arrayable, Jsonable
{
    /**
     * Get the invoice ID.
     */
    public function id(): string;

    /**
     * Get the gateway name.
     */
    public function gateway(): string;

    /**
     * Get the invoice number.
     */
    public function number(): ?string;

    /**
     * Get the invoice date.
     */
    public function date(): CarbonInterface;

    /**
     * Get the due date.
     */
    public function dueDate(): ?CarbonInterface;

    /**
     * Get the invoice status.
     */
    public function status(): string;

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int;

    /**
     * Get the formatted total.
     */
    public function total(): string;

    /**
     * Get the subtotal in cents.
     */
    public function rawSubtotal(): int;

    /**
     * Get the formatted subtotal.
     */
    public function subtotal(): string;

    /**
     * Get the tax amount in cents.
     */
    public function rawTax(): int;

    /**
     * Get the formatted tax.
     */
    public function tax(): string;

    /**
     * Get the currency.
     */
    public function currency(): string;

    /**
     * Get the invoice line items.
     *
     * @return Collection<int, InvoiceLineItemContract>
     */
    public function items(): Collection;

    /**
     * Determine if the invoice is paid.
     */
    public function isPaid(): bool;

    /**
     * Determine if the invoice is open/pending.
     */
    public function isOpen(): bool;

    /**
     * Determine if the invoice is void.
     */
    public function isVoid(): bool;

    /**
     * Determine if the invoice is a draft.
     */
    public function isDraft(): bool;

    /**
     * Get the customer.
     */
    public function customer(): mixed;

    public function customerId(): ?string;

    /**
     * Get the hosted invoice URL.
     */
    public function hostedUrl(): ?string;

    /**
     * Get the PDF download URL.
     */
    public function pdfUrl(): ?string;

    /**
     * Get the underlying gateway invoice object.
     */
    public function asGatewayInvoice(): mixed;
}
