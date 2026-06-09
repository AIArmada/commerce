<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Handlers;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;

final class ShippingConditionHandler implements ConditionTypeHandlerInterface
{
    public function type(): string
    {
        return 'shipping';
    }

    public function isSingleton(): bool
    {
        return true;
    }

    public function beforeAdd(Cart $cart, array &$attributes): void
    {
        $this->remove($cart);
    }

    public function get(Cart $cart): ?CartCondition
    {
        return $cart->getConditionsByType('shipping')?->first();
    }

    public function remove(Cart $cart): void
    {
        $cart->removeConditionsByType('shipping');
    }
}
