<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\Chip\Data\Casts\MoneyCast;
use AIArmada\Chip\Data\Transformers\MoneyTransformer;
use Akaunting\Money\Money;
use InvalidArgumentException;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;

final class ProductData extends ChipData
{
    public function __construct(
        public readonly string $name,
        public readonly string $quantity,
        #[WithCast(MoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $price,
        #[WithCast(MoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $discount,
        public readonly float $tax_percent,
        public readonly ?string $category,
    ) {}

    /**
     * Create a Product from array data (typically from CHIP API response).
     * Prices in the array are expected to be in cents (minor units).
     *
     * @param  array<string, mixed>|self  ...$payloads
     */
    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);
        $currency = $data['currency'] ?? 'MYR';

        return new self(
            name: $data['name'],
            quantity: (string) ($data['quantity'] ?? '1'),
            price: Money::{$currency}((int) $data['price']),
            discount: Money::{$currency}((int) ($data['discount'] ?? 0)),
            tax_percent: (float) ($data['tax_percent'] ?? 0.0),
            category: $data['category'] ?? null,
        );
    }

    /**
     * Create a Product with Money objects directly.
     */
    public static function make(
        string $name,
        Money $price,
        string | float | int $quantity = 1,
        ?Money $discount = null,
        float $taxPercent = 0.0,
        ?string $category = null,
    ): self {
        $currency = $price->getCurrency()->getCurrency();

        return new self(
            name: $name,
            quantity: (string) $quantity,
            price: $price,
            discount: $discount ?? Money::{$currency}(0),
            tax_percent: $taxPercent,
            category: $category,
        );
    }

    /**
     * Get the currency code for this product.
     */
    public function getCurrency(): string
    {
        return $this->price->getCurrency()->getCurrency();
    }

    /**
     * Get the price in cents (minor units) for API communication.
     */
    public function getPriceInCents(): int
    {
        return (int) $this->price->getAmount();
    }

    /**
     * Get the discount in cents (minor units) for API communication.
     */
    public function getDiscountInCents(): int
    {
        return (int) $this->discount->getAmount();
    }

    /**
     * Get the line subtotal in cents: price × quantity with half-up rounding.
     */
    public function getSubtotalInCents(): int
    {
        return self::multiplyMinorUnits($this->getPriceInCents(), $this->quantity);
    }

    /**
     * Get the line discount total in cents: discount × quantity, half-up.
     */
    public function getDiscountTotalInCents(): int
    {
        return self::multiplyMinorUnits($this->getDiscountInCents(), $this->quantity);
    }

    /**
     * Get the total price as Money (price - discount) × quantity.
     */
    public function getTotalPrice(): Money
    {
        $netUnitMinor = $this->getPriceInCents() - $this->getDiscountInCents();
        $currency = $this->getCurrency();

        return Money::{$currency}(self::multiplyMinorUnits($netUnitMinor, $this->quantity));
    }

    /**
     * Get the total price in cents for API communication.
     */
    public function getTotalPriceInCents(): int
    {
        return (int) $this->getTotalPrice()->getAmount();
    }

    /**
     * Multiply integer minor units by a decimal quantity with explicit
     * half-up rounding so fractional quantities never silently truncate.
     */
    private static function multiplyMinorUnits(int $minorUnits, string $quantity): int
    {
        $normalized = mb_trim($quantity);

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException('Product quantity must be numeric.');
        }

        return (int) round($minorUnits * (float) $normalized, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Convert to array for CHIP API (prices in cents).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->getPriceInCents(),
            'discount' => $this->getDiscountInCents(),
            'tax_percent' => $this->tax_percent,
            'category' => $this->category,
        ];
    }
}
