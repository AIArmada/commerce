<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Integrations\ShippingAdapter;
use AIArmada\Checkout\Models\CheckoutSession;

final class CalculateShippingStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly ShippingAdapter $shippingAdapter,
    ) {}

    public function getIdentifier(): string
    {
        return 'calculate_shipping';
    }

    public function getName(): string
    {
        return 'Calculate Shipping';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['calculate_pricing'];
    }

    public function canSkip(CheckoutSession $session): bool
    {
        // Skip if shipping integration is disabled
        if (! config('checkout.integrations.shipping.enabled', true)) {
            return true;
        }

        // Skip if all items are digital (don't require shipping)
        return $this->isShippingNotRequired($session);
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $errors = [];

        $shippingData = $session->shipping_data ?? [];

        if (config('checkout.integrations.shipping.require_selection', true)) {
            if ($session->selected_shipping_method === null && empty($shippingData)) {
                $errors['shipping'] = 'Shipping address is required';
            }
        }

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $shippingData = $session->shipping_data ?? [];

        // Get available shipping rates
        $rates = $this->shippingAdapter->getRates($session);

        if (empty($rates)) {
            // Check if shipping is not required (e.g., digital products only)
            if ($this->isShippingNotRequired($session)) {
                $session->update([
                    'shipping_total' => 0,
                    'shipping_data' => array_merge($shippingData, [
                        'not_required' => true,
                        'reason' => 'Digital products only',
                    ]),
                ]);

                return $this->success('Shipping not required for this order');
            }

            return $this->failed('No shipping rates available', ['shipping' => 'No shipping methods available for your location']);
        }

        // If shipping method already selected, calculate with that method
        $selectedMethod = $session->selected_shipping_method;
        $selectedRate = null;

        if ($selectedMethod !== null) {
            $selectedRate = collect($rates)->firstWhere('method_id', $selectedMethod);
        }

        // If no method selected or selected method not available, use cheapest
        if ($selectedRate === null) {
            $selectedRate = collect($rates)->sortBy('rate')->first();
            $selectedMethod = $selectedRate['method_id'] ?? null;
        }

        $shippingTotal = $selectedRate['rate'] ?? 0;

        $shippingData = array_merge($shippingData, [
            'available_rates' => $rates,
            'selected_rate' => $selectedRate,
            'calculated_at' => now()->toIso8601String(),
        ]);

        // Check for JNT integration
        if ($this->shippingAdapter->hasJntIntegration() && $this->shouldUseJnt($selectedRate)) {
            $jntData = $this->shippingAdapter->getJntShippingData($session, $selectedRate);
            $shippingData['jnt'] = $jntData;
        }

        $session->update([
            'shipping_data' => $shippingData,
            'shipping_total' => $shippingTotal,
            'selected_shipping_method' => $selectedMethod,
        ]);

        $session->calculateTotals();
        $session->save();

        return $this->success('Shipping calculated', [
            'shipping_total' => $shippingTotal,
            'method' => $selectedMethod,
            'rates_count' => count($rates),
        ]);
    }

    private function isShippingNotRequired(CheckoutSession $session): bool
    {
        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        // Check if all items are digital/non-physical
        foreach ($items as $item) {
            $requiresShipping = $item['requires_shipping'] ?? true;
            if ($requiresShipping) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $rate
     */
    private function shouldUseJnt(array $rate): bool
    {
        $carrier = $rate['carrier'] ?? '';

        return mb_strtolower($carrier) === 'jnt' || str_contains(mb_strtolower($carrier), 'j&t');
    }
}
