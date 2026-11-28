<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Lifecycle\Actions;

use InvalidArgumentException;

/**
 * Calculates document totals using integer minor-unit amounts.
 */
final readonly class CalculateDocumentTotals
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal_minor: int, tax_amount_minor: int, total_minor: int}
     */
    public function calculate(array $items, int $discountAmountMinor = 0, ?string $currency = null): array
    {
        if ($discountAmountMinor < 0) {
            throw new InvalidArgumentException('discount_amount_minor must not be negative.');
        }

        $currency = $currency !== null ? mb_strtoupper($currency) : null;
        $subtotalMinor = 0;
        $taxAmountMinor = 0;

        foreach ($items as $index => $item) {
            $this->assertMinorUnitItem($item, $index, $currency);

            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPriceMinor = (int) $item['unit_price_minor'];
            $itemTaxMinor = (int) ($item['tax_amount_minor'] ?? 0);

            $subtotalMinor = $this->add($subtotalMinor, $this->multiply($quantity, $unitPriceMinor));
            $taxAmountMinor = $this->add($taxAmountMinor, $itemTaxMinor);
        }

        return [
            'subtotal_minor' => $subtotalMinor,
            'tax_amount_minor' => $taxAmountMinor,
            'total_minor' => $this->net($subtotalMinor, $taxAmountMinor, $discountAmountMinor),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function subtotal(array $items, string $currency): int
    {
        return $this->calculate($items, 0, $currency)['subtotal_minor'];
    }

    /** @param array<int, array<string, mixed>> $items */
    public function assertItemCurrencies(array $items, string $currency): void
    {
        foreach ($items as $index => $item) {
            $this->assertItemCurrency($item, $index, $currency);
        }
    }

    public function net(int $subtotalMinor, int $taxAmountMinor, int $discountAmountMinor): int
    {
        if (min($subtotalMinor, $taxAmountMinor, $discountAmountMinor) < 0) {
            throw new InvalidArgumentException('Document monetary amounts must be non-negative minor-unit integers.');
        }

        if ($discountAmountMinor >= $subtotalMinor) {
            return max(0, $taxAmountMinor - ($discountAmountMinor - $subtotalMinor));
        }

        return $this->add($subtotalMinor - $discountAmountMinor, $taxAmountMinor);
    }

    public function applyBasisPoints(int $amountMinor, int $basisPoints): int
    {
        if ($amountMinor < 0 || $basisPoints < 0 || $basisPoints > 100_000) {
            throw new InvalidArgumentException('Invalid amount or tax rate.');
        }

        // Split the calculation to avoid overflowing the intermediate product.
        $whole = $this->multiply(intdiv($amountMinor, 10_000), $basisPoints);
        $fraction = intdiv(($amountMinor % 10_000) * $basisPoints + 5_000, 10_000);

        return $this->add($whole, $fraction);
    }

    private function add(int $left, int $right): int
    {
        if ($left > PHP_INT_MAX - $right) {
            throw new InvalidArgumentException('Document monetary amount exceeds the supported integer range.');
        }

        return $left + $right;
    }

    private function multiply(int $left, int $right): int
    {
        if ($right !== 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            throw new InvalidArgumentException('Document monetary amount exceeds the supported integer range.');
        }

        return $left * $right;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function assertMinorUnitItem(array $item, int $index, ?string $currency): void
    {
        foreach (['price', 'unit_price', 'tax_amount'] as $unsupportedKey) {
            if (array_key_exists($unsupportedKey, $item)) {
                throw new InvalidArgumentException(sprintf(
                    'Document item %d uses removed major-unit field `%s`; use the corresponding `*_minor` integer field.',
                    $index,
                    $unsupportedKey,
                ));
            }
        }

        $quantity = $item['quantity'] ?? 1;

        if (! is_int($quantity) || $quantity <= 0) {
            throw new InvalidArgumentException(sprintf('Document item %d quantity must be a positive integer.', $index));
        }

        if (! array_key_exists('unit_price_minor', $item) || ! is_int($item['unit_price_minor']) || $item['unit_price_minor'] < 0) {
            throw new InvalidArgumentException(sprintf('Document item %d unit_price_minor must be a non-negative integer.', $index));
        }

        if (isset($item['tax_amount_minor']) && (! is_int($item['tax_amount_minor']) || $item['tax_amount_minor'] < 0)) {
            throw new InvalidArgumentException(sprintf('Document item %d tax_amount_minor must be a non-negative integer.', $index));
        }

        if ($currency !== null) {
            $this->assertItemCurrency($item, $index, $currency);
        }
    }

    /** @param array<string, mixed> $item */
    private function assertItemCurrency(array $item, int $index, string $currency): void
    {
        if (isset($item['currency']) && (! is_string($item['currency']) || mb_strtoupper($item['currency']) !== $currency)) {
            throw new InvalidArgumentException(sprintf('Document item %d currency does not match document currency.', $index));
        }
    }
}
