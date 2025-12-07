<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\ConditionProviderInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Services\FreeShippingEvaluator;
use AIArmada\Shipping\Services\RateShoppingEngine;
use Illuminate\Support\Collection;

/**
 * Provides shipping conditions for the cart.
 */
class ShippingConditionProvider implements ConditionProviderInterface
{
    public function __construct(
        protected readonly RateShoppingEngine $rateEngine,
        protected readonly ?FreeShippingEvaluator $freeShippingEvaluator = null
    ) {}

    /**
     * Get shipping conditions for the cart.
     *
     * @return Collection<int, ShippingCondition>
     */
    public function getConditions(Cart $cart): Collection
    {
        $destination = $this->getShippingAddress($cart);

        if ($destination === null) {
            return collect();
        }

        $rate = $this->getSelectedRate($cart, $destination);

        if ($rate === null) {
            return collect();
        }

        // Apply free shipping if qualified
        if ($this->freeShippingEvaluator !== null) {
            $freeResult = $this->freeShippingEvaluator->evaluate($cart);
            if ($freeResult !== null && $freeResult->applies) {
                $rate = $rate->withRate(0)->withNote('Free shipping applied');
            }
        }

        return collect([
            new ShippingCondition(
                name: $rate->service,
                type: 'shipping',
                value: $rate->rate,
                attributes: [
                    'carrier' => $rate->carrier,
                    'service' => $rate->service,
                    'estimated_days' => $rate->estimatedDays,
                    'quote_id' => $rate->quoteId,
                    'note' => $rate->note,
                ],
            ),
        ]);
    }

    /**
     * Get the selected or best shipping rate.
     */
    protected function getSelectedRate(Cart $cart, AddressData $destination): ?RateQuoteData
    {
        $metadata = $cart->metadata ?? [];
        $selectedMethod = $metadata['selected_shipping_method'] ?? null;

        $origin = $this->getOriginAddress();
        $packages = $this->cartToPackages($cart);

        if ($selectedMethod !== null) {
            // Get specific rate that was selected
            $allRates = $this->rateEngine->getAllRates($origin, $destination, $packages);

            return $allRates->first(
                fn (RateQuoteData $rate) => $rate->carrier === $selectedMethod['carrier']
                && $rate->service === $selectedMethod['service']
            );
        }

        // Default to best rate based on strategy
        return $this->rateEngine->getBestRate($origin, $destination, $packages);
    }

    /**
     * Get shipping address from cart metadata.
     */
    protected function getShippingAddress(Cart $cart): ?AddressData
    {
        $metadata = $cart->metadata ?? [];
        $addressData = $metadata['shipping_address'] ?? null;

        if ($addressData === null || ! is_array($addressData)) {
            return null;
        }

        return AddressData::from($addressData);
    }

    /**
     * Get the origin (sender) address from configuration.
     */
    protected function getOriginAddress(): AddressData
    {
        $origin = config('shipping.origin', []);

        return new AddressData(
            name: $origin['name'] ?? config('app.name', 'Store'),
            phone: $origin['phone'] ?? '',
            address: $origin['address'] ?? '',
            postCode: $origin['post_code'] ?? '',
            countryCode: $origin['country_code'] ?? 'MYS',
            state: $origin['state'] ?? null,
            city: $origin['city'] ?? null,
        );
    }

    /**
     * Convert cart items to package data.
     *
     * @return array<PackageData>
     */
    protected function cartToPackages(Cart $cart): array
    {
        $totalWeight = 0;
        $totalValue = 0;

        foreach ($cart->getItems() as $item) {
            $weight = $item->getAttribute('weight') ?? 0;
            $totalWeight += (int) ($weight * $item->quantity);
            $totalValue += $item->getSubtotal();
        }

        // For now, treat entire cart as single package
        return [
            new PackageData(
                weight: $totalWeight,
                declaredValue: $totalValue,
            ),
        ];
    }
}
