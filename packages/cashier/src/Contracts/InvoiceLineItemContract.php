<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Contract for invoice line items.
 */
interface InvoiceLineItemContract extends Arrayable
{
    /**
     * Get the line item ID.
     */
    public function id(): string;

    /**
     * Get the description.
     */
    public function description(): ?string;

    /**
     * Get the quantity.
     */
    public function quantity(): int;

    /**
     * Get the unit amount in cents.
     */
    public function rawUnitAmount(): int;

    /**
     * Get the formatted unit amount.
     */
    public function unitAmount(): string;

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int;

    /**
     * Get the formatted total.
     */
    public function total(): string;

    /**
     * Get the currency.
     */
    public function currency(): string;

    /**
     * Determine if this is a proration.
     */
    public function isProration(): bool;

    /**
     * Get the price ID if applicable.
     */
    public function priceId(): ?string;
}
