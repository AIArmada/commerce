<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;

final class CalculatePricingStep extends AbstractCheckoutStep
{
    public function getIdentifier(): string
    {
        return 'calculate_pricing';
    }

    public function getName(): string
    {
        return 'Calculate Pricing';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['validate_cart', 'resolve_customer'];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        if (empty($items)) {
            return $this->failed('No items in cart snapshot');
        }

        $pricingData = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $itemPrice = $item['price'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $lineTotal = $itemPrice * $quantity;

            $pricingData['items'][] = [
                'item_id' => $item['id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'unit_price' => $itemPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineTotal;
        }

        // Apply any pricing rules if pricing package is available
        if ($this->hasPricingPackage()) {
            $pricingData = $this->applyPricingRules($session, $pricingData, $subtotal);
            $subtotal = $pricingData['subtotal'] ?? $subtotal;
        }

        $pricingData['subtotal'] = $subtotal;
        $pricingData['calculated_at'] = now()->toIso8601String();

        $session->update([
            'pricing_data' => $pricingData,
            'subtotal' => $subtotal,
        ]);

        $session->calculateTotals();
        $session->save();

        return $this->success('Pricing calculated', [
            'subtotal' => $subtotal,
            'item_count' => count($items),
        ]);
    }

    private function hasPricingPackage(): bool
    {
        return class_exists(\AIArmada\Pricing\PricingServiceProvider::class);
    }

    /**
     * @param  array<string, mixed>  $pricingData
     * @return array<string, mixed>
     */
    private function applyPricingRules(CheckoutSession $session, array $pricingData, int $subtotal): array
    {
        // Integration with pricing package
        // This will be enhanced when pricing package integration is fully implemented

        $pricingData['subtotal'] = $subtotal;

        return $pricingData;
    }
}
