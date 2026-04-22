<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\Condition as StoredCondition;
use AIArmada\FilamentCart\Models\Cart as CartModel;
use AIArmada\FilamentCart\Models\CartCondition as CartConditionModel;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Removes conditions from all active carts.
 *
 * This service provides batch operations to remove specific conditions
 * from all carts in the system. Useful when admin deactivates a global
 * condition and needs to remove it immediately from all active carts.
 */
final class CartConditionBatchRemoval
{
    public function __construct(
        private CartInstanceManager $cartInstances,
        private CartSyncManager $syncManager
    ) {}

    /**
     * Remove a specific condition from all active carts.
     *
     * This method:
     * 1. Finds all cart snapshots that have this condition
     * 2. Loads each cart
     * 3. Removes the condition (handles both static and dynamic)
     * 4. Saves the cart (which triggers sync to update snapshot)
     *
     * @param  StoredCondition|string  $condition  The stored condition or condition name to remove
     * @return array{
     *     success: bool,
     *     carts_processed: int,
     *     carts_updated: int,
     *     errors: array<string>
     * }
     */
    public function removeConditionFromAllCarts(StoredCondition | string $condition): array
    {
        $cartsProcessed = 0;
        $cartsUpdated = 0;
        $errors = [];
        $conditionLabel = $condition instanceof StoredCondition
            ? ($condition->display_name ?? $condition->name)
            : $condition;

        try {
            $matchedConditions = $this->findMatchingConditions($condition);

            $affectedCartIds = $matchedConditions
                ->pluck('cart_id')
                ->unique()
                ->values();

            $affectedSnapshots = CartModel::query()
                ->forOwner()
                ->whereIn('id', $affectedCartIds)
                ->get();

            Log::info("Found {$affectedSnapshots->count()} cart snapshots with condition '{$conditionLabel}'");

            $conditionsByCart = $matchedConditions->groupBy('cart_id');

            foreach ($affectedSnapshots as $snapshot) {
                $cartsProcessed++;

                try {
                    // Load the cart instance
                    $cart = $this->loadCartForSnapshot($snapshot);

                    if ($cart === null) {
                        $errors[] = "Could not load cart for snapshot ID: {$snapshot->id}";

                        continue;
                    }

                    $conditionRemoved = false;

                    foreach ($conditionsByCart->get($snapshot->id, collect()) as $snapshotCondition) {
                        if (! $snapshotCondition instanceof CartConditionModel) {
                            continue;
                        }

                        $conditionRemoved = $this->removeConditionFromCart($cart, $snapshotCondition) || $conditionRemoved;
                    }

                    if ($conditionRemoved) {
                        // Sync the cart to update the database snapshot
                        $this->syncManager->sync($cart);
                        $cartsUpdated++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Error processing snapshot ID {$snapshot->id}: {$e->getMessage()}";
                    Log::error('Error removing condition from cart', [
                        'snapshot_id' => $snapshot->id,
                        'condition' => $conditionLabel,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Batch condition removal completed', [
                'condition' => $conditionLabel,
                'processed' => $cartsProcessed,
                'updated' => $cartsUpdated,
                'errors' => count($errors),
            ]);

            return [
                'success' => true,
                'carts_processed' => $cartsProcessed,
                'carts_updated' => $cartsUpdated,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            Log::error('Batch condition removal failed', [
                'condition' => $conditionLabel,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'carts_processed' => $cartsProcessed,
                'carts_updated' => $cartsUpdated,
                'errors' => [...$errors, "Fatal error: {$e->getMessage()}"],
            ];
        }
    }

    /**
     * Load a cart instance from a snapshot.
     */
    private function loadCartForSnapshot(CartModel $snapshot): ?Cart
    {
        try {
            // Set the cart instance based on snapshot
            $instance = $snapshot->instance ?? 'default';

            // Get identifier (user ID or session ID)
            $identifier = $snapshot->identifier;

            // Get the cart for this specific instance and identifier
            return $this->cartInstances->resolve($instance, $identifier);
        } catch (Exception $e) {
            Log::error('Failed to load cart from snapshot', [
                'snapshot_id' => $snapshot->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return Collection<int, CartConditionModel>
     */
    private function findMatchingConditions(StoredCondition | string $condition): Collection
    {
        $query = CartConditionModel::query()
            ->whereIn('cart_id', CartModel::query()->forOwner()->select('id'));

        if (is_string($condition)) {
            return $query
                ->where('name', $condition)
                ->get();
        }

        $candidateNames = array_values(array_unique(array_filter([
            $condition->display_name,
            $condition->name,
        ], static fn (?string $name): bool => is_string($name) && $name !== '')));

        return $query
            ->where(function ($builder) use ($condition, $candidateNames): void {
                $builder->where('attributes->condition_id', $condition->getKey());

                if ($candidateNames === []) {
                    return;
                }

                $builder->orWhere(function ($fallback) use ($candidateNames): void {
                    $fallback
                        ->whereNull('attributes->condition_id')
                        ->whereIn('name', $candidateNames);
                });
            })
            ->get();
    }

    private function removeConditionFromCart(Cart $cart, CartConditionModel $snapshotCondition): bool
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
}
