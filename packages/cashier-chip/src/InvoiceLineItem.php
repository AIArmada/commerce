<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\Chip\DataObjects\Product;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use NumberFormatter;

/**
 * @implements Arrayable<string, mixed>
 */
class InvoiceLineItem implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The invoice instance.
     */
    protected Invoice $invoice;

    /**
     * The CHIP product data.
     */
    protected Product $product;

    /**
     * The line item index.
     */
    protected int $index;

    /**
     * Create a new invoice line item instance.
     */
    public function __construct(Invoice $invoice, Product $product, int $index = 0)
    {
        $this->invoice = $invoice;
        $this->product = $product;
        $this->index = $index;
    }

    /**
     * Get the line item ID.
     */
    public function id(): string
    {
        return "line_{$this->index}";
    }

    /**
     * Get the line item description.
     */
    public function description(): string
    {
        return $this->product->name;
    }

    /**
     * Get the line item quantity.
     */
    public function quantity(): int
    {
        return (int) $this->product->quantity;
    }

    /**
     * Get the unit price in cents.
     */
    public function unitPrice(): int
    {
        return $this->product->getPriceInCents();
    }

    /**
     * Get the unit price formatted.
     */
    public function unitPriceFormatted(): string
    {
        return $this->formatAmount($this->unitPrice());
    }

    /**
     * Get the total amount in cents.
     */
    public function total(): int
    {
        return $this->unitPrice() * $this->quantity();
    }

    /**
     * Get the total amount formatted.
     */
    public function totalFormatted(): string
    {
        return $this->formatAmount($this->total());
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return $this->invoice->currency();
    }

    /**
     * Get the underlying CHIP product.
     */
    public function asChipProduct(): Product
    {
        return $this->product;
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
            'description' => $this->description(),
            'quantity' => $this->quantity(),
            'unit_price' => $this->unitPriceFormatted(),
            'total' => $this->totalFormatted(),
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
