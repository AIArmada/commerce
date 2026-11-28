<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Data\TaxResultData;
use InvalidArgumentException;

final class TaxAdapter
{
    /**
     * Calculate tax for the checkout session.
     *
     * @return array{total: int, included_total: int, additional_total: int, lines: array<string, array{discount: int, additional_tax: int, included_tax: int}>, shipping_additional_tax: int, rate: float, breakdown: array<array<string, mixed>>, taxable_amount: int, exempt: bool}
     */
    public function calculateTax(CheckoutSession $session): array
    {
        if (! interface_exists(TaxCalculatorInterface::class) || ! app()->bound(TaxCalculatorInterface::class)) {
            return $this->getDefaultTaxResult($session);
        }

        /** @var TaxCalculatorInterface $taxCalculator */
        $taxCalculator = app(TaxCalculatorInterface::class);

        $context = $this->buildTaxContext($session);
        $taxableItems = $this->buildTaxableItems($session);

        $totalTax = 0;
        $includedTax = 0;
        $weightedRateAmount = 0;
        $weightedRateBase = 0;
        $taxableAmount = 0;
        $breakdown = [];
        $lines = [];
        $shippingAdditionalTax = 0;
        $isExempt = true;

        foreach ($taxableItems as $taxableItem) {
            $lineId = $taxableItem['line_id'];
            $lines[$lineId] = [
                'discount' => $taxableItem['discount'],
                'additional_tax' => 0,
                'included_tax' => 0,
            ];
            $lineTaxableAmount = $taxableItem['taxable_amount'];

            if ($lineTaxableAmount <= 0) {
                continue;
            }

            $result = $taxCalculator->calculateTax(
                amountInCents: $lineTaxableAmount,
                taxClass: (string) ($taxableItem['tax_class'] ?? 'standard'),
                context: $context,
            );

            $taxableAmount += $lineTaxableAmount;
            $lines[$lineId][$result->includedInPrice ? 'included_tax' : 'additional_tax'] = $result->taxAmount;
            $totalTax += $result->taxAmount;
            $includedTax += $result->includedInPrice ? $result->taxAmount : 0;
            $weightedRateAmount += $result->ratePercentage * $lineTaxableAmount;
            $weightedRateBase += $lineTaxableAmount;
            $isExempt = $isExempt && $result->isExempt();
            array_push($breakdown, ...$this->normalizeBreakdown($result, 'items', (string) $lineId));
        }

        if ($session->shipping_total > 0) {
            $shippingResult = $taxCalculator->calculateShippingTax(
                shippingAmountInCents: $session->shipping_total,
                context: $context,
            );

            $shippingAdditionalTax = $shippingResult->includedInPrice ? 0 : $shippingResult->taxAmount;
            $totalTax += $shippingResult->taxAmount;
            $includedTax += $shippingResult->includedInPrice ? $shippingResult->taxAmount : 0;
            $weightedRateAmount += $shippingResult->ratePercentage * $session->shipping_total;
            $weightedRateBase += $session->shipping_total;
            $isExempt = $isExempt && $shippingResult->isExempt();

            if ($shippingResult->taxAmount > 0 || ! $shippingResult->isExempt()) {
                $taxableAmount += $session->shipping_total;
            }

            array_push($breakdown, ...$this->normalizeBreakdown($shippingResult, 'shipping'));
        }

        $rate = $weightedRateBase > 0
            ? round(($weightedRateAmount / $weightedRateBase) / 100, 2)
            : 0.0;

        return [
            'total' => $totalTax,
            'included_total' => $includedTax,
            'additional_total' => $totalTax - $includedTax,
            'lines' => $lines,
            'shipping_additional_tax' => $shippingAdditionalTax,
            'rate' => $rate,
            'breakdown' => $breakdown,
            'taxable_amount' => $taxableAmount,
            'exempt' => $isExempt,
        ];
    }

