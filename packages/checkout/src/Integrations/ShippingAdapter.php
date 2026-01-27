<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Jnt\Facades\JntExpress;
use AIArmada\Shipping\Contracts\ShippingManagerInterface;
use Throwable;

final class ShippingAdapter
{
    private bool $hasJnt = false;

    public function __construct()
    {
        $this->hasJnt = class_exists(JntExpress::class)
            && config('checkout.integrations.shipping.jnt.enabled', true);
    }

    /**
     * Get available shipping rates for the checkout session.
     *
     * @return array<array<string, mixed>>
     */
    public function getRates(CheckoutSession $session): array
    {
        if (! class_exists(ShippingManagerInterface::class)) {
            return $this->getDefaultRates($session);
        }

        $shippingManager = app(ShippingManagerInterface::class);

        $shippingData = $session->shipping_data ?? [];
        $cartSnapshot = $session->cart_snapshot ?? [];

        $request = $this->buildShippingRequest($session, $shippingData, $cartSnapshot);

        $rates = $shippingManager->getRates($request);

        if ($this->hasJnt && config('checkout.integrations.shipping.jnt.auto_detect', true)) {
            $jntRates = $this->getJntRates($session, $shippingData);
            $rates = array_merge($rates, $jntRates);
        }

        return $rates;
    }

    public function hasJntIntegration(): bool
    {
        return $this->hasJnt;
    }

    /**
     * Get JNT-specific shipping data.
     *
     * @param  array<string, mixed>  $selectedRate
     * @return array<string, mixed>
     */
    public function getJntShippingData(CheckoutSession $session, array $selectedRate): array
    {
        if (! $this->hasJnt) {
            return [];
        }

        $shippingData = $session->shipping_data ?? [];

        return [
            'service_type' => $selectedRate['service_type'] ?? 'EZ',
            'estimated_delivery' => $selectedRate['estimated_delivery'] ?? null,
            'tracking_available' => true,
            'origin' => $this->getOriginData(),
            'destination' => [
                'name' => $shippingData['name'] ?? '',
                'phone' => $shippingData['phone'] ?? '',
                'address' => $this->formatAddress($shippingData),
                'postcode' => $shippingData['postcode'] ?? '',
                'city' => $shippingData['city'] ?? '',
                'state' => $shippingData['state'] ?? '',
                'country' => $shippingData['country'] ?? 'MY',
            ],
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getDefaultRates(CheckoutSession $session): array
    {
        /** @var int $defaultRate */
        $defaultRate = config('checkout.defaults.shipping_rate', 1000);

        return [
            [
                'method_id' => 'flat_rate',
                'carrier' => 'Standard',
                'name' => 'Standard Shipping',
                'rate' => $defaultRate,
                'currency' => $session->currency,
                'estimated_days' => '3-5',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $shippingData
     * @param  array<string, mixed>  $cartSnapshot
     * @return array<string, mixed>
     */
    private function buildShippingRequest(CheckoutSession $session, array $shippingData, array $cartSnapshot): array
    {
        $items = $cartSnapshot['items'] ?? [];

        $totalWeight = 0;
        $packageItems = [];

        foreach ($items as $item) {
            $weight = ($item['weight'] ?? 0) * ($item['quantity'] ?? 1);
            $totalWeight += $weight;

            $packageItems[] = [
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'weight' => $item['weight'] ?? 0,
                'dimensions' => $item['dimensions'] ?? null,
            ];
        }

        return [
            'destination' => [
                'address_line_1' => $shippingData['address_line_1'] ?? '',
                'address_line_2' => $shippingData['address_line_2'] ?? '',
                'city' => $shippingData['city'] ?? '',
                'state' => $shippingData['state'] ?? '',
                'postcode' => $shippingData['postcode'] ?? '',
                'country' => $shippingData['country'] ?? 'MY',
            ],
            'items' => $packageItems,
            'total_weight' => $totalWeight,
            'subtotal' => $session->subtotal,
            'currency' => $session->currency,
        ];
    }

    /**
     * @param  array<string, mixed>  $shippingData
     * @return array<array<string, mixed>>
     */
    private function getJntRates(CheckoutSession $session, array $shippingData): array
    {
        if (! $this->hasJnt) {
            return [];
        }

        try {
            $cartSnapshot = $session->cart_snapshot ?? [];
            $items = $cartSnapshot['items'] ?? [];

            $totalWeight = array_reduce($items, function (int $carry, array $item) {
                return $carry + (($item['weight'] ?? 500) * ($item['quantity'] ?? 1));
            }, 0);

            $originPostcode = config('jnt.origin.postcode', config('shipping.origin.postcode', ''));
            $destinationPostcode = $shippingData['postcode'] ?? '';

            if (empty($originPostcode) || empty($destinationPostcode)) {
                return [];
            }

            // The JNT package doesn't have a getRates method - return empty for now
            // JNT integration would need to be implemented based on actual JNT API
            return [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getOriginData(): array
    {
        return [
            'name' => config('jnt.origin.name', config('shipping.origin.name', '')),
            'phone' => config('jnt.origin.phone', config('shipping.origin.phone', '')),
            'address' => config('jnt.origin.address', config('shipping.origin.address', '')),
            'postcode' => config('jnt.origin.postcode', config('shipping.origin.postcode', '')),
            'city' => config('jnt.origin.city', config('shipping.origin.city', '')),
            'state' => config('jnt.origin.state', config('shipping.origin.state', '')),
            'country' => config('jnt.origin.country', config('shipping.origin.country', 'MY')),
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function formatAddress(array $address): string
    {
        $parts = array_filter([
            $address['address_line_1'] ?? '',
            $address['address_line_2'] ?? '',
        ]);

        return implode(', ', $parts);
    }
}
