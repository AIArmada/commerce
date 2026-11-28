<?php

declare(strict_types=1);

namespace AIArmada\Jnt\EntryPoints\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Runtime\ConditionProviderInterface;
use AIArmada\CommerceSupport\Support\CompositeComponents;
use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Support\ShippingRateCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Calculates J&T Express shipping rates and exposes them as cart conditions.
 *
 * This is the single cart integration path for J&T. Monetary values remain
 * integer minor units from configuration through the generated quote.
 */
final class JntShippingCalculator implements ConditionProviderInterface
{
    private const string CONDITION_TYPE = 'shipping';

    private const string SHIPPING_ADDRESS_KEY = 'jnt_shipping_address';

    private const int PRIORITY = 75;

    /**
     * Calculate shipping cost for the cart to a destination address.
     *
     * @return array<string, mixed>|null Shipping quote or null if unavailable
     */
    public function calculateShipping(Cart $cart, AddressData $destination): ?array
    {
        $totalWeight = $this->getCartWeight($cart);

        if ($totalWeight <= 0) {
            return null;
        }

        if ($this->getOriginAddress() === null) {
            return null;
        }

        $quote = ShippingRateCalculator::quote($totalWeight, $destination->postCode, $destination->state);

        return [
            'service_name' => (string) config('jnt.shipping.default_service_name', 'J&T Express'),
            'service_type' => (string) config('jnt.shipping.default_service_type', 'EZ'),
            'amount' => $quote['amount'],
            'weight_kg' => $totalWeight / 1000,
            'estimated_days' => $quote['estimated_days'],
            'calculated_at' => CarbonImmutable::now()->toISOString(),
            'cart_weight' => $totalWeight,
            'quote_id' => 'jnt_quote_' . (string) Str::ulid(),
        ];
    }

    /**
     * Get total weight of all cart items in grams.
     */
    public function getCartWeight(Cart $cart): int
    {
        $totalWeight = 0;

        foreach ($cart->getItems() as $item) {
            $quantity = (int) $item->quantity;
            $components = $item->getAttribute('components');

            if ($components !== null && $components !== []) {
                foreach (CompositeComponents::expand(CompositeComponents::read($components), $quantity) as $component) {
                    if ($component['requires_shipping'] && $component['weight'] !== null) {
                        $totalWeight = $this->addWeight($totalWeight, $component['weight'], $component['quantity']);
                    }
                }

                continue;
            }

            if ($item->getAttribute('requires_shipping') === false) {
                continue;
            }

            $weight = $item->getAttribute('weight_grams') ?? $item->getAttribute('weight');

            if (! is_numeric($weight) || ! is_finite((float) $weight) || $weight <= 0) {
                continue;
            }

            if ((float) $weight >= (float) PHP_INT_MAX) {
                throw new InvalidArgumentException('Cart item weight exceeds the supported range.');
            }

            $totalWeight = $this->addWeight($totalWeight, (int) round((float) $weight), $quantity);
        }

        return $totalWeight;
    }

    private function addWeight(int $total, int $weight, int $quantity): int
    {
        if ($weight <= 0 || $quantity <= 0) {
            return $total;
        }

        if ($weight > intdiv(PHP_INT_MAX - $total, $quantity)) {
            throw new InvalidArgumentException('Cart weight exceeds the supported range.');
        }

        return $total + ($weight * $quantity);
    }

    /**
     * Get shipping conditions applicable to the cart.
     *
     * @return array<CartCondition>
     */
    public function getConditionsFor(Cart $cart): array
    {
        $destination = $this->destination($cart);

        if ($destination === null) {
            return [];
        }

        $quote = $this->calculateShipping($cart, $destination);

        if ($quote === null) {
            return [];
        }

        return [$this->createConditionFromQuote($quote)];
    }

    public function validate(CartCondition $condition, Cart $cart): bool
    {
        if ($condition->getType() !== self::CONDITION_TYPE) {
            return true;
        }

        return $this->destination($cart) !== null && $this->getCartWeight($cart) > 0 && $this->getOriginAddress() !== null;
    }

    private function destination(Cart $cart): ?AddressData
    {
        $data = $cart->getMetadata(self::SHIPPING_ADDRESS_KEY);

        if (! is_array($data)) {
            return null;
        }

        foreach (['name', 'phone', 'address', 'postCode'] as $field) {
            if (! is_string($data[$field] ?? null) || mb_trim($data[$field]) === '') {
                return null;
            }
        }

        foreach (['countryCode', 'prov', 'city', 'area', 'email', 'idCard'] as $field) {
            if (isset($data[$field]) && ! is_string($data[$field])) {
                return null;
            }
        }

        return AddressData::fromApiArray($data);
    }

    public function getType(): string
    {
        return self::CONDITION_TYPE;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * Get the origin (sender) address from configuration.
     */
    private function getOriginAddress(): ?AddressData
    {
        $origin = config('jnt.shipping.origin');

        if (! is_array($origin) || empty($origin['name'])) {
            return null;
        }

        return new AddressData(
            name: (string) $origin['name'],
            phone: (string) ($origin['phone'] ?? ''),
            address: (string) ($origin['address'] ?? ''),
            postCode: (string) ($origin['post_code'] ?? ''),
            countryCode: (string) ($origin['country_code'] ?? 'MYS'),
            state: isset($origin['state']) ? (string) $origin['state'] : null,
            city: isset($origin['city']) ? (string) $origin['city'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function createConditionFromQuote(array $quote): CartCondition
    {
        return new CartCondition(
            name: (string) ($quote['service_name'] ?? 'jnt_shipping'),
            type: self::CONDITION_TYPE,
            target: $this->buildTargetDefinition(),
            value: (string) ($quote['amount'] ?? 0),
            attributes: $this->buildAttributes($quote),
            order: self::PRIORITY,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTargetDefinition(): array
    {
        return [
            'scope' => ConditionScope::CART->value,
            'phase' => ConditionPhase::SHIPPING->value,
            'application' => ConditionApplication::AGGREGATE->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function buildAttributes(array $quote): array
    {
        return [
            'provider' => 'jnt',
            'service_type' => $quote['service_type'] ?? 'standard',
            'service_name' => $quote['service_name'] ?? 'J&T Express',
            'estimated_days' => $quote['estimated_days'] ?? null,
            'weight_kg' => $quote['weight_kg'] ?? null,
            'calculated_at' => $quote['calculated_at'] ?? CarbonImmutable::now()->toISOString(),
            'quote_id' => $quote['quote_id'] ?? null,
        ];
    }
}
