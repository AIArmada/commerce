<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;

/**
 * Interface for packages that provide cart conditions.
 *
 * Implemented by: Vouchers, Affiliates, Shipping providers
 * This enables loose coupling between cart and condition-providing packages.
 */
interface ConditionProviderInterface
{
    /**
     * Get conditions applicable to the cart.
     *
     * @return array<CartCondition>
     */
    public function getConditionsFor(Cart $cart): array;

    /**
     * Validate a condition is still applicable.
     * Called during checkout to ensure conditions are still valid.
     */
    public function validate(CartCondition $condition, Cart $cart): bool;

    /**
     * Get the condition type identifier.
     * Used for grouping and filtering conditions.
     */
    public function getType(): string;

    /**
     * Get the priority for condition application.
     * Lower values are applied first. Suggested ranges:
     * - Shipping: 50-99
     * - Vouchers/Discounts: 100-149
     * - Tax: 150-199
     * - Fees: 200+
     */
    public function getPriority(): int;
}
