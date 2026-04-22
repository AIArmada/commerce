<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Data\TaxResultData;

final class TaxAdapter
{
    /**
     * Calculate tax for the checkout session.
     *
     * @return array{total: int, rate: float, breakdown: array<array<string, mixed>>, taxable_amount: int, exempt: bool}
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
        $weightedRateAmount = 0;
        $weightedRateBase = 0;
        $taxableAmount = 0;
        $breakdown = [];
        $isExempt = true;

        foreach ($taxableItems as $taxableItem) {
            $lineTaxableAmount = (int) ($taxableItem['taxable_amount'] ?? 0);

            if ($lineTaxableAmount <= 0) {
                continue;
            }

            $result = $taxCalculator->calculateTax(
                amountInCents: $lineTaxableAmount,
                taxClass: (string) ($taxableItem['tax_class'] ?? 'standard'),
                context: $context,
            );

            $taxableAmount += $lineTaxableAmount;
            $totalTax += $result->taxAmount;
            $weightedRateAmount += $result->ratePercentage * $lineTaxableAmount;
            $weightedRateBase += $lineTaxableAmount;
            $isExempt = $isExempt && $result->isExempt();
            $breakdown = array_merge($breakdown, $this->normalizeBreakdown($result, 'items'));
        }

        if ($session->shipping_total > 0) {
            $shippingResult = $taxCalculator->calculateShippingTax(
                shippingAmountInCents: $session->shipping_total,
                context: $context,
            );

            $totalTax += $shippingResult->taxAmount;
            $weightedRateAmount += $shippingResult->ratePercentage * $session->shipping_total;
            $weightedRateBase += $session->shipping_total;
            $isExempt = $isExempt && $shippingResult->isExempt();

            if ($shippingResult->taxAmount > 0 || ! $shippingResult->isExempt()) {
                $taxableAmount += $session->shipping_total;
            }

            $breakdown = array_merge($breakdown, $this->normalizeBreakdown($shippingResult, 'shipping'));
        }

        $rate = $weightedRateBase > 0
            ? round(($weightedRateAmount / $weightedRateBase) / 100, 2)
            : 0.0;

        return [
            'total' => $totalTax,
            'rate' => $rate,
            'breakdown' => $breakdown,
            'taxable_amount' => $taxableAmount,
            'exempt' => $isExempt,
        ];
    }

    /**
     * @return array{total: int, rate: float, breakdown: array<mixed>, taxable_amount: int, exempt: bool}
     */
    private function getDefaultTaxResult(CheckoutSession $session): array
    {
        // Default: no tax when tax package is not installed
        return [
            'total' => 0,
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
            'address' => $preferredAddress,
            'shipping_address' => $shippingAddress,
            'billing_address' => $billingAddress,
            'customer_id' => $session->customer_id,
            'customer_type' => $session->customer?->getMorphClass(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTaxableItems(CheckoutSession $session): array
    {
        $pricingItems = array_values($session->pricing_data['items'] ?? []);
        $cartItems = array_values($session->cart_snapshot['items'] ?? []);

        $baseAmounts = [];

        foreach ($pricingItems as $index => $pricingItem) {
            $cartItem = $cartItems[$index] ?? [];
            $quantity = max(1, (int) ($pricingItem['quantity'] ?? $cartItem['quantity'] ?? 1));
            $lineTotal = (int) ($pricingItem['line_total'] ?? (($pricingItem['unit_price'] ?? $cartItem['price'] ?? 0) * $quantity));

            $baseAmounts[$index] = max(0, $lineTotal);
        }

        if ($baseAmounts === []) {
            foreach ($cartItems as $index => $cartItem) {
                $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
                $baseAmounts[$index] = max(0, (int) (($cartItem['price'] ?? 0) * $quantity));
            }
        }

        $allocatedDiscounts = $this->allocateAmount((int) $session->discount_total, $baseAmounts);

        $taxableItems = [];

        foreach ($baseAmounts as $index => $baseAmount) {
            $pricingItem = $pricingItems[$index] ?? [];
            $cartItem = $cartItems[$index] ?? [];

            $taxableItems[] = [
                'tax_class' => $cartItem['tax_class'] ?? $pricingItem['tax_class'] ?? 'standard',
                'taxable_amount' => max(0, $baseAmount - ($allocatedDiscounts[$index] ?? 0)),
            ];
        }

        return $taxableItems;
    }

    /**
     * @param  array<int, int>  $bases
     * @return array<int, int>
     */
    private function allocateAmount(int $amount, array $bases): array
    {
        $allocations = array_fill(0, count($bases), 0);
        $remainingAmount = max(0, $amount);
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
    private function normalizeBreakdown(TaxResultData $result, string $source): array
    {
        return array_map(
            fn (array $entry): array => array_merge($entry, ['source' => $source]),
            $result->breakdown,
        );
    }
}
