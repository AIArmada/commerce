<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

describe('LowInventoryDetected', function (): void {
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
            'quantity_on_hand' => 5,
            'quantity_reserved' => 2,
            'reorder_point' => 10,
        ]);
    });

    it('event stores properties correctly', function (): void {
        $event = new LowInventoryDetected($this->item, $this->level);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
    });

    it('get available returns correct quantity', function (): void {
        $event = new LowInventoryDetected($this->item, $this->level);

        expect($event->getAvailable())->toBe(3); // 5 - 2 = 3
    });

    it('get reorder point returns correct value', function (): void {
        $event = new LowInventoryDetected($this->item, $this->level);

        expect($event->getReorderPoint())->toBe(10);
    });

    it('get reorder point returns default when null', function (): void {
        $anotherLocation = InventoryLocation::factory()->create([
            'name' => 'Another Location',
            'code' => 'TEST2',
        ]);
        $levelWithoutReorderPoint = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $anotherLocation->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 2,
            'reorder_point' => null,
        ]);

        $event = new LowInventoryDetected($this->item, $levelWithoutReorderPoint);

        expect($event->getReorderPoint())->toBe(10); // default value
    });
});
