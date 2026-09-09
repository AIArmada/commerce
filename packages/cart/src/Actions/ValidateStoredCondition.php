<?php

declare(strict_types=1);

namespace AIArmada\Cart\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\Condition;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;

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
        $oldTotal = $cart->total();
        $removedConditions = [];

        $globalConditionNames = $this->getGlobalConditionNames($cart);
        if ($globalConditionNames !== []) {
            $activeGlobalNames = $this->globalConditionsQuery()
                ->pluck('name')
                ->all();

            foreach (array_diff($globalConditionNames, $activeGlobalNames) as $conditionName) {
                $this->removeConditionFromCart($cart, $conditionName);
                $removedConditions[] = $conditionName;
            }
        }

        $newTotal = $cart->total();

        return [
            'is_valid' => $removedConditions === [],
            'removed_conditions' => $removedConditions,
            'price_changed' => ! $oldTotal->equals($newTotal),
            'old_total' => (int) $oldTotal->getAmount(),
            'new_total' => (int) $newTotal->getAmount(),
        ];
    }

    /**
     * @return array<string>
     */
    private function getGlobalConditionNames(Cart $cart): array
    {
        $names = [];

        foreach ($cart->getConditions() as $condition) {
            if ($condition->getAttribute('is_global') === true) {
                $names[] = $condition->getName();
            }
        }

        foreach ($cart->getDynamicConditions() as $condition) {
            if ($condition->getAttribute('is_global') === true) {
                $names[] = $condition->getName();
            }
        }

        return $names;
    }

    private function removeConditionFromCart(Cart $cart, string $conditionName): void
    {
        if ($cart->getConditions()->has($conditionName)) {
            $cart->removeCondition($conditionName);
        }

        if ($cart->getDynamicConditions()->has($conditionName)) {
            $cart->removeDynamicCondition($conditionName);
        }
    }

    /**
     * @return Builder<Condition>
     */
    private function globalConditionsQuery(): Builder
    {
        $query = Condition::query();

        if (! Condition::ownerScopingEnabled()) {
            return $query->global();
        }

        $owner = OwnerContext::resolve();
        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            Condition::class . ' requires an owner context or explicit global context.',
        );

        if ($owner === null) {
            return $query->globalOnly()->global();
        }

        return $query
            ->forOwner($owner, (bool) config('cart.owner.include_global', false))
            ->global();
    }
}
