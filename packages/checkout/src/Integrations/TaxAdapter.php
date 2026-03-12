<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Tax\Contracts\TaxServiceInterface;

final class TaxAdapter
{
    /**
     * Calculate tax for the checkout session.
     *
     * @return array{total: int, rate: float, breakdown: array<array<string, mixed>>, taxable_amount: int, exempt: bool}
     */
    public function calculateTax(CheckoutSession $session): array
    {
        if (! class_exists(TaxServiceInterface::class)) {
            return $this->getDefaultTaxResult($session);
        }

        $taxService = app(TaxServiceInterface::class);

        $shippingData = $session->shipping_data ?? [];
        $billingData = $session->billing_data ?? [];
        $cartSnapshot = $session->cart_snapshot ?? [];

        // Determine tax address (usually shipping, fallback to billing)
        $taxAddress = ! empty($shippingData) ? $shippingData : $billingData;

        // Calculate taxable amount (subtotal - discounts + shipping if taxable)
        $taxableAmount = $session->subtotal - $session->discount_total;

        if (config('tax.shipping_taxable', false)) {
            $taxableAmount += $session->shipping_total;
        }

        // Get tax calculation from tax service
        $result = $taxService->calculate([
            'amount' => $taxableAmount,
            'address' => [
                'country' => $taxAddress['country'] ?? 'MY',
                'state' => $taxAddress['state'] ?? '',
                'city' => $taxAddress['city'] ?? '',
                'postcode' => $taxAddress['postcode'] ?? '',
            ],
            'items' => $this->buildTaxableItems($cartSnapshot['items'] ?? []),
            'customer_id' => $session->customer_id,
        ]);

        return [
            'total' => $result['total'] ?? 0,
            'rate' => $result['rate'] ?? 0.0,
            'breakdown' => $result['breakdown'] ?? [],
            'taxable_amount' => $taxableAmount,
            'exempt' => $result['exempt'] ?? false,
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
     * @param  array<array<string, mixed>>  $items
     * @return array<array<string, mixed>>
     */
    private function buildTaxableItems(array $items): array
    {
        return array_map(fn (array $item) => [
            'product_id' => $item['product_id'] ?? null,
            'name' => $item['name'] ?? '',
            'quantity' => $item['quantity'] ?? 1,
            'unit_price' => $item['price'] ?? 0,
            'total' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
            'tax_class' => $item['tax_class'] ?? 'standard',
            'taxable' => $item['taxable'] ?? true,
        ], $items);
    }
}
