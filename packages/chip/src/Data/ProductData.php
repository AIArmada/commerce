<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\Chip\Data\Casts\MoneyCast;
use AIArmada\Chip\Data\Transformers\MoneyTransformer;
use AIArmada\Chip\Exceptions\ChipValidationException;
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
        public readonly float | string $tax_percent,
        public readonly ?string $category,
        public readonly ?int $total_price_override = null,
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
            tax_percent: is_string($data['tax_percent'] ?? null) ? $data['tax_percent'] : (float) ($data['tax_percent'] ?? 0.0),
            category: $data['category'] ?? null,
            total_price_override: isset($data['total_price_override']) ? (int) $data['total_price_override'] : null,
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
        float | string $taxPercent = 0.0,
        ?string $category = null,
        ?int $totalPriceOverride = null,
    ): self {
        $currency = $price->getCurrency()->getCurrency();

        return new self(
            name: $name,
            quantity: (string) $quantity,
            price: $price,
            discount: $discount ?? Money::{$currency}(0),
            tax_percent: $taxPercent,
            category: $category,
            total_price_override: $totalPriceOverride,
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
     * Get the line discount total in cents: the per-line discount,
     * passed through unmultiplied (sandbox-proven P25a/f: the
     * server subtracts it once per line, not per unit).
     */
    public function getDiscountTotalInCents(): int
    {
        return $this->getDiscountInCents();
    }

    /**
     * Get the total price as Money, or the total price override
     * when set.
     *
     * Sandbox-proven server parity (P17/P25a–g): discount is
     * per-line, tax applies to the net line, and rounding is a
     * single half-up per line — one round, not gross-then-tax.
     */
    public function getTotalPrice(): Money
    {
        return Money::{$this->getCurrency()}(self::lineTotalMinorUnits(
            $this->getPriceInCents(),
            $this->quantity,
            $this->getDiscountInCents(),
            $this->tax_percent,
            $this->total_price_override,
        ));
    }

    /**
     * Server line-total primitive shared by the accessors, the API
     * validation, and the cashier-chip fakes: TPO wins, else a
     * single half-up round of
     * (price × quantity − discount) × (1 + tax/100).
     *
     * Sandbox-proven 2026-09-29 (P17, P25a–g, disctax, taxcombo):
     * per-line discount, net-base tax, once-per-line rounding, TPO
     * final even with tax. Exact decimal math (BCMath): float64
     * misrounds valid half-cent lines (100 × "1.005" must be 101).
     * A discount above the line gross throws, mirroring the
     * server's 400 `product_subtotal_negative` (P25g) — checked
     * before TPO, since the server validates first (P25h).
     */
    public static function lineTotalMinorUnits(
        int $priceMinor,
        int | string $quantity,
        int $discountMinor = 0,
        float | int | string $taxPercent = 0.0,
        ?int $totalPriceOverride = null,
    ): int {
        [$quantityNum, $quantityDen] = self::decimalParts($quantity, 'Product quantity must be numeric.');
        [$taxNum, $taxDen] = self::decimalParts($taxPercent, 'Product tax percent must be numeric.');

        // net = price × quantity − discount, as netNum/netDen.
        $netNum = bcsub(bcmul((string) $priceMinor, $quantityNum), bcmul((string) $discountMinor, $quantityDen));

        if (bccomp($netNum, '0') < 0) {
            throw new ChipValidationException('Product discount cannot be larger than price times quantity.');
        }

        if ($totalPriceOverride !== null) {
            return $totalPriceOverride;
        }

        // total = net × (100 × taxDen + taxNum) / (taxDen × 100).
        $num = bcmul($netNum, bcadd(bcmul('100', $taxDen), $taxNum));
        $den = bcmul($quantityDen, bcmul($taxDen, '100'));

        return self::roundHalfUp($num, $den);
    }

    /**
     * Mirror of the server discount bound: true when the discount
     * exceeds the exact line gross (price × quantity).
     */
    public static function discountExceedsLineGross(int $discountMinor, int $priceMinor, int | string $quantity): bool
    {
        [$quantityNum, $quantityDen] = self::decimalParts($quantity, 'Product quantity must be numeric.');

        return bccomp(bcmul((string) $discountMinor, $quantityDen), bcmul((string) $priceMinor, $quantityNum)) > 0;
    }

    /**
     * Half-up round of the rational num/den (denominator > 0),
     * away from zero on exact halves. Throws when the result
     * exceeds the int range instead of saturating.
     */
    private static function roundHalfUp(string $num, string $den): int
    {
        $negative = bccomp($num, '0') < 0;
        $abs = $negative ? bcsub('0', $num) : $num;

        $rounded = bcdiv(bcadd(bcmul($abs, '2'), $den), bcmul($den, '2'));

        // PHP_INT_MIN has magnitude PHP_INT_MAX + 1 and stays valid.
        if ($negative && $rounded === bcsub('0', (string) PHP_INT_MIN)) {
            return PHP_INT_MIN;
        }

        if (bccomp($rounded, (string) PHP_INT_MAX) > 0) {
            throw new ChipValidationException('Line total exceeds the supported range.');
        }

        $result = (int) $rounded;

        return $negative ? -$result : $result;
    }

    /**
     * Split a numeric into exact [numerator, denominator] decimal
     * parts (both integer strings, denominator > 0). Handles ints,
     * decimal strings, and exponent forms ("1e2"). Digit runs and
     * exponents past 1024 digits throw: accepted quantities and
     * taxes never approach that, and unbounded runs would turn
     * into unbounded allocations. Shared with `TaxPercent`, which
     * counts digits on the parts to mirror the server's
     * max_digits / max_decimal_places rules.
     *
     * @return array{string, string}
     */
    public static function decimalParts(int | float | string $value, string $errorMessage): array
    {
        $text = mb_trim((string) $value);

        if (! is_numeric($text)) {
            throw new InvalidArgumentException($errorMessage);
        }

        $exponent = 0;
        $exponentAt = mb_strpos(mb_strtolower($text), 'e');

        if ($exponentAt !== false) {
            $exponent = (int) mb_substr($text, $exponentAt + 1);
            $text = mb_substr($text, 0, $exponentAt);
        }

        $negative = str_starts_with($text, '-');
        $text = mb_ltrim($text, '+-');

        $decimals = 0;

        if (str_contains($text, '.')) {
            [$whole, $fraction] = explode('.', $text, 2);
            $text = $whole . $fraction;
            $decimals = mb_strlen($fraction);
        }

        $digits = mb_ltrim($text, '0');

        // value = digits × 10^(exponent − decimals).
        $shift = $exponent - $decimals;

        if (mb_strlen($digits) > 1024 || abs($shift) > 1024) {
            throw new InvalidArgumentException('Numeric value exceeds supported precision.');
        }

        if ($digits === '') {
            // Zero keeps its decimal places in the denominator so
            // precision checks see them ('0.000' is 3dp, rejected
            // like the server's max_decimal_places, P26d).
            return ['0', $shift >= 0 ? '1' : '1' . str_repeat('0', -$shift)];
        }

        $numerator = ($negative ? '-' : '') . $digits;

        if ($shift >= 0) {
            return [$numerator . str_repeat('0', $shift), '1'];
        }

        return [$numerator, '1' . str_repeat('0', -$shift)];
    }

    /**
     * Get the total price in cents for API communication.
     */
    public function getTotalPriceInCents(): int
    {
        return (int) $this->getTotalPrice()->getAmount();
    }

    /**
     * Multiply integer minor units by a quantity with half-up rounding.
     *
     * Shared gross primitive (subtotals): exact decimal math, since
     * float64 misrounds valid half-cent lines (100 × "1.005" must
     * be 101). Matches CHIP's server-side rounding (sandbox-proven
     * half-up per line, 2026-09-29).
     */
    public static function multiplyMinorUnits(int $minorUnits, string $quantity): int
    {
        [$quantityNum, $quantityDen] = self::decimalParts($quantity, 'Product quantity must be numeric.');

        return self::roundHalfUp(bcmul((string) $minorUnits, $quantityNum), $quantityDen);
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
            'total_price_override' => $this->total_price_override,
        ];
    }

    /**
     * Convert to array for outbound CHIP requests.
     *
     * Same shape as toArray() but omits nulls: the spec types `category`
     * as a non-nullable string, so a null must never reach the wire.
     * Zeros are preserved.
     *
     * @return array<string, mixed>
     */
    public function toRequestArray(): array
    {
        return array_filter(
            $this->toArray(),
            fn (mixed $value): bool => $value !== null
        );
    }
}
