<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Actions;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Exception;

final class RemoveConditionFromCartAction
{
    public function __construct(
        private readonly CartInstanceManager $cartInstanceManager,
    ) {}

    /**
     * Remove a condition from a cart
     *
     * @throws Exception
     */
    public function removeFromCart(Cart $cart, string $conditionName): bool
    {
        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);
        $success = $cartInstance->removeCondition($conditionName);

        if (! $success) {
            throw new Exception('Condition not found or could not be removed');
        }

        return true;
    }

    /**
     * Remove a condition from a specific cart item
     *
     * @throws Exception
     */
    public function removeFromItem(Cart $cart, string $itemId, string $conditionName): bool
    {
        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);
        $success = $cartInstance->removeItemCondition($itemId, $conditionName);

        if (! $success) {
            throw new Exception('Condition not found or could not be removed');
        }

        return true;
    }

    /**
     * Remove a condition record and sync the cart
     *
     * @throws Exception
     */
    public function removeCondition(CartCondition $record): bool
    {
        $cart = $record->cart;

        if (! $cart) {
            throw new Exception('Cart not found for condition');
        }

        if ($record->isItemLevel()) {
            return $this->removeFromItem($cart, $record->item_id, $record->name);
        }

        return $this->removeFromCart($cart, $record->name);
    }

    /**
     * Clear all conditions from a cart
     *
     * @throws Exception
     */
    public function clearAll(Cart $cart): void
    {
        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);

        // Clear all cart-level conditions
        $cartInstance->clearConditions();

        // Clear all item-level conditions
        $items = $cartInstance->getItems();
        foreach ($items as $item) {
            $cartInstance->clearItemConditions($item->id);
        }
    }

    /**
     * Clear all conditions of a specific type from a cart
     *
     * @throws Exception
     */
    public function clearByType(Cart $cart, string $type): void
    {
        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);
        $cartInstance->removeConditionsByType($type);
    }
}
