<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\InvoiceLineItemContract;
use AIArmada\Chip\Data\ProductData;

/**
 * Wrapper for CHIP invoice line item (product).
 */
class ChipInvoiceLineItem implements InvoiceLineItemContract
{
    /**
     * Create a new CHIP invoice line item wrapper.
     */
    public function __construct(
        protected ProductData $product,
        protected int $index = 0
    ) {}

    /**
     * Get the line item ID.
     */
    public function id(): string
    {
        return (string) $this->index;
    }

    /**
     * Get the description.
     */
    public function description(): ?string
    {
        return $this->product->name;
    }

    /**
     * Get the quantity.
     */
    public function quantity(): int
    {
        return max(1, (int) $this->product->quantity);
    }

    /**
     * Get the unit amount in cents.
     */
    public function rawUnitAmount(): int
    {
        return max(0, $this->product->getPriceInCents() - $this->product->getDiscountInCents());
    }

    /**
     * Get the formatted unit amount.
     */
    public function unitAmount(): string
    {
        return number_format($this->rawUnitAmount() / 100, 2) . ' ' . mb_strtoupper($this->currency());
    }

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int
    {
        return $this->rawUnitAmount() * $this->quantity();
    }

    /**
     * Get the formatted total.
     */
    public function total(): string
    {
        return number_format($this->rawTotal() / 100, 2) . ' ' . mb_strtoupper($this->currency());
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return $this->product->getCurrency();
    }

    /**
     * Determine if this is a proration.
     */
    public function isProration(): bool
    {
        return false; // CHIP doesn't have proration concept
    }

    /**
     * Get the price ID if applicable.
     */
    public function priceId(): ?string
    {
        return null;
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
            'description' => $this->description(),
            'quantity' => $this->quantity(),
            'unit_amount' => $this->unitAmount(),
            'raw_unit_amount' => $this->rawUnitAmount(),
            'total' => $this->total(),
            'raw_total' => $this->rawTotal(),
            'currency' => $this->currency(),
            'is_proration' => $this->isProration(),
            'price_id' => $this->priceId(),
        ];
    }
}
