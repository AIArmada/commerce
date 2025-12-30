<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\InvoiceLineItemContract;
use Stripe\InvoiceLineItem;

/**
 * Wrapper for Stripe invoice line item.
 */
class StripeInvoiceLineItem implements InvoiceLineItemContract
{
    /**
     * Create a new Stripe invoice line item wrapper.
     */
    public function __construct(
        protected InvoiceLineItem $item
    ) {}

    /**
     * Get the line item ID.
     */
    public function id(): string
    {
        return $this->item->id;
    }

    /**
     * Get the description.
     */
    public function description(): ?string
    {
        return $this->item->description;
    }

    /**
     * Get the quantity.
     */
    public function quantity(): int
    {
        return $this->item->quantity ?? 1;
    }

    /**
     * Get the unit amount in cents.
     */
    public function rawUnitAmount(): int
    {
        return $this->item->unit_amount_excluding_tax ?? $this->item->amount;
    }

    /**
     * Get the formatted unit amount.
     */
    public function unitAmount(): string
    {
        return $this->formatAmount($this->rawUnitAmount());
    }

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int
    {
        return $this->item->amount;
    }

    /**
     * Get the formatted total.
     */
    public function total(): string
    {
        return $this->formatAmount($this->rawTotal());
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return mb_strtoupper($this->item->currency);
    }

    /**
     * Determine if this is a proration.
     */
    public function isProration(): bool
    {
        return $this->item->proration ?? false;
    }

    /**
     * Get the price ID if applicable.
     */
    public function priceId(): ?string
    {
        $pricing = $this->item->offsetGet('pricing');

        if (is_object($pricing) && isset($pricing->price_id) && is_string($pricing->price_id)) {
            return $pricing->price_id;
        }

        $priceDetails = $this->item->offsetGet('price_details');

        if (is_object($priceDetails) && isset($priceDetails->price) && is_string($priceDetails->price)) {
            return $priceDetails->price;
        }

        $price = $this->item->offsetGet('price');

        if ($price instanceof \Stripe\Price) {
            return $price->id;
        }

        if (is_object($price) && isset($price->id) && is_string($price->id)) {
            return $price->id;
        }

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

    /**
     * Format an amount.
     */
    protected function formatAmount(int $amount): string
    {
        return number_format($amount / 100, 2) . ' ' . mb_strtoupper($this->currency());
    }
}
