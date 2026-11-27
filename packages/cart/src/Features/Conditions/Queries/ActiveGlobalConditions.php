<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Conditions\Queries;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Features\Conditions\Models\Condition;
use AIArmada\Cart\Runtime\CartOwnerIdentity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

final class ActiveGlobalConditions
{
    /** @return Collection<int, Condition> */
    public static function forCart(Cart $cart): Collection
    {
        $query = Condition::query()->global();
        $ownerIdColumn = OwnerTupleColumns::forModelClass(Condition::class)->ownerIdColumn;
        $ownerId = $query->getQuery()->getGrammar()->wrap($ownerIdColumn);

        if (Condition::ownerScopingEnabled()) {
            $storage = $cart->storage();
            $identity = new CartOwnerIdentity($storage->getOwnerType(), $storage->getOwnerId());
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                Condition::class . ' requires an owner context or explicit global context.',
            );

            if ($owner?->getMorphClass() !== $identity->ownerType
                || (string) $owner?->getKey() !== (string) $identity->ownerId) {
                throw new AuthorizationException('Global conditions require the owner context of the source cart.');
            }

            $query->forOwner($owner, (bool) config('cart.owner.include_global', false));
        }

        return $query->orderByRaw("CASE WHEN {$ownerId} IS NULL THEN 1 ELSE 0 END")->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Condition>  $active
     * @return list<string>
     */
    public static function removeInactive(Cart $cart, Collection $active): array
    {
        $activeIds = array_fill_keys($active->modelKeys(), true);
        $activeNames = array_fill_keys($active->pluck('name')->all(), true);
        $isInactive = static function (CartCondition $condition) use ($activeIds, $activeNames): bool {
            if ($condition->getAttribute('is_global') !== true) {
                return false;
            }

            $id = $condition->getAttribute('condition_id');
            $canonicalName = $condition->getAttribute('condition_name');

            return is_string($id) && $id !== ''
                ? ! isset($activeIds[$id])
                : ! isset($activeNames[is_string($canonicalName) ? $canonicalName : $condition->getName()]);
        };
        $removed = [];

        foreach ($cart->getDynamicConditions() as $condition) {
            if ($isInactive($condition)) {
                $cart->removeDynamicCondition($condition->getName());
                $removed[] = $condition->getName();
            }
        }

        foreach ($cart->getStoredConditions() as $condition) {
            if ($isInactive($condition) && $cart->removeCondition($condition->getName())) {
                $removed[] = $condition->getName();
            }
        }

        return array_values(array_unique($removed));
    }
}
