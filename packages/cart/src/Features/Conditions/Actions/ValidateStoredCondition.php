<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Conditions\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Features\Conditions\Queries\ActiveGlobalConditions;

final class ValidateStoredCondition
{
    /**
     * @return array{
     *     is_valid: bool,
     *     removed_conditions: array<string>,
     *     price_changed: bool,
     *     old_total: int,
     *     new_total: int
     * }
     */
    public function handle(Cart $cart): array
    {
        $active = ActiveGlobalConditions::forCart($cart);
        $oldTotal = $cart->total();
        $removedConditions = ActiveGlobalConditions::removeInactive($cart, $active);

        $newTotal = $removedConditions === [] ? $oldTotal : $cart->total();

        return [
            'is_valid' => $removedConditions === [],
            'removed_conditions' => $removedConditions,
            'price_changed' => ! $oldTotal->equals($newTotal),
            'old_total' => (int) $oldTotal->getAmount(),
            'new_total' => (int) $newTotal->getAmount(),
        ];
    }
}
