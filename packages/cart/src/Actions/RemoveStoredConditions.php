<?php

declare(strict_types=1);

namespace AIArmada\Cart\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\Condition as StoredCondition;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Cart\Snapshots\CartSnapshotCondition;
use AIArmada\Cart\Snapshots\CartSyncManager;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class RemoveStoredConditions
{
    public function __construct(
        private readonly CartInstanceManager $cartInstances,
        private readonly CartSyncManager $syncManager,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     carts_processed: int,
     *     carts_updated: int,
     *     errors: array<string>
     * }
     */
    public function handle(StoredCondition | string $condition): array
    {
        if (
            $condition instanceof StoredCondition
            && StoredCondition::ownerScopingEnabled()
            && ! ($condition->owner_type === null && $condition->owner_id === null)
        ) {
            /** @var StoredCondition $condition */
            $condition = OwnerWriteGuard::findOrFailForOwner(
                StoredCondition::class,
                (string) $condition->getKey(),
                includeGlobal: false,
                message: 'Condition is not accessible in the current owner scope.',
            );
        }

        $cartsProcessed = 0;
        $cartsUpdated = 0;
        $errors = [];
        $conditionLabel = $condition instanceof StoredCondition
            ? ($condition->display_name ?? $condition->name)
            : $condition;

        try {
            $matchedConditions = $this->findMatchingConditions($condition);
            $affectedCartIds = $matchedConditions->pluck('cart_id')->unique()->values();
            $affectedSnapshots = $this->snapshotQuery($condition)
                ->whereIn('id', $affectedCartIds)
                ->get();
            $conditionsByCart = $matchedConditions->groupBy('cart_id');

            foreach ($affectedSnapshots as $snapshot) {
                $cartsProcessed++;

                try {
                    $cart = $this->cartInstances->resolveForSnapshot($snapshot);
                    $conditionRemoved = false;

                    foreach ($conditionsByCart->get($snapshot->id, collect()) as $snapshotCondition) {
                        if (! $snapshotCondition instanceof CartSnapshotCondition) {
                            continue;
                        }

                        $conditionRemoved = $this->removeConditionFromCart($cart, $snapshotCondition) || $conditionRemoved;
                    }

                    if ($conditionRemoved) {
                        $this->syncManager->sync($cart, force: true);
                        $cartsUpdated++;
                    }
                } catch (Exception $exception) {
                    $errors[] = "Error processing snapshot ID {$snapshot->id}: {$exception->getMessage()}";
                    Log::error('Error removing condition from cart', [
                        'snapshot_id' => $snapshot->id,
                        'condition' => $conditionLabel,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'carts_processed' => $cartsProcessed,
                'carts_updated' => $cartsUpdated,
                'errors' => $errors,
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'carts_processed' => $cartsProcessed,
                'carts_updated' => $cartsUpdated,
                'errors' => [...$errors, "Fatal error: {$exception->getMessage()}"],
            ];
        }
    }

    /**
     * Remove one persisted snapshot condition and refresh its cart projection.
     *
     * @throws Exception
     */
    public function removeSnapshotCondition(CartSnapshotCondition $snapshotCondition): bool
    {
        $snapshot = $snapshotCondition->cart;

        if (! $snapshot instanceof CartSnapshot) {
            throw new RuntimeException('Cart condition parent snapshot is not accessible.');
        }

        $snapshot = $this->authorizeSnapshot($snapshot);
        $cart = $this->cartInstances->resolveForSnapshot($snapshot);
        $removed = $this->removeConditionFromCart($cart, $snapshotCondition);

        if ($removed) {
            $this->syncManager->sync($cart, force: true);
        }

        return $removed;
    }

    /**
     * Remove all persisted conditions from a cart and refresh its projection.
     *
     * @throws Exception
     */
    public function clearAll(CartSnapshot $snapshot): void
    {
        $snapshot = $this->authorizeSnapshot($snapshot);
        $cart = $this->cartInstances->resolveForSnapshot($snapshot);

        $cart->clearConditions();

        foreach ($cart->getItems() as $item) {
            $cart->clearItemConditions($item->getId());
        }

        $this->syncManager->sync($cart, force: true);
    }

    /**
     * Remove persisted conditions of one type from a cart and refresh its projection.
     *
     * @throws Exception
     */
    public function clearByType(CartSnapshot $snapshot, string $type): void
    {
        $snapshot = $this->authorizeSnapshot($snapshot);
        $cart = $this->cartInstances->resolveForSnapshot($snapshot);
        $cart->removeConditionsByType($type);
        $this->syncManager->sync($cart, force: true);
    }

    /**
     * @return Collection<int, CartSnapshotCondition>
     */
    private function findMatchingConditions(StoredCondition | string $condition): Collection
    {
        $query = CartSnapshotCondition::query()
            ->whereIn('cart_id', $this->snapshotQuery($condition)->select('id'));

        if (is_string($condition)) {
            return $query->where('name', $condition)->get();
        }

        $candidateNames = array_values(array_unique(array_filter([
            $condition->display_name,
            $condition->name,
        ], static fn (?string $name): bool => is_string($name) && $name !== '')));

        return $query
            ->where(function (Builder $builder) use ($condition, $candidateNames): void {
                $builder->where('attributes->condition_id', $condition->getKey());

                if ($candidateNames === []) {
                    return;
                }

                $builder->orWhere(function (Builder $fallback) use ($candidateNames): void {
                    $fallback
                        ->whereNull('attributes->condition_id')
                        ->whereIn('name', $candidateNames);
                });
            })
            ->get();
    }

    /**
     * @return Builder<CartSnapshot>
     */
    private function snapshotQuery(StoredCondition | string $condition): Builder
    {
        if (
            $condition instanceof StoredCondition
            && $condition->owner_type === null
            && $condition->owner_id === null
            && StoredCondition::ownerScopingEnabled()
        ) {
            if (! OwnerContext::isExplicitGlobal()) {
                throw new RuntimeException('Removing shared global conditions from all carts requires explicit global owner context.');
            }

            return CartSnapshot::query()->withoutGlobalScope(OwnerScope::class);
        }

        return CartSnapshot::query()->forOwner();
    }

    private function removeConditionFromCart(Cart $cart, CartSnapshotCondition $snapshotCondition): bool
    {
        $conditionName = $snapshotCondition->name;

        if ($snapshotCondition->isCartLevel()) {
            $removed = $cart->removeCondition($conditionName);

            if ($cart->getDynamicConditions()->has($conditionName)) {
                $cart->removeDynamicCondition($conditionName);
                $removed = true;
            }

            return $removed;
        }

        $removed = false;
        foreach ($cart->getItems() as $item) {
            if ($snapshotCondition->item_id !== null && $item->getId() !== $snapshotCondition->item_id) {
                continue;
            }

            if (! $item->getConditions()->has($conditionName)) {
                continue;
            }

            $cart->removeItemCondition($item->getId(), $conditionName);
            $removed = true;
        }

        return $removed;
    }

    private function authorizeSnapshot(CartSnapshot $snapshot): CartSnapshot
    {
        if (! CartSnapshot::ownerScopingEnabled()) {
            return $snapshot;
        }

        /** @var CartSnapshot $validated */
        $validated = OwnerWriteGuard::findOrFailForOwner(
            CartSnapshot::class,
            (string) $snapshot->getKey(),
            includeGlobal: false,
            message: 'Cart snapshot is not accessible in the current owner scope.',
        );

        return $validated;
    }
}
