<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Target;
use Akaunting\Money\Money;

/**
 * Built-in shipping calculator for common shipping scenarios.
 *
 * All amounts are in cents (the smallest currency unit), matching cart's internal representation.
 * For example: 800 = RM 8.00, 1500 = $15.00, 10000 = $100.00.
 * Supports flat-rate, weight-based, tiered, and free shipping thresholds.
 * For complex shipping (carriers, zones, real-time rates), integrate with
 * a dedicated service like EasyPost, Shippo, or carrier APIs directly.
 *
 * @example
 * ```php
 * $calculator = ShippingCalculator::create()
 *     ->flatRate(1500)                    // $15.00 base (1500 cents)
 *     ->freeAbove(10000)                  // Free above $100 (10000 cents)
 *     ->weightRate(50, perGrams: 1000);   // +$0.50 per kg (50 cents)
 *
 * $shipping = $calculator->calculate($cart);
 * $calculator->applyToCart($cart);
 * ```
 */
final class ShippingCalculator
{
    private int $flatRate = 0;

    private int $freeThreshold = 0;

    private int $weightRate = 0;

    private int $weightUnit = 1000; // grams per unit (1000 = per kg)

    private int $minimumCharge = 0;

    private int $maximumCharge = 0;

    /** @var array<string, int> */
    private array $zoneRates = [];

    /** @var array<array{min: int, max: int|null, rate: int}> */
    private array $tiers = [];

    private string $defaultZone = 'default';

    private string $currency = 'MYR';

    private string $conditionName = 'Shipping';

    private function __construct()
    {
        $this->currency = config('cart.money.default_currency', 'MYR');
    }

    /**
     * Create a new shipping calculator instance.
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Create calculator for weight-based shipping.
     *
     * @param  int  $totalWeight  Total weight in grams
     */
    public static function forWeight(int $totalWeight): self
    {
        $calculator = new self;
        $calculator->weightRate = 0;

        return $calculator;
    }

    /**
     * Create with common Malaysian shipping presets.
     * All amounts in cents (sen).
     */
    public static function malaysiaDefaults(): self
    {
        return self::create()
            ->currency('MYR')
            ->flatRate(800)                          // RM 8.00 base (800 sen)
            ->freeAbove(15000)                       // Free above RM 150 (15000 sen)
            ->zoneRate('MY-PENINSULA', 800)          // RM 8.00 West Malaysia
            ->zoneRate('MY-EAST', 1500)              // RM 15.00 East Malaysia
            ->zoneRate('SG', 2500)                   // RM 25.00 Singapore
            ->zoneRate('INTERNATIONAL', 5000)        // RM 50.00 International
            ->weightRate(100, perGrams: 1000)        // +RM 1.00 per kg (100 sen)
            ->maximum(10000)                         // Cap at RM 100 (10000 sen)
            ->named('Standard Shipping');
    }

    /**
     * Create with tiered shipping based on order value.
     * All amounts in cents.
     */
    public static function tieredDefaults(): self
    {
        return self::create()
            ->tier(0, 5000, 1500)         // $0-50: $15 shipping (0-5000 cents: 1500 cents)
            ->tier(5000, 10000, 1000)     // $50-100: $10 shipping
            ->tier(10000, 20000, 500)     // $100-200: $5 shipping
            ->tier(20000, null, 0)        // $200+: Free
            ->named('Tiered Shipping');
    }

    /**
     * Set flat shipping rate.
     *
     * @param  int  $amount  Shipping rate in cents (e.g., 800 for $8.00)
     */
    public function flatRate(int $amount): self
    {
        $this->flatRate = $amount;

        return $this;
    }

    /**
     * Set free shipping threshold (cart subtotal).
     *
     * @param  int  $threshold  Minimum subtotal in cents for free shipping (e.g., 10000 for $100)
     */
    public function freeAbove(int $threshold): self
    {
        $this->freeThreshold = $threshold;

        return $this;
    }

    /**
     * Set weight-based rate per weight unit.
     *
     * @param  int  $rate  Rate per weight unit in cents (e.g., 50 for $0.50 per kg)
     * @param  int  $perGrams  Weight unit in grams (default: 1000 = per kg)
     */
    public function weightRate(int $rate, int $perGrams = 1000): self
    {
        $this->weightRate = $rate;
        $this->weightUnit = $perGrams;

        return $this;
    }

    /**
     * Set minimum shipping charge.
     *
     * @param  int  $amount  Minimum charge in cents (e.g., 500 for $5.00)
     */
    public function minimum(int $amount): self
    {
        $this->minimumCharge = $amount;

        return $this;
    }

    /**
     * Set maximum shipping charge (cap).
     *
     * @param  int  $amount  Maximum charge in cents (e.g., 5000 for $50.00)
     */
    public function maximum(int $amount): self
    {
        $this->maximumCharge = $amount;

        return $this;
    }

