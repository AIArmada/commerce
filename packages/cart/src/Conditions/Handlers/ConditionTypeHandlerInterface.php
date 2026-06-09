<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Handlers;

use AIArmada\Cart\Cart;

interface ConditionTypeHandlerInterface
{
    /**
     * The condition type this handler manages (e.g. 'shipping').
     */
    public function type(): string;

    /**
     * Check if this type allows only one condition instance (singleton).
     */
    public function isSingleton(): bool;

    /**
     * Perform pre-add operations (e.g., remove existing conditions of this type).
     */
    public function beforeAdd(Cart $cart, array &$attributes): void;

    /**
     * Get all conditions of this type from the cart.
     */
    public function get(Cart $cart): mixed;

    /**
     * Remove all conditions of this type from the cart.
     */
    public function remove(Cart $cart): void;
}
