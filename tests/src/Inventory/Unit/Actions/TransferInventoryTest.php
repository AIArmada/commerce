<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Actions\TransferInventory;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Support\Facades\Event;

describe('TransferInventory', function (): void {
    beforeEach(function (): void {
        $this->action = app(TransferInventory::class);
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->fromLocation = InventoryLocation::factory()->create([
            'name' => 'From Location',
            'code' => 'FROM',
        ]);
        $this->toLocation = InventoryLocation::factory()->create([
            'name' => 'To Location',
            'code' => 'TO',
        ]);
    });

    it('transfers inventory between locations', function (): void {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $movement = $this->action->handle(
            $this->item,
            $this->fromLocation->id,
            $this->toLocation->id,
            20,
            'Transfer note',
            'user-1'
        );

        expect($movement->getMovementType())->toBe(MovementType::Transfer);
        expect($movement->quantity)->toBe(20);
        expect($movement->from_location_id)->toBe($this->fromLocation->id);
        expect($movement->to_location_id)->toBe($this->toLocation->id);

        Event::assertDispatched(InventoryTransferred::class);
    });

    it('updates source location quantity', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 30);

        $fromLevel = InventoryLevel::where('location_id', $this->fromLocation->id)->first();
        expect($fromLevel->quantity_on_hand)->toBe(70);
    });

    it('updates destination location quantity', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 30);

        $toLevel = InventoryLevel::where('location_id', $this->toLocation->id)->first();
        expect($toLevel)->not->toBeNull();
        expect($toLevel->quantity_on_hand)->toBe(30);
    });

    it('creates destination level if not exists', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 10);

        $toLevel = InventoryLevel::where('location_id', $this->toLocation->id)->first();
        expect($toLevel)->not->toBeNull();
    });

    it('throws exception when insufficient inventory', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 50);
    })->throws(InsufficientInventoryException::class);

    it('throws exception when no source level', function (): void {
        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 10);
    })->throws(InsufficientInventoryException::class);

    it('considers reserved quantity at source', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 40,
        ]);

        // Available is 50 - 40 = 10, so transferring 20 should fail

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 20);
    })->throws(InsufficientInventoryException::class);

    it('dispatches inventory transferred event', function (): void {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 25);

        Event::assertDispatched(InventoryTransferred::class, function (InventoryTransferred $event): bool {
            return $event->inventoryable->is($this->item);
        });
    });
});
