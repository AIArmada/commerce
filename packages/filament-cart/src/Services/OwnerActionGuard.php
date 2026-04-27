<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\Cart\Models\Condition;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartCondition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class OwnerActionGuard
{
    public static function resolveCartRecord(mixed $record, mixed $livewire = null): Cart
    {
        $cart = $record instanceof Cart ? $record : null;

        if ($cart === null && is_object($livewire) && method_exists($livewire, 'getOwnerRecord')) {
            $ownerRecord = $livewire->getOwnerRecord();
            $cart = $ownerRecord instanceof Cart ? $ownerRecord : null;
        }

        if (! $cart instanceof Cart) {
            throw new InvalidArgumentException('Cart actions require a cart snapshot record.');
        }

        return self::authorizeCart($cart);
    }

    public static function authorizeCart(Cart $cart): Cart
    {
        if (! Cart::ownerScopingEnabled()) {
            return $cart;
        }

        /** @var Cart $validated */
        $validated = OwnerWriteGuard::findOrFailForOwner(
            Cart::class,
            (string) $cart->getKey(),
            includeGlobal: false,
            message: 'Cart is not accessible in the current owner scope.',
        );

        return $validated;
    }

    public static function authorizeCartCondition(CartCondition $condition): Cart
    {
        $cart = $condition->cart;

        if (! $cart instanceof Cart) {
            throw new AuthorizationException('Cart condition parent cart is not accessible.');
        }

        return self::authorizeCart($cart);
    }

    public static function authorizeStoredCondition(Condition $condition): Condition
    {
        if (! Condition::ownerScopingEnabled()) {
            return $condition;
        }

        /** @var Condition $validated */
        $validated = OwnerWriteGuard::findOrFailForOwner(
            Condition::class,
            (string) $condition->getKey(),
            includeGlobal: false,
            message: 'Condition is not accessible in the current owner scope.',
        );

        return $validated;
    }

    public static function findStoredCondition(string | int $id, bool $forItems): Condition
    {
        if (! Condition::ownerScopingEnabled()) {
            /** @var Condition $condition */
            $condition = self::baseConditionQuery($forItems)->findOrFail($id);

            return $condition;
        }

        /** @var Condition $condition */
        $condition = OwnerWriteGuard::findOrFailForOwner(
            Condition::class,
            $id,
            includeGlobal: (bool) config('cart.owner.include_global', false),
            message: 'Condition is not accessible in the current owner scope.',
        );

        self::assertConditionCanBeApplied($condition, $forItems);

        return $condition;
    }

    /**
     * @return Builder<Condition>
     */
    private static function baseConditionQuery(bool $forItems): Builder
    {
        $query = Condition::query()->active();

        if ($forItems) {
            $query->forItems();
        }

        return $query;
    }

    private static function assertConditionCanBeApplied(Condition $condition, bool $forItems): void
    {
        if (! $condition->is_active) {
            throw new AuthorizationException('Condition is not active.');
        }

        if ($forItems && ! str_starts_with((string) $condition->target, 'items@')) {
            throw new AuthorizationException('Condition is not an item-level condition.');
        }
    }
}
