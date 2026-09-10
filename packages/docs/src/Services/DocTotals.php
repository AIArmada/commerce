<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use InvalidArgumentException;

/**
 * Calculates document totals using integer minor-unit amounts.
 */
final class DocTotals
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

            $subtotalMinor += $quantity * $unitPriceMinor;
            $taxAmountMinor += $itemTaxMinor;
        }

        return [
            'subtotal_minor' => $subtotalMinor,
            'tax_amount_minor' => $taxAmountMinor,
            'total_minor' => max(0, $subtotalMinor + $taxAmountMinor - $discountAmountMinor),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function subtotal(array $items, string $currency): int
    {
        return $this->calculate($items, 0, $currency)['subtotal_minor'];
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

        if (isset($item['currency']) && $currency !== null && mb_strtoupper((string) $item['currency']) !== $currency) {
            throw new InvalidArgumentException(sprintf('Document item %d currency does not match document currency.', $index));
        }
    }
}
