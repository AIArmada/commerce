<?php

declare(strict_types=1);

namespace AIArmada\Cart\Actions;

use AIArmada\Cart\Contracts\CartMergeStrategyInterface;
use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Storage\StorageInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class MigrateGuestCartToUserAction
{
    private ?CartMergeStrategyInterface $mergeStrategy = null;

    private ?StorageInterface $storage = null;

    public function __construct(
        private readonly CartMergeStrategyRegistry $strategyRegistry,
        ?StorageInterface $storage = null,
    ) {
        $this->storage = $storage;
    }

    private function resolveStorage(): StorageInterface
    {
        if ($this->storage !== null) {
            return $this->storage;
        }

        if (function_exists('app')) {
            return app(StorageInterface::class);
        }

        throw new RuntimeException('Cart storage is not available');
    }

    /**
     * Resolve storage without owner scope for reading guest items.
     */
    private function resolveGlobalStorage(): StorageInterface
    {
        $storage = $this->resolveStorage();
        $ownerType = $storage->getOwnerType();

        if ($ownerType !== null) {
            return $storage->withOwner(null);
        }

        return $storage;
    }

    /**
     * Migrate guest cart to user cart when user logs in.
     *
     * @param  int|string  $userId  The user ID that will become the new cart identifier
     * @param  string  $instance  The cart instance name (e.g., 'default', 'wishlist')
     * @param  string  $sessionId  The guest session ID (cart identifier) to migrate from
     */
    public function execute(int | string $userId, string $instance, string $sessionId, ?CartMergeStrategyInterface $strategy = null, ?string $strategyName = null): bool
    {
        $mergeStrategy = $strategy ?? $this->mergeStrategy ?? $this->strategyRegistry->resolveFromConfig();
        $resolvedStrategyName = $strategyName ?? $mergeStrategy::class;

        $guestIdentifier = $sessionId;
        $userIdentifier = (string) $userId;

        $guestStorage = $this->resolveGlobalStorage();
        $guestItems = $guestStorage->getItems($guestIdentifier, $instance);

        // If guest cart is empty, nothing to migrate
        if (empty($guestItems)) {
            return false;
        }

        // Get existing user cart items for the same instance
        $userItems = $this->resolveStorage()->getItems($userIdentifier, $instance);
        $guestMetadata = $guestStorage->getAllMetadata($guestIdentifier, $instance);

        if (empty($userItems)) {
            $this->swapIdentifierWithStorage($guestIdentifier, $userIdentifier, $instance, $guestStorage, $this->resolveStorage());

            if (config('cart.events', true)) {
                $this->dispatchCartMergedEvent(
                    instance: $instance,
                    guestIdentifier: $guestIdentifier,
                    userIdentifier: $userIdentifier,
                    guestItems: $guestItems,
                    userItems: [],
                    totalItemsMerged: $this->sumItemQuantities($guestItems),
                    hadConflicts: false,
                    mergeStrategy: $mergeStrategy,
                    strategyName: $resolvedStrategyName,
                );
            }

            return true;
        }

        // Merge the cart data
        $mergedItems = $this->mergeItems($guestItems, $userItems, $mergeStrategy);
        $this->resolveStorage()->putItems($userIdentifier, $instance, $mergedItems);

        $guestConditions = $guestStorage->getConditions($guestIdentifier, $instance);
        if (! empty($guestConditions)) {
            $userConditions = $this->resolveStorage()->getConditions($userIdentifier, $instance);
            $mergedConditions = $this->mergeConditions($guestConditions, $userConditions);
            $this->resolveStorage()->putConditions($userIdentifier, $instance, $mergedConditions);
        }

        if ($guestMetadata !== []) {
            $userMetadata = $this->resolveStorage()->getAllMetadata($userIdentifier, $instance);
            $this->resolveStorage()->putMetadataBatch($userIdentifier, $instance, array_merge($guestMetadata, $userMetadata));
        }

        $guestStorage->forget($guestIdentifier, $instance);

        if (config('cart.events', true)) {
            $this->dispatchCartMergedEvent(
                instance: $instance,
                guestIdentifier: $guestIdentifier,
                userIdentifier: $userIdentifier,
                guestItems: $guestItems,
                userItems: $userItems,
                totalItemsMerged: $this->sumItemQuantities($guestItems),
                hadConflicts: $this->hasMergeConflicts($guestItems, $userItems),
                mergeStrategy: $mergeStrategy,
                strategyName: $resolvedStrategyName,
            );
        }

        return true;
    }

    public function withMergeStrategy(CartMergeStrategyInterface $strategy): static
    {
        $this->mergeStrategy = $strategy;

        return $this;
    }

    public function executeForUser(Model | int | string $user, string $instance, ?string $sessionId): object
    {
        if ($sessionId === null || $sessionId === '') {
            return (object) [
                'success' => false,
                'itemsMerged' => 0,
                'conflicts' => collect(),
                'message' => 'No guest session to migrate',
            ];
        }

        $userId = $user instanceof Model ? $user->getKey() : $user;

        try {
            $success = $this->execute($userId, $instance, $sessionId);

            return (object) [
                'success' => $success,
                'itemsMerged' => $success ? 1 : 0, // Simplified for now
                'conflicts' => collect(),
                'message' => $success ? 'Cart migration completed successfully' : 'No items to migrate',
            ];
        } catch (Exception $e) {
            return (object) [
                'success' => false,
                'itemsMerged' => 0,
                'conflicts' => collect(),
                'message' => 'Migration failed: ' . $e->getMessage(),
            ];
        }
    }

    private function swapIdentifierWithStorage(
        string $oldIdentifier,
        string $newIdentifier,
        string $instance,
        StorageInterface $sourceStorage,
        StorageInterface $targetStorage,
    ): bool {
        $items = $sourceStorage->getItems($oldIdentifier, $instance);

        if (empty($items)) {
            return false;
        }

        $conditions = $sourceStorage->getConditions($oldIdentifier, $instance);
        $metadata = $sourceStorage->getAllMetadata($oldIdentifier, $instance);

        $targetStorage->putItems($newIdentifier, $instance, $items);

        if (! empty($conditions)) {
            $targetStorage->putConditions($newIdentifier, $instance, $conditions);
        }

        if ($metadata !== []) {
            $targetStorage->putMetadataBatch($newIdentifier, $instance, $metadata);
        }

        $sourceStorage->forget($oldIdentifier, $instance);

        return true;
    }

    /**
     * @param  array<string, mixed>  $guestItems
     * @param  array<string, mixed>  $userItems
     * @return array<string, mixed>
     */
    private function mergeItems(array $guestItems, array $userItems, CartMergeStrategyInterface $mergeStrategy): array
    {
        $mergedItems = $userItems;

        foreach ($guestItems as $itemId => $guestItemData) {
            $existingItem = $userItems[$itemId] ?? null;

            if ($existingItem) {
                $newQuantity = $mergeStrategy->resolveConflict(
                    $existingItem['quantity'] ?? 0,
                    $guestItemData['quantity'] ?? 0,
                );

                $mergedItems[$itemId]['quantity'] = $newQuantity;
            } else {
                $mergedItems[$itemId] = $guestItemData;
            }
        }

        return $mergedItems;
    }

    /**
     * Merge cart conditions from guest to user cart.
     *
     * @param  array<string, mixed>  $guestConditions
     * @param  array<string, mixed>  $userConditions
     * @return array<string, mixed>
     */
    private function mergeConditions(array $guestConditions, array $userConditions): array
    {
        $mergedConditions = $userConditions;

        foreach ($guestConditions as $conditionName => $conditionData) {
            if (! isset($mergedConditions[$conditionName])) {
                $mergedConditions[$conditionName] = $conditionData;
            }
            // If condition exists in both, keep the user's version
        }

        return $mergedConditions;
    }

    /**
     * Sum quantities of items in array.
     *
     * @param  array<string, mixed>  $items
     */
    private function sumItemQuantities(array $items): int
    {
        return array_reduce(
            $items,
            static fn (int $sum, array $item) => $sum + ($item['quantity'] ?? 0),
            0
        );
    }

    /**
     * Check if there are merge conflicts.
     *
     * @param  array<string, mixed>  $guestItems
     * @param  array<string, mixed>  $userItems
     */
    private function hasMergeConflicts(array $guestItems, array $userItems): bool
    {
        foreach ($guestItems as $itemId => $guestItemData) {
            if (isset($userItems[$itemId])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $guestItems
     * @param  array<string, mixed>  $userItems
     */
    private function dispatchCartMergedEvent(
        string $instance,
        string $guestIdentifier,
        string $userIdentifier,
        array $guestItems,
        array $userItems,
        int $totalItemsMerged,
        bool $hadConflicts,
        CartMergeStrategyInterface $mergeStrategy,
        string $strategyName,
    ): void {
        $cartManager = Cart::getFacadeRoot();
        $targetCartInstance = $cartManager->getCartInstance($instance, $userIdentifier);

        event(new CartMerged(
            targetCart: $targetCartInstance,
            sourceCart: $cartManager->getCartInstance($instance, $guestIdentifier),
            totalItemsMerged: $totalItemsMerged,
            mergeStrategy: $strategyName,
            hadConflicts: $hadConflicts,
            originalSourceIdentifier: $guestIdentifier,
            originalTargetIdentifier: $userIdentifier,
        ));
    }
}
