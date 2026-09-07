<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Actions\AdjustInventory;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAdjusted;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Support\Facades\Event;

describe('AdjustInventory', function (): void {
    beforeEach(function (): void {
        $this->action = app(AdjustInventory::class);
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    });

    it('adjusts inventory and creates movement', function (): void {
        Event::fake();

        // Create initial level
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $movement = $this->action->handle($this->item, $this->location->id, 15, 'correction', 'Test note', 'user-1');

        expect($movement->getMovementType())->toBe(MovementType::Adjustment);
        expect($movement->quantity)->toBe(5); // 15 - 10 = 5
        expect($movement->reason)->toBe('correction');
        expect($movement->note)->toBe('Test note');
        expect($movement->user_id)->toBe('user-1');

        Event::assertDispatched(InventoryAdjusted::class);
    });

    it('creates inventory level if not exists', function (): void {
        $movement = $this->action->handle($this->item, $this->location->id, 20);

        $level = $this->item->inventoryLevels()->where('location_id', $this->location->id)->first();
        expect($level)->not->toBeNull();
        expect($level->quantity_on_hand)->toBe(20);
    });

    it('handles negative adjustment', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
        ]);

        $movement = $this->action->handle($this->item, $this->location->id, 5);

        expect($movement->quantity)->toBe(15); // abs(5 - 20) = 15
        expect($movement->from_location_id)->toBe($this->location->id);
        expect($movement->to_location_id)->toBeNull();

        $level = $this->item->inventoryLevels()->where('location_id', $this->location->id)->first();
        expect($level->quantity_on_hand)->toBe(5);
    });

    it('dispatches inventory adjusted event with correct data', function (): void {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->location->id, 25);

        Event::assertDispatched(InventoryAdjusted::class, function (InventoryAdjusted $event): bool {
            return $event->oldQuantity === 10
                && $event->newQuantity === 25
                && $event->inventoryable->is($this->item);
        });
    });
});
