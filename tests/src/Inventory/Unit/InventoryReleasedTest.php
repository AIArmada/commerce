<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Events\InventoryReleased;

describe('InventoryReleased', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
    });

    it('event stores properties correctly', function (): void {
        $event = new InventoryReleased($this->item, 5, 'cart-123');

        expect($event->inventoryable)->toBe($this->item);
        expect($event->quantity)->toBe(5);
        expect($event->cartId)->toBe('cart-123');
    });

    it('get event type returns correct value', function (): void {
        $event = new InventoryReleased($this->item, 5, 'cart-123');

        expect($event->getEventType())->toBe('inventory.released');
    });
});