    /**
     * @return array{total: int, included_total: int, additional_total: int, lines: array<string, array{discount: int, additional_tax: int, included_tax: int}>, shipping_additional_tax: int, rate: float, breakdown: array<mixed>, taxable_amount: int, exempt: bool}
     */
    private function getDefaultTaxResult(CheckoutSession $session): array
    {
        // Default: no tax when tax package is not installed
        return [
            'total' => 0,
            'included_total' => 0,
            'additional_total' => 0,
            'lines' => [],
            'shipping_additional_tax' => 0,
            'rate' => 0.0,
            'breakdown' => [],
            'taxable_amount' => $session->subtotal - $session->discount_total,
            'exempt' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTaxContext(CheckoutSession $session): array
    {
        $shippingAddress = $session->shipping_data ?? [];
        $billingAddress = $session->billing_data ?? [];
        $preferredAddress = $shippingAddress !== [] ? $shippingAddress : $billingAddress;

        return [
            'currency' => $session->currency,
            'address' => $preferredAddress,
            'shipping_address' => $shippingAddress,
            'billing_address' => $billingAddress,
            'customer_id' => $session->customer_id,
            'customer_type' => $session->customer?->getMorphClass(),
        ];
    }

    /**
     * @return array<int, array{line_id: string, discount: int, tax_class: mixed, taxable_amount: int}>
     */
    private function buildTaxableItems(CheckoutSession $session): array
    {
        $pricingItems = array_values($session->pricing_data['items'] ?? []);
        $cartItems = array_values($session->cart_snapshot['items'] ?? []);

        $pricingById = [];
        foreach ($pricingItems as $pricingItem) {
            $id = $this->lineIdentity($pricingItem['item_id'] ?? null);

            if (isset($pricingById[$id])) {
                throw new InvalidArgumentException('Duplicate checkout pricing line identity.');
            }

            $pricingById[$id] = $pricingItem;
        }

        $baseAmounts = [];
        $matchedPricing = [];
        $cartIds = [];

        foreach ($cartItems as $index => $cartItem) {
            $pricingItem = [];
            $id = $this->lineIdentity($cartItem['id'] ?? null);

            if (isset($cartIds[$id])) {
                throw new InvalidArgumentException('Duplicate checkout cart line identity.');
            }

            $cartIds[$id] = true;

            if ($pricingById !== []) {

                if (! isset($pricingById[$id]) || isset($matchedPricing[$id])) {
                    throw new InvalidArgumentException('Checkout pricing does not match the cart snapshot.');
                }

                $pricingItem = $pricingById[$id];
                $matchedPricing[$id] = true;
            }

            $quantity = max(1, (int) ($pricingItem['quantity'] ?? $cartItem['quantity'] ?? 1));
            $lineTotal = (int) ($pricingItem['line_total'] ?? (($pricingItem['unit_price'] ?? $cartItem['price'] ?? 0) * $quantity));

            $baseAmounts[$index] = max(0, $lineTotal);
        }

        if (count($matchedPricing) !== count($pricingById)) {
            throw new InvalidArgumentException('Checkout pricing contains an unknown cart line.');
        }

        $allocatedDiscounts = $this->allocateAmount((int) $session->discount_total, $baseAmounts);

        $taxableItems = [];

        foreach ($baseAmounts as $index => $baseAmount) {
            $cartItem = $cartItems[$index];
            $pricingItem = $pricingById === [] ? [] : $pricingById[$this->lineIdentity($cartItem['id'] ?? null)];
            $attributes = is_array($cartItem['attributes'] ?? null) ? $cartItem['attributes'] : [];
            $isTaxable = (bool) ($attributes['is_taxable'] ?? $cartItem['is_taxable'] ?? $pricingItem['is_taxable'] ?? true);

            $taxableItems[] = [
                'line_id' => $this->lineIdentity($cartItem['id'] ?? null),
                'discount' => $allocatedDiscounts[$index] ?? 0,
                'tax_class' => $attributes['tax_class'] ?? $cartItem['tax_class'] ?? $pricingItem['tax_class'] ?? 'standard',
                'taxable_amount' => $isTaxable
                    ? max(0, $baseAmount - ($allocatedDiscounts[$index] ?? 0))
                    : 0,
            ];
        }

        return $taxableItems;
    }

    private function lineIdentity(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value)) || (string) $value === '') {
            throw new InvalidArgumentException('Checkout tax calculation requires a stable cart line identity.');
        }

        return (string) $value;
    }

    /**
     * @param  array<int, int>  $bases
     * @return array<int, int>
     */
    private function allocateAmount(int $amount, array $bases): array
    {
        $allocations = array_fill(0, count($bases), 0);
        $remainingAmount = min(max(0, $amount), array_sum($bases));
        $positiveIndexes = array_values(array_filter(array_keys($bases), fn (int $index): bool => ($bases[$index] ?? 0) > 0));

        if ($positiveIndexes === [] || $remainingAmount === 0) {
            return $allocations;
        }

        $remainingBase = array_sum(array_intersect_key($bases, array_flip($positiveIndexes)));

        foreach ($positiveIndexes as $position => $index) {
            if ($position === array_key_last($positiveIndexes)) {
                $allocations[$index] = $remainingAmount;

                break;
            }

            $base = $bases[$index];
            $allocated = (int) floor(($base / $remainingBase) * $remainingAmount);

            $allocations[$index] = $allocated;
            $remainingAmount -= $allocated;
            $remainingBase -= $base;
        }

        return $allocations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBreakdown(TaxResultData $result, string $source, ?string $lineId = null): array
    {
        return array_map(
            fn (array $entry): array => array_merge($entry, ['source' => $source, 'line_id' => $lineId, 'included_in_price' => $result->includedInPrice]),
            $result->breakdown,
        );
    }
}
