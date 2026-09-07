<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;

describe('InventoryTransferred', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->fromLocation = InventoryLocation::factory()->create([
            'name' => 'From Location',
            'code' => 'FROM',
        ]);
        $this->toLocation = InventoryLocation::factory()->create([
            'name' => 'To Location',
            'code' => 'TO',
        ]);
        $this->fromLevel = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 10,
        ]);
        $this->toLevel = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->toLocation->id,
            'quantity_on_hand' => 5,
        ]);
        $this->movement = InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'from_location_id' => $this->fromLocation->id,
            'to_location_id' => $this->toLocation->id,
            'type' => 'transfer',
            'quantity' => 3,
        ]);
    });

    it('event stores properties correctly', function (): void {
        $event = new InventoryTransferred($this->item, $this->fromLevel, $this->toLevel, $this->movement);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->fromLevel)->toBe($this->fromLevel);
        expect($event->toLevel)->toBe($this->toLevel);
        expect($event->movement)->toBe($this->movement);
    });
});
