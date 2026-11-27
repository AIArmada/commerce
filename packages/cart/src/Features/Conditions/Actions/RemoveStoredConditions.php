<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Conditions\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartSnapshotSyncInterface;
use AIArmada\Cart\Features\Conditions\Models\Condition as StoredCondition;
use AIArmada\Cart\Features\Snapshots\Api\SnapshotConditionData;
use AIArmada\Cart\Features\Snapshots\Api\SnapshotConditionLookup;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Exception;
use Illuminate\Support\Facades\Log;

final class RemoveStoredConditions
{
    public function __construct(
        private readonly CartInstanceManager $cartInstances,
        private readonly CartSnapshotSyncInterface $syncManager,
        private readonly SnapshotConditionLookup $snapshots,
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
            $matches = is_string($condition)
                ? $this->snapshots->matchingNamedCondition($condition)
                : $this->snapshots->matchingStoredCondition(
                    (string) $condition->getKey(),
                    allOwners: StoredCondition::ownerScopingEnabled()
                        && $condition->owner_type === null
                        && $condition->owner_id === null,
                );

            foreach ($matches as $match) {
                $snapshot = $match->snapshot;
                $cartsProcessed++;

                try {
                    $remove = function () use ($match, $snapshot): bool {
                        $cart = $this->cartInstances->resolveForSnapshot($snapshot);
                        $conditionRemoved = false;

                        foreach ($match->conditions as $snapshotCondition) {
                            $conditionRemoved = $this->removeConditionFromCart($cart, $snapshotCondition) || $conditionRemoved;
                        }

                        if ($conditionRemoved) {
                            $this->syncManager->sync($cart, force: true);
                        }

                        return $conditionRemoved;
                    };

                    $removed = StoredCondition::ownerScopingEnabled()
                        ? OwnerContext::withOwner(
                            OwnerContext::fromTypeAndId($snapshot->ownerType, $snapshot->ownerId),
                            $remove,
                        )
                        : $remove();

                    if ($removed) {
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
    public function removeSnapshotCondition(string $snapshotConditionId): bool
    {
        $match = $this->snapshots->requireOwnedCondition($snapshotConditionId);
        $cart = $this->cartInstances->resolveForSnapshot($match->snapshot);
        $removed = $this->removeConditionFromCart($cart, $match->conditions[0]);

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
    public function clearAll(string $snapshotId): void
    {
        $snapshot = $this->snapshots->requireOwnedSnapshot($snapshotId);
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
    public function clearByType(string $snapshotId, string $type): void
    {
        $snapshot = $this->snapshots->requireOwnedSnapshot($snapshotId);
        $cart = $this->cartInstances->resolveForSnapshot($snapshot);
        $cart->removeConditionsByType($type);
        $this->syncManager->sync($cart, force: true);
    }

    private function removeConditionFromCart(Cart $cart, SnapshotConditionData $snapshotCondition): bool
    {
        $conditionName = $snapshotCondition->name;
        $conditionId = $snapshotCondition->conditionId;

        if ($snapshotCondition->cartLevel) {
            $stored = $cart->getStoredConditions()->getCondition($conditionName);
            $dynamic = $cart->getDynamicConditions()->getCondition($conditionName);
            $removed = false;

            if ($stored !== null && $stored->getAttribute('condition_id') === $conditionId) {
                $removed = $cart->removeCondition($conditionName);
            }

            if ($dynamic !== null && $dynamic->getAttribute('condition_id') === $conditionId) {
                $cart->removeDynamicCondition($conditionName);
                $removed = true;
            }

            return $removed;
        }

        $removed = false;
        foreach ($cart->getItems() as $item) {
            if ($snapshotCondition->itemId !== null && $item->getId() !== $snapshotCondition->itemId) {
                continue;
            }

            $condition = $item->getConditions()->getCondition($conditionName);

            if ($condition === null || $condition->getAttribute('condition_id') !== $conditionId) {
                continue;
            }

            $cart->removeItemCondition($item->getId(), $conditionName);
            $removed = true;
        }

        return $removed;
    }
}