    /**
     * Add a zone-specific rate.
     *
     * @param  string  $zone  Zone code (e.g., 'MY-EAST', 'SG', 'INTERNATIONAL')
     * @param  int  $rate  Shipping rate in cents (e.g., 1500 for $15.00)
     */
    public function zoneRate(string $zone, int $rate): self
    {
        $this->zoneRates[$zone] = $rate;

        return $this;
    }

    /**
     * Set the default zone for calculations.
     */
    public function defaultZone(string $zone): self
    {
        $this->defaultZone = $zone;

        return $this;
    }

    /**
     * Add a tiered rate based on cart subtotal.
     *
     * @param  int  $minSubtotal  Minimum subtotal in cents (inclusive)
     * @param  int|null  $maxSubtotal  Maximum subtotal in cents (exclusive), null for unlimited
     * @param  int  $rate  Shipping rate in cents for this tier
     */
    public function tier(int $minSubtotal, ?int $maxSubtotal, int $rate): self
    {
        $this->tiers[] = [
            'min' => $minSubtotal,
            'max' => $maxSubtotal,
            'rate' => $rate,
        ];

        return $this;
    }

    /**
     * Set the condition name for cart display.
     */
    public function named(string $name): self
    {
        $this->conditionName = $name;

        return $this;
    }

    /**
     * Set the currency for Money objects.
     */
    public function currency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Calculate shipping cost for a cart.
     *
     * @param  Cart  $cart  The cart to calculate shipping for
     * @param  string|null  $zone  Optional zone override
     * @return Money The calculated shipping cost
     */
    public function calculate(Cart $cart, ?string $zone = null): Money
    {
        $subtotal = (int) $cart->getRawSubtotal();
        $zone = $zone ?? $this->defaultZone;

        // Check free shipping threshold
        if ($this->freeThreshold > 0 && $subtotal >= $this->freeThreshold) {
            return Money::{$this->currency}(0);
        }

        // Start with flat rate or zone rate
        $shipping = $this->zoneRates[$zone] ?? $this->flatRate;

        // Add weight-based charges
        if ($this->weightRate > 0) {
            $weight = $this->getTotalWeight($cart);
            $units = (int) ceil($weight / $this->weightUnit);
            $shipping += $units * $this->weightRate;
        }

        // Check tiered rates
        if (! empty($this->tiers)) {
            foreach ($this->tiers as $tier) {
                if ($subtotal >= $tier['min'] && ($tier['max'] === null || $subtotal < $tier['max'])) {
                    $shipping = $tier['rate'];

                    break;
                }
            }
        }

        // Apply minimum/maximum caps
        if ($this->minimumCharge > 0 && $shipping < $this->minimumCharge) {
            $shipping = $this->minimumCharge;
        }

        if ($this->maximumCharge > 0 && $shipping > $this->maximumCharge) {
            $shipping = $this->maximumCharge;
        }

        return Money::{$this->currency}($shipping);
    }

    /**
     * Calculate shipping and apply to cart as condition.
     *
     * @param  Cart  $cart  The cart to apply shipping to
     * @param  string|null  $zone  Optional zone override
     * @return CartCondition The shipping condition
     */
    public function applyToCart(Cart $cart, ?string $zone = null): CartCondition
    {
        $shipping = $this->calculate($cart, $zone);

        $condition = new CartCondition(
            name: $this->conditionName,
            type: 'shipping',
            value: $shipping->getAmount(),
            target: Target::cart()
                ->phase(ConditionPhase::SHIPPING)
                ->applyAggregate()
                ->build(),
            order: ConditionPhase::SHIPPING->order(),
            attributes: [
                'zone' => $zone ?? $this->defaultZone,
                'method' => 'calculated',
            ]
        );

        $cart->addCondition($condition);

        return $condition;
    }

    /**
     * Create a shipping condition without calculating.
     *
     * @param  int  $amount  Shipping amount in cents (e.g., 1200 for $12.00)
     * @param  string  $method  Shipping method identifier
     */
    public function createCondition(int $amount, string $method = 'standard'): CartCondition
    {
        return new CartCondition(
            name: $this->conditionName,
            type: 'shipping',
            value: $amount,
            target: Target::cart()
                ->phase(ConditionPhase::SHIPPING)
                ->applyAggregate()
                ->build(),
            order: ConditionPhase::SHIPPING->order(),
            attributes: [
                'method' => $method,
            ]
        );
    }

    /**
     * Get total weight from cart items.
     *
     * @return int Total weight in grams
     */
    private function getTotalWeight(Cart $cart): int
    {
        $totalWeight = 0;
        foreach ($cart->getItems() as $item) {
            $weight = $item->attributes->get('weight', 0);
            $totalWeight += (int) $weight * $item->quantity;
        }

        return $totalWeight;
    }
}
