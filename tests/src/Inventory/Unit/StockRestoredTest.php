<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Events\StockRestored;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

describe('StockRestored', function (): void {
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
            'quantity_on_hand' => 25,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);
    });

    it('event stores properties correctly', function (): void {
        $event = new StockRestored($this->item, $this->level, AlertStatus::LowStock);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
        expect($event->previousStatus)->toBe(AlertStatus::LowStock);
    });

    it('get available returns correct quantity', function (): void {
        $event = new StockRestored($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getAvailable())->toBe(25);
    });

    it('get reorder point returns correct value', function (): void {
        $event = new StockRestored($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getReorderPoint())->toBe(10);
    });

    it('is above reorder point returns true when above threshold', function (): void {
        $event = new StockRestored($this->item, $this->level, AlertStatus::LowStock);

        expect($event->isAboveReorderPoint())->toBeTrue();
    });

    it('is above reorder point returns false when at or below threshold', function (): void {
        $anotherLocation = InventoryLocation::factory()->create([
            'name' => 'Another Location',
            'code' => 'TEST2',
        ]);
        $levelAtReorderPoint = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $anotherLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        $event = new StockRestored($this->item, $levelAtReorderPoint, AlertStatus::LowStock);

        expect($event->isAboveReorderPoint())->toBeFalse();
    });
});
