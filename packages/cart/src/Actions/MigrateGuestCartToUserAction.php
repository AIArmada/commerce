<?php

declare(strict_types=1);

namespace AIArmada\Cart\Actions;

use AIArmada\Cart\Contracts\CartMergeStrategyInterface;
use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Exceptions\CartConflictException;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Support\CartLimits;
use Exception;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

final class MigrateGuestCartToUserAction
{
    /**
     * Maximum number of times the whole migration re-runs against fresh
     * snapshots after losing a revision race before surfacing the conflict.
     */
    private const MAX_MERGE_ATTEMPTS = 3;

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
        $guestIdentifier = $sessionId;
        $userIdentifier = (string) $userId;

        if ($userIdentifier === '') {
            throw new InvalidArgumentException('A non-empty user identifier is required to migrate a guest cart.');
        }

        $mergeStrategy = $strategy ?? $this->mergeStrategy ?? $this->strategyRegistry->resolveFromConfig();
        $resolvedStrategyName = $strategyName ?? $mergeStrategy::class;

        $guestStorage = $this->resolveGlobalStorage();
        $targetStorage = $this->resolveStorage();

        $guestSnapshot = $guestStorage->getSnapshot($guestIdentifier, $instance);

        // If guest cart is empty, nothing to migrate
        if ($guestSnapshot === null || $guestSnapshot->items === []) {
            return false;
        }

        // All writes run in one transaction and the whole cycle re-reads both
        // carts against fresh snapshots when a concurrent mutation wins the
        // revision race, so a stale merge payload is never committed. A retry
        // after success is a no-op (the guest cart is already consumed).
        $merged = $this->applyMerge(
            $guestStorage,
            $targetStorage,
            $guestIdentifier,
            $userIdentifier,
            $instance,
            $mergeStrategy,
        );

        if ($merged === null) {
            return false;
        }

        if (config('cart.events', true)) {
            $this->dispatchCartMergedEvent(
                instance: $instance,
                guestIdentifier: $guestIdentifier,
                userIdentifier: $userIdentifier,
                guestItems: $merged['guestItems'],
                userItems: $merged['userItems'],
                totalItemsMerged: $this->sumItemQuantities($merged['guestItems']),
                hadConflicts: $this->hasMergeConflicts($merged['guestItems'], $merged['userItems']),
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

        if ($userId === null || $userId === '') {
            return (object) [
                'success' => false,
                'itemsMerged' => 0,
                'conflicts' => collect(),
                'message' => 'Cannot migrate cart: the user has no usable ID.',
            ];
        }

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

    /**
     * Commit the guest-to-user migration with bounded retry on revision conflicts.
     *
     * When a concurrent mutation wins the revision race the whole cycle (fresh
     * snapshots, recomputed merge, guarded writes) re-runs, so the committed
     * payload always derives from the exact state it replaces.
     *
     * Two-cart operations follow one documented order: the source cart is read
     * first, then the target, and the target is written before the exact source
     * revision is consumed. The guarded consumption conflicts on any
     * concurrent source write or a second consumer, so a source update is
     * never silently destroyed by the tombstone.
     *
     * @return array{guestItems: array<string, mixed>, userItems: array<string, mixed>}|null The item sets merged, or null when the source cart no longer has items
     */
    private function applyMerge(
        StorageInterface $guestStorage,
        StorageInterface $targetStorage,
        string $guestIdentifier,
        string $userIdentifier,
        string $instance,
        CartMergeStrategyInterface $mergeStrategy,
    ): ?array {
        for ($attempt = 1; ; $attempt++) {
            try {
                $guestSnapshot = $guestStorage->getSnapshot($guestIdentifier, $instance);

                if ($guestSnapshot === null || $guestSnapshot->items === []) {
                    return null;
                }

                $userSnapshot = $targetStorage->getSnapshot($userIdentifier, $instance);

                $guestItems = $guestSnapshot->items;
                $userItems = $userSnapshot?->items ?? [];

                $mergedItems = $this->mergeItems($guestItems, $userItems, $mergeStrategy);
                $mergedConditions = $this->mergeConditions($guestSnapshot->conditions, $userSnapshot?->conditions ?? []);
                $mergedMetadata = array_merge($guestSnapshot->metadata, $userSnapshot?->metadata ?? []);

                // The two-cart atomic boundary lives in the storage backend:
                // target data and source consumption commit as one unit, using
                // the connection/backing store that owns the writes.
                $targetStorage->commitMergeTransfer(
                    $guestStorage,
                    $guestIdentifier,
                    $userIdentifier,
                    $instance,
                    $mergedItems,
                    $mergedConditions,
                    $mergedMetadata,
                    $guestSnapshot->revision(),
                    $userSnapshot?->revision() ?? CartRevision::none(),
                );

                return ['guestItems' => $guestItems, 'userItems' => $userItems];
            } catch (CartConflictException $exception) {
                if ($attempt >= self::MAX_MERGE_ATTEMPTS) {
                    throw $exception;
                }

                usleep(25000 * $attempt);
            }
        }
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
