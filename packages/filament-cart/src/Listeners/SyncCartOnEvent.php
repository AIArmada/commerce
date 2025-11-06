<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Listeners;

use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\CartCreated;
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
 * - Cart lifecycle: CartCreated, CartCleared
 * - Item operations: ItemAdded, ItemUpdated, ItemRemoved
 * - Cart conditions: CartConditionAdded, CartConditionRemoved
 * - Item conditions: ItemConditionAdded, ItemConditionRemoved
 */
final class SyncCartOnEvent
{
    public function __construct(private CartSyncManager $syncManager) {}

    public function handle(
        CartCreated|CartCleared|ItemAdded|ItemUpdated|ItemRemoved|CartConditionAdded|CartConditionRemoved|ItemConditionAdded|ItemConditionRemoved $event
    ): void {
        $this->syncManager->sync($event->cart);
    }
}
