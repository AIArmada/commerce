<?php

declare(strict_types=1);

namespace AIArmada\Cart\Actions;

use AIArmada\Cart\Contracts\CartMergeStrategyInterface;
use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Support\CartLimits;
use AIArmada\Cart\Support\CartOwnerScope;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
            DB::transaction(fn (): bool => $this->swapIdentifierWithStorage($guestIdentifier, $userIdentifier, $instance, $guestStorage, $this->resolveStorage()));

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

        // Merge the cart data. All writes run in one transaction so a mid-merge
        // failure rolls everything back and a retry re-runs cleanly instead of
        // duplicating items; a retry after success is a no-op (guest is gone).
        $mergedItems = $this->mergeItems($guestItems, $userItems, $mergeStrategy);

        $guestConditions = $guestStorage->getConditions($guestIdentifier, $instance);
        $userConditions = $guestConditions === []
            ? []
            : $this->resolveStorage()->getConditions($userIdentifier, $instance);

        $userMetadata = $guestMetadata === []
            ? []
            : $this->resolveStorage()->getAllMetadata($userIdentifier, $instance);

        $targetStorage = $this->resolveStorage();

        DB::transaction(function () use (
            $targetStorage,
            $guestStorage,
            $userIdentifier,
            $guestIdentifier,
            $instance,
            $mergedItems,
            $guestConditions,
            $userConditions,
            $guestMetadata,
            $userMetadata,
        ): void {
            $targetStorage->putItems($userIdentifier, $instance, $mergedItems);

            if ($guestConditions !== []) {
                $targetStorage->putConditions(
                    $userIdentifier,
                    $instance,
                    $this->mergeConditions($guestConditions, $userConditions)
                );
            }

            if ($guestMetadata !== []) {
                $targetStorage->putMetadataBatch($userIdentifier, $instance, array_merge($guestMetadata, $userMetadata));
            }

            $this->markSourceCartAsMerged($guestIdentifier, $instance, $userIdentifier, $guestStorage, $targetStorage);

            $this->tombstoneSourceCart($guestIdentifier, $instance, $guestStorage);
        });

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
            $guestItems = $this->resolveGlobalStorage()->getItems($sessionId, $instance);
            $success = $this->execute($userId, $instance, $sessionId);

            return (object) [
                'success' => $success,
                'itemsMerged' => $success ? $this->sumItemQuantities($guestItems) : 0,
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

        // Mark only after the target exists so merged_into_id attribution sticks.
        $this->markSourceCartAsMerged($oldIdentifier, $instance, $newIdentifier, $sourceStorage, $targetStorage);

        $this->tombstoneSourceCart($oldIdentifier, $instance, $sourceStorage);

        return true;
    }

    private function markSourceCartAsMerged(
        string $sourceIdentifier,
        string $instance,
        string $targetIdentifier,
        StorageInterface $sourceStorage,
        StorageInterface $targetStorage,
    ): void {
        $table = config('cart.database.table', 'carts');

        $targetQuery = DB::table($table)
            ->where('identifier', $targetIdentifier)
            ->where('instance', $instance);

        CartOwnerScope::apply($targetQuery, $targetStorage);

        $targetCart = $targetQuery->first(['id']);

        if ($targetCart === null) {
            return;
        }

        $sourceQuery = DB::table($table)
            ->where('identifier', $sourceIdentifier)
            ->where('instance', $instance);

        CartOwnerScope::apply($sourceQuery, $sourceStorage);

        $sourceQuery->update([
            'merged_into_id' => $targetCart->id,
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Convert the migrated-from cart into a tombstone: content cleared and
     * expired, but the row (and its merged_into_id attribution) kept.
     */
    private function tombstoneSourceCart(
        string $sourceIdentifier,
        string $instance,
        StorageInterface $sourceStorage,
    ): void {
        $sourceStorage->clearAll($sourceIdentifier, $instance);

        $table = config('cart.database.table', 'carts');

        $sourceQuery = DB::table($table)
            ->where('identifier', $sourceIdentifier)
            ->where('instance', $instance);

        CartOwnerScope::apply($sourceQuery, $sourceStorage);

        $sourceQuery->update([
            'expired_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $guestItems
     * @param  array<string, mixed>  $userItems
     * @return array<string, mixed>
     */
    private function mergeItems(array $guestItems, array $userItems, CartMergeStrategyInterface $mergeStrategy): array
    {
        $limits = CartLimits::fromConfig();
        $mergedItems = $userItems;

        foreach ($guestItems as $itemId => $guestItemData) {
            if (! is_array($guestItemData)) {
                throw new InvalidCartItemException("Cannot merge corrupt cart item [{$itemId}]: row is not an array.");
            }

            $existingItem = $userItems[$itemId] ?? null;

            if (is_array($existingItem)) {
                $newQuantity = $mergeStrategy->resolveConflict(
                    $this->coerceMergeQuantity($existingItem['quantity'] ?? 0),
                    $this->coerceMergeQuantity($guestItemData['quantity'] ?? 0),
                );

                $mergedItems[$itemId]['quantity'] = max(1, min($newQuantity, $limits->maxItemQuantity));
            } else {
                $guestItemData['quantity'] = max(1, min(
                    $this->coerceMergeQuantity($guestItemData['quantity'] ?? 0),
                    $limits->maxItemQuantity
                ));

                $mergedItems[$itemId] = $guestItemData;
            }
        }

        if (count($mergedItems) > $limits->maxItems) {
            throw new InvalidCartItemException(
                "Merged cart would contain more than {$limits->maxItems} items."
            );
        }

        return $mergedItems;
    }

    private function coerceMergeQuantity(mixed $quantity): int
    {
        return is_numeric($quantity) ? (int) $quantity : 0;
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
        $sum = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $quantity = $item['quantity'] ?? 0;
            $sum += is_numeric($quantity) ? (int) $quantity : 0;
        }

        return $sum;
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
