<?php

declare(strict_types=1);

namespace AIArmada\Cart\Actions;

use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

final class MigrateGuestCartToUserAction
{
    public function __construct(
        private readonly StorageInterface $storage,
    ) {}

    /**
     * Migrate guest cart to user cart when user logs in.
     *
     * @param  int|string  $userId  The user ID that will become the new cart identifier
     * @param  string  $instance  The cart instance name (e.g., 'default', 'wishlist')
     * @param  string  $sessionId  The guest session ID (cart identifier) to migrate from
     */
    public function execute(int | string $userId, string $instance, string $sessionId): bool
    {
        $guestIdentifier = $sessionId;
        $userIdentifier = (string) $userId;

        // Get guest cart items for the specified instance
        $guestItems = $this->storage->getItems($guestIdentifier, $instance);

        // If guest cart is empty, nothing to migrate
        if (empty($guestItems)) {
            return false;
        }

        // Get existing user cart items for the same instance
        $userItems = $this->storage->getItems($userIdentifier, $instance);
        $guestMetadata = $this->storage->getAllMetadata($guestIdentifier, $instance);

        // If user cart is empty, swap guest cart to user
        if (empty($userItems)) {
            $this->swapIdentifier($guestIdentifier, $userIdentifier, $instance);

            if (config('cart.events', true)) {
                $this->dispatchCartMergedEvent(
                    instance: $instance,
                    guestIdentifier: $guestIdentifier,
                    userIdentifier: $userIdentifier,
                    guestItems: $guestItems,
                    userItems: [],
                    totalItemsMerged: $this->sumItemQuantities($guestItems),
                    hadConflicts: false,
                );
            }

            return true;
        }

        // Merge the cart data
        $mergedItems = $this->mergeItems($guestItems, $userItems);
        $this->storage->putItems($userIdentifier, $instance, $mergedItems);

        // Also migrate conditions if any
        $guestConditions = $this->storage->getConditions($guestIdentifier, $instance);
        if (! empty($guestConditions)) {
            $userConditions = $this->storage->getConditions($userIdentifier, $instance);
            $mergedConditions = $this->mergeConditions($guestConditions, $userConditions);
            $this->storage->putConditions($userIdentifier, $instance, $mergedConditions);
        }

        // Migrate metadata if any
        if ($guestMetadata !== []) {
            $userMetadata = $this->storage->getAllMetadata($userIdentifier, $instance);
            $this->storage->putMetadataBatch($userIdentifier, $instance, array_merge($guestMetadata, $userMetadata));
        }

        // Forget guest cart
        $this->storage->forget($guestIdentifier, $instance);

        if (config('cart.events', true)) {
            $this->dispatchCartMergedEvent(
                instance: $instance,
                guestIdentifier: $guestIdentifier,
                userIdentifier: $userIdentifier,
                guestItems: $guestItems,
                userItems: $userItems,
                totalItemsMerged: $this->sumItemQuantities($guestItems),
                hadConflicts: $this->hasMergeConflicts($guestItems, $userItems),
            );
        }

        return true;
    }

    /**
     * Migrate guest cart to user cart when user logs in (user object version).
     *
     * @param  Model|int|string  $user  The user model or ID
     * @param  string  $instance  The cart instance name
     * @param  string|null  $sessionId  The guest session ID to migrate from
     * @return object Standardized result object
     */
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
        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'itemsMerged' => 0,
                'conflicts' => collect(),
                'message' => 'Migration failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Swap cart ownership by transferring cart from old identifier to new identifier.
     */
    private function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        return $this->storage->swapIdentifier($oldIdentifier, $newIdentifier, $instance);
    }

    /**
     * Merge items arrays from guest cart and user cart.
     *
     * @param  array<string, mixed>  $guestItems
     * @param  array<string, mixed>  $userItems
     * @return array<string, mixed>
     */
    private function mergeItems(array $guestItems, array $userItems): array
    {
        $mergedItems = $userItems;
        $mergeStrategy = config('cart.migration.merge_strategy', 'add_quantities');

        foreach ($guestItems as $itemId => $guestItemData) {
            $existingItem = $userItems[$itemId] ?? null;

            if ($existingItem) {
                $newQuantity = $this->resolveQuantityConflict(
                    $existingItem['quantity'] ?? 0,
                    $guestItemData['quantity'] ?? 0,
                    $mergeStrategy
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
     * Resolve quantity conflicts based on merge strategy.
     */
    private function resolveQuantityConflict(int $userQuantity, int $guestQuantity, string $strategy): int
    {
        return match ($strategy) {
            'add_quantities' => $userQuantity + $guestQuantity,
            'keep_highest_quantity' => max($userQuantity, $guestQuantity),
            'keep_user_cart' => $userQuantity,
            'replace_with_guest' => $guestQuantity,
            default => $userQuantity + $guestQuantity,
        };
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
     * Dispatch cart merged event.
     *
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
    ): void {
        $cartManager = Cart::getFacadeRoot();
        $targetCartInstance = $cartManager->getCartInstance($instance, $userIdentifier);

        event(new CartMerged(
            targetCart: $targetCartInstance,
            sourceCart: $cartManager->getCartInstance($instance, $guestIdentifier),
            totalItemsMerged: $totalItemsMerged,
            mergeStrategy: config('cart.migration.merge_strategy', 'add_quantities'),
            hadConflicts: $hadConflicts,
            originalSourceIdentifier: $guestIdentifier,
            originalTargetIdentifier: $userIdentifier,
        ));
    }
}
