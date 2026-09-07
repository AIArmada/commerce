<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Events\SafetyStockBreached;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

describe('SafetyStockBreached', function (): void {
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
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);
    });

    it('event stores properties correctly', function (): void {
        $event = new SafetyStockBreached($this->item, $this->level, AlertStatus::LowStock);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
        expect($event->previousStatus)->toBe(AlertStatus::LowStock);
    });

    it('get available returns correct quantity', function (): void {
        $event = new SafetyStockBreached($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getAvailable())->toBe(3);
    });

    it('get safety stock returns correct value', function (): void {
        $event = new SafetyStockBreached($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getSafetyStock())->toBe(10);
    });

    it('get deficit calculates correctly', function (): void {
        $event = new SafetyStockBreached($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getDeficit())->toBe(7); // 10 - 3 = 7
    });

    it('get safety stock returns zero when null', function (): void {
        $anotherLocation = InventoryLocation::factory()->create([
            'name' => 'Another Location',
            'code' => 'TEST2',
        ]);
        $levelWithoutSafetyStock = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $anotherLocation->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => null,
        ]);

        $event = new SafetyStockBreached($this->item, $levelWithoutSafetyStock, AlertStatus::LowStock);

        expect($event->getSafetyStock())->toBe(0);
        expect($event->getDeficit())->toBe(0); // 0 - 5 = 0 (max(0, ...))
    });
});
