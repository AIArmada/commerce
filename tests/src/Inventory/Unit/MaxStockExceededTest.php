<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Events\MaxStockExceeded;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

describe('MaxStockExceeded', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
        $this->level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 150,
            'quantity_reserved' => 0,
            'max_stock' => 100,
        ]);
    });

    it('event stores properties correctly', function (): void {
        $event = new MaxStockExceeded($this->item, $this->level);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
    });

    it('get on hand returns correct quantity', function (): void {
        $event = new MaxStockExceeded($this->item, $this->level);

        expect($event->getOnHand())->toBe(150);
    });

    it('get max stock returns correct value', function (): void {
        $event = new MaxStockExceeded($this->item, $this->level);

        expect($event->getMaxStock())->toBe(100);
    });

    it('get overage calculates correctly', function (): void {
        $event = new MaxStockExceeded($this->item, $this->level);

        expect($event->getOverage())->toBe(50); // 150 - 100 = 50
    });

    it('get max stock returns zero when null', function (): void {
        $anotherLocation = InventoryLocation::factory()->create([
            'name' => 'Another Location',
            'code' => 'TEST2',
        ]);
        $levelWithoutMaxStock = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $anotherLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'max_stock' => null,
        ]);

        $event = new MaxStockExceeded($this->item, $levelWithoutMaxStock);

        expect($event->getMaxStock())->toBe(0);
        expect($event->getOverage())->toBe(50); // 50 - 0 = 50
    });
});
