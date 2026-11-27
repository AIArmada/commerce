<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Conditions\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Features\Conditions\Models\Condition as StoredCondition;
use AIArmada\Cart\Features\Snapshots\Api\SnapshotConditionData;
use AIArmada\Cart\Features\Snapshots\Api\SnapshotConditionLookup;
use AIArmada\Cart\Runtime\CartInstanceManager;
use AIArmada\Cart\Runtime\CartSnapshotSyncInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Exception;
use Illuminate\Support\Facades\Log;

final readonly class RemoveStoredConditions
{
    public function __construct(
        private CartInstanceManager $cartInstances,
        private CartSnapshotSyncInterface $syncManager,
        private SnapshotConditionLookup $snapshots,
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
                        $conditionRemoved = $this->removeConditionsFromCart($cart, $match->conditions);

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

        foreach ($cart->getDynamicConditions()->keys()->all() as $name) {
            $cart->removeDynamicCondition((string) $name);
        }

        $cart->clearConditions();

        foreach ($cart->getItems() as $item) {
            $cart->clearItemConditions($item->id);
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
        foreach ($cart->getDynamicConditions()->all() as $condition) {
            if ($condition->getType() === $type) {
                $cart->removeDynamicCondition($condition->getName());
            }
        }

        $cart->removeConditionsByType($type);

        foreach ($cart->getItems() as $item) {
            foreach ($item->getConditions() as $condition) {
                if ($condition->getType() === $type) {
                    $cart->removeItemCondition($item->id, $condition->getName());
                }
            }
        }

        $this->syncManager->sync($cart, force: true);
    }

    /**
     * @param  list<SnapshotConditionData>  $snapshotConditions
     */
    private function removeConditionsFromCart(Cart $cart, array $snapshotConditions): bool
    {
        $removed = false;
        $itemConditions = [];
        $dynamicNames = [];

        foreach ($snapshotConditions as $snapshotCondition) {
            if ($snapshotCondition->cartLevel) {
                $removed = $this->removeConditionFromCart($cart, $snapshotCondition) || $removed;

                continue;
            }

            $dynamicName = $this->matchingDynamicDefinitionName($cart, $snapshotCondition);

            if ($dynamicName !== null) {
                $dynamicNames[$dynamicName] = true;
            }

            if ($snapshotCondition->itemId !== null) {
                $itemConditions[$snapshotCondition->itemId][$snapshotCondition->name] = $snapshotCondition->conditionId;
            }
        }

        foreach (array_keys($dynamicNames) as $dynamicName) {
            $cart->removeDynamicCondition((string) $dynamicName);
            $removed = true;
        }

        if ($itemConditions !== []) {
            $removed = $cart->removeItemConditions($itemConditions) || $removed;
        }

        return $removed;
    }

    private function removeConditionFromCart(Cart $cart, SnapshotConditionData $snapshotCondition): bool
    {
        $conditionName = $snapshotCondition->name;
        $conditionId = $snapshotCondition->conditionId;

        if ($snapshotCondition->cartLevel) {
            $stored = $cart->getStoredConditions()->getCondition($conditionName);
            $dynamic = $cart->getDynamicConditions()->getCondition($conditionName);
            $removed = false;

            if ($stored !== null && $this->matchesConditionId($stored->getAttribute('condition_id'), $conditionId)) {
                $removed = $cart->removeCondition($conditionName);
            }

            if ($dynamic !== null && $this->matchesConditionId($dynamic->getAttribute('condition_id'), $conditionId)) {
                $cart->removeDynamicCondition($conditionName);
                $removed = true;
            }

            return $removed;
        }

        $removed = false;
        $dynamicName = $this->matchingDynamicDefinitionName($cart, $snapshotCondition);

        if ($dynamicName !== null) {
            $cart->removeDynamicCondition($dynamicName);
            $removed = true;
        }

        foreach ($cart->getItems() as $item) {
            if ($snapshotCondition->itemId !== null && $item->id !== $snapshotCondition->itemId) {
                continue;
            }

            $condition = $item->getConditions()->getCondition($conditionName);

            if ($condition === null || ! $this->matchesConditionId($condition->getAttribute('condition_id'), $conditionId)) {
                continue;
            }

            $removed = $cart->removeItemCondition($item->id, $conditionName) || $removed;
        }

        return $removed;
    }

    private function matchingDynamicDefinitionName(Cart $cart, SnapshotConditionData $snapshotCondition): ?string
    {
        if ($snapshotCondition->conditionId === null) {
            return null;
        }

        foreach ($cart->getDynamicConditions() as $definition) {
            if ($definition->getName() !== $snapshotCondition->name) {
                continue;
            }

            if ($this->matchesConditionId($definition->getAttribute('condition_id'), $snapshotCondition->conditionId)) {
                return $definition->getName();
            }
        }

        return null;
    }

    private function matchesConditionId(mixed $actual, ?string $expected): bool
    {
        if ($expected === null) {
            return $actual === null;
        }

        return $actual !== null && (string) $actual === $expected;
    }
}
