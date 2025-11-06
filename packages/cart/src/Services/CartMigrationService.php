<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartMigrationService
{
    /**
     * Configuration array for migration settings.
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Optional storage instance for testing.
     */
    private ?StorageInterface $storage = null;

    /**
     * Create a new cart migration service instance.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [], ?StorageInterface $storage = null)
    {
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * Get the appropriate cart identifier for a user or guest session.
     *
     * NOTE: This returns an IDENTIFIER (user ID or session ID) that identifies WHO owns the cart,
     * NOT an instance name (which identifies WHICH cart - like 'wishlist', 'shopping', etc.)
     *
     * Examples:
     * - Guest identifier: session ID like "abc123def456"
     * - User identifier: user ID like "42"
     * - Instance names: "default", "wishlist", "compare", etc.
     */
    public function getIdentifier(?int $userId = null, ?string $sessionId = null): string
    {
        if ($userId) {
            return (string) $userId;
        }

        if ($sessionId) {
            return $sessionId;
        }

        // Fallback to current session if no params provided
        return session()->getId();
    }

    /**
     * Migrate guest cart to user cart when user logs in.
     *
     * IMPORTANT: This method works with IDENTIFIERS (who owns the cart) and INSTANCES (which cart type).
     *
     * @param  int  $userId  The user ID that will become the new cart IDENTIFIER
     * @param  string  $instance  The cart instance name (e.g., 'default', 'wishlist')
     * @param  string  $sessionId  The guest session ID (cart IDENTIFIER) to migrate from
     *
     * Example: migrateGuestCartToUser(42, 'default', 'abc123')
     * - Migrates from identifier 'abc123' to identifier '42'
     * - For the 'default' cart instance
     */
    public function migrateGuestCartToUser(string|int $userId, string $instance, string $sessionId): bool
    {
        $guestIdentifier = $sessionId;
        $userIdentifier = (string) $userId;

        // Get the storage directly to work with specific identifiers
        $storage = Cart::storage();

        // Get guest cart items for the specified instance
        $guestItems = $storage->getItems($guestIdentifier, $instance);

        // If guest cart is empty, nothing to migrate
        if (empty($guestItems)) {
            return false;
        }

        // Get existing user cart items for the same instance
        $userItems = $storage->getItems($userIdentifier, $instance);

        // If user cart is empty, forget it and swap guest cart to user
        if (empty($userItems)) {
            // Swap guest cart to user cart (ownership transfer)
            $this->swap($guestIdentifier, $userIdentifier, $instance);

            // Dispatch event if events are enabled
            if (config('cart.events', true)) {
                // Get Cart instances for the event
                $cartManager = Cart::getFacadeRoot();
                $targetCartInstance = $cartManager->getCartInstance($instance);

                event(new CartMerged(
                    targetCart: $targetCartInstance,
                    sourceCart: $targetCartInstance, // Limited by design
                    totalItemsMerged: array_sum(array_column($guestItems, 'quantity')),
                    mergeStrategy: $this->config['merge_strategy'] ?? 'add_quantities',
                    hadConflicts: false,
                    originalSourceIdentifier: $guestIdentifier, // Preserve original guest identifier
                    originalTargetIdentifier: $userIdentifier, // Preserve original user identifier
                ));
            }

            return true;
        }

        // Merge the cart data using arrays directly
        $mergedItems = $this->mergeItemsArray($guestItems, $userItems);

        // Save merged items to user cart
        $storage->putItems($userIdentifier, $instance, $mergedItems);

        // Also migrate conditions if any
        $guestConditions = $storage->getConditions($guestIdentifier, $instance);
        if (! empty($guestConditions)) {
            $userConditions = $storage->getConditions($userIdentifier, $instance);
            $mergedConditions = $this->mergeConditionsData($guestConditions, $userConditions);
            $storage->putConditions($userIdentifier, $instance, $mergedConditions);
        }

        // Forget guest cart
        $storage->forget($guestIdentifier, $instance);

        // Dispatch event if events are enabled
        if (config('cart.events', true)) {
            // Get Cart instances for the event
            $cartManager = Cart::getFacadeRoot();
            $targetCartInstance = $cartManager->getCartInstance($instance);

            event(new CartMerged(
                targetCart: $targetCartInstance,
                sourceCart: $targetCartInstance, // Limited by design
                totalItemsMerged: array_sum(array_column($mergedItems, 'quantity')),
                mergeStrategy: $this->config['merge_strategy'] ?? 'add_quantities',
                hadConflicts: count($mergedItems) > count($guestItems),
                originalSourceIdentifier: $guestIdentifier, // Preserve original guest identifier
                originalTargetIdentifier: $userIdentifier, // Preserve original user identifier
            ));
        }

        return true;
    }

    /**
     * Migrate guest cart to user cart when user logs in (user object version).
     */
    public function migrateGuestCartForUser(mixed $user, string $instance, string $sessionId): object
    {
        $success = $this->migrateGuestCartToUser($user->id, $instance, $sessionId);

        return (object) [
            'success' => $success,
            'itemsMerged' => $success ? 1 : 0, // Simplified for now
            'conflicts' => collect(),
            'message' => $success ? 'Cart migration completed successfully' : 'No items to migrate',
        ];
    }

    /**
     * Automatically switch to appropriate cart identifier based on authentication state.
     * Note: The Cart system automatically determines the identifier based on authentication state,
     * so this method serves as a placeholder for potential future functionality.
     *
     * IMPORTANT: This does NOT change instance names (like 'default', 'wishlist').
     * Instance names should only be changed explicitly by the developer.
     * This only affects WHO owns the cart (user ID vs session ID), not WHICH cart type.
     */
    public function autoSwitchCartIdentifier(): void
    {
        // The Cart class automatically determines the correct identifier based on:
        // - Auth::id() for authenticated users
        // - Session::getId() for guest users
        // No manual switching is needed, this is handled automatically by getIdentifier()

        // Instance names ('default', 'wishlist', etc.) remain unchanged
        // Only the cart identifier (who owns the cart) is managed automatically
    }

    /**
     * Get current identifier based on authentication state.
     */
    public function getCurrentIdentifier(): string
    {
        if (Auth::check()) {
            return $this->getIdentifier((int) Auth::id());
        }

        return $this->getIdentifier(null, session()->getId());
    }

    /**
     * Get guest identifier for current or specified session.
     */
    public function getGuestIdentifier(?string $sessionId = null): string
    {
        return $this->getIdentifier(null, $sessionId ?? session()->getId());
    }

    /**
     * Get user identifier for specified user.
     */
    public function getUserIdentifier(int $userId): string
    {
        return $this->getIdentifier($userId);
    }

    /**
     * Swap cart ownership by transferring cart from old identifier to new identifier.
     *
     * This ensures the new identifier has an active cart by transferring
     * the cart from the old identifier, regardless of whether the new identifier
     * already has a cart. This prevents cart abandonment by ensuring continued
     * cart activity under the new identifier.
     *
     * @param  string  $oldIdentifier  The old identifier (e.g., guest session)
     * @param  string  $newIdentifier  The new identifier (e.g., user ID)
     * @param  string  $instance  The cart instance name (e.g., 'default', 'wishlist')
     * @return bool True if swap was successful (new identifier now has the cart)
     */
    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        $storage = $this->storage ?: Cart::storage();

        // Use the swapIdentifier method which simply transfers cart ownership
        return $storage->swapIdentifier($oldIdentifier, $newIdentifier, $instance);
    }

    /**
     * Swap guest cart when user logs in (simplified version of migration).
     *
     * Unlike migration which merges carts, this simply transfers the guest
     * cart to the user identifier, ensuring the user gets an active cart.
     *
     * @param  int  $userId  The user ID that will take over cart ownership
     * @param  string  $instance  The cart instance name (e.g., 'default', 'wishlist')
     * @param  string|null  $guestSessionId  The guest session ID to swap from
     * @return bool True if swap was successful
     */
    public function swapGuestCartToUser(int $userId, string $instance = 'default', ?string $guestSessionId = null): bool
    {
        $guestIdentifier = $this->getIdentifier(null, $guestSessionId ?? session()->getId());
        $userIdentifier = $this->getIdentifier($userId);

        return $this->swap($guestIdentifier, $userIdentifier, $instance);
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
            default => $userQuantity + $guestQuantity, // Fallback to add_quantities
        };
    }

    /**
     * Merge cart conditions from guest to user cart.
     *
     * @param  array<string, mixed>  $guestConditions
     * @param  array<string, mixed>  $userConditions
     * @return array<string, mixed>
     */
    private function mergeConditionsData(array $guestConditions, array $userConditions): array
    {
        // For now, keep all conditions from both carts
        // In the future, we could add strategies for condition conflicts
        $mergedConditions = $userConditions;

        foreach ($guestConditions as $conditionName => $conditionData) {
            if (! isset($mergedConditions[$conditionName])) {
                $mergedConditions[$conditionName] = $conditionData;
            }
            // If condition exists in both, keep the user's version (could be configurable)
        }

        return $mergedConditions;
    }

    /**
     * Merge items arrays from guest cart and user cart.
     *
     * @param  array<string, mixed>  $guestItems
     * @param  array<string, mixed>  $userItems
     * @return array<string, mixed>
     */
    private function mergeItemsArray(array $guestItems, array $userItems): array
    {
        $mergedItems = $userItems; // Start with user items
        $mergeStrategy = config('cart.migration.merge_strategy', 'add_quantities');

        foreach ($guestItems as $itemId => $guestItemData) {
            $existingItem = $userItems[$itemId] ?? null;

            if ($existingItem) {
                // Handle conflict based on strategy
                $newQuantity = $this->resolveQuantityConflict(
                    $existingItem['quantity'] ?? 0,
                    $guestItemData['quantity'] ?? 0,
                    $mergeStrategy
                );

                // Update the quantity in merged items
                $mergedItems[$itemId]['quantity'] = $newQuantity;
            } else {
                // No conflict, add the guest item
                $mergedItems[$itemId] = $guestItemData;
            }
        }

        return $mergedItems;
    }
}
