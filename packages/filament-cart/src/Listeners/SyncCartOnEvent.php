<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Listeners;

use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemConditionAdded;
use AIArmada\Cart\Events\ItemConditionRemoved;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\FilamentCart\Services\CartSyncManager;

/**
 * Unified listener that syncs normalized cart snapshot whenever cart state changes.
 *
 * This listener handles all cart events that require database synchronization:
 * - Cart lifecycle: CartCreated, CartCleared (sync empty state), CartDestroyed (delete normalized)
 * - Item operations: ItemAdded, ItemUpdated, ItemRemoved
 * - Cart conditions: CartConditionAdded, CartConditionRemoved
 * - Item conditions: ItemConditionAdded, ItemConditionRemoved
 *
 * Strategy:
 * - CartDestroyed: Cart deleted → delete normalized cart (source is gone)
 * - All other events (including CartCleared): Cart exists → sync normalized cart (mirror the state)
 *   - CartCleared means cart exists with empty state → normalized cart should reflect this truth
 */
final class SyncCartOnEvent
{
    public function __construct(private CartSyncManager $syncManager) {}

    public function handle(
        CartCreated|CartCleared|CartDestroyed|ItemAdded|ItemUpdated|ItemRemoved|CartConditionAdded|CartConditionRemoved|ItemConditionAdded|ItemConditionRemoved $event
    ): void {
        // CartDestroyed: cart no longer exists → delete normalized cart
        if ($event instanceof CartDestroyed) {
            $this->syncManager->deleteByIdentity($event->instance, $event->identifier);
            return;
        }

        // All other events (including CartCleared): cart exists → sync its state
        // CartCleared means the cart exists but is empty - normalized cart must mirror this truth
        $this->syncManager->sync($event->cart);
    }
}
