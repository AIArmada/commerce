<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Actions\ShipInventory;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryShipped;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Support\Facades\Event;

describe('ShipInventory', function (): void {
    beforeEach(function (): void {
        $this->action = app(ShipInventory::class);
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    });

    it('ships inventory and creates movement', function (): void {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
        ]);

        $movement = $this->action->handle(
            $this->item,
            $this->location->id,
            5,
            'sale',
            'ORDER-123',
            'Shipped to customer',
            'user-1'
        );

        expect($movement->getMovementType())->toBe(MovementType::Shipment);
        expect($movement->quantity)->toBe(5);
        expect($movement->reason)->toBe('sale');
        expect($movement->reference)->toBe('ORDER-123');
        expect($movement->note)->toBe('Shipped to customer');
        expect($movement->user_id)->toBe('user-1');

        Event::assertDispatched(InventoryShipped::class);
    });

    it('decrements quantity on hand', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->location->id, 10);

        $level = $this->item->inventoryLevels()->where('location_id', $this->location->id)->first();
        expect($level->quantity_on_hand)->toBe(20);
    });

    it('throws exception when insufficient inventory', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->location->id, 10);
    })->throws(InsufficientInventoryException::class);

    it('throws exception when no inventory level', function (): void {
        $this->action->handle($this->item, $this->location->id, 5);
    })->throws(InsufficientInventoryException::class);

    it('considers reserved quantity', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 15,
        ]);

        // Available is 20 - 15 = 5, so shipping 10 should fail

        $this->action->handle($this->item, $this->location->id, 10);
    })->throws(InsufficientInventoryException::class);

    it('dispatches inventory shipped event', function (): void {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->location->id, 10);

        Event::assertDispatched(InventoryShipped::class, function (InventoryShipped $event): bool {
            return $event->inventoryable->is($this->item);
        });
    });
});
