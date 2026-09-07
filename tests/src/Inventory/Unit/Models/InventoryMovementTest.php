<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('InventoryMovement', function (): void {
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
    });

    it('can create movement', function (): void {
        $movement = InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'to_location_id' => $this->toLocation->id,
            'type' => MovementType::Receipt->value,
            'quantity' => 10,
        ]);

        expect($movement)->toBeInstanceOf(InventoryMovement::class);
        expect($movement->quantity)->toBe(10);
    });

    it('get movement type returns enum', function (): void {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Receipt->value,
        ]);

        expect($movement->getMovementType())->toBe(MovementType::Receipt);
    });

    it('is receipt returns true for receipt type', function (): void {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Receipt->value,
        ]);

        expect($movement->isReceipt())->toBeTrue();
        expect($movement->isShipment())->toBeFalse();
    });

    it('is shipment returns true for shipment type', function (): void {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Shipment->value,
        ]);

        expect($movement->isShipment())->toBeTrue();
        expect($movement->isReceipt())->toBeFalse();
    });

    it('is transfer returns true for transfer type', function (): void {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Transfer->value,
        ]);

        expect($movement->isTransfer())->toBeTrue();
    });

    it('is adjustment returns true for adjustment type', function (): void {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Adjustment->value,
        ]);

        expect($movement->isAdjustment())->toBeTrue();
    });

    it('from location relationship', function (): void {
        $movement = InventoryMovement::factory()->create([
            'from_location_id' => $this->fromLocation->id,
            'to_location_id' => null,
            'type' => MovementType::Shipment->value,
        ]);

        expect($movement->fromLocation)->not->toBeNull();
        expect($movement->fromLocation->id)->toBe($this->fromLocation->id);
    });

    it('to location relationship', function (): void {
        $movement = InventoryMovement::factory()->create([
            'from_location_id' => null,
            'to_location_id' => $this->toLocation->id,
            'type' => MovementType::Receipt->value,
        ]);

        expect($movement->toLocation)->not->toBeNull();
        expect($movement->toLocation->id)->toBe($this->toLocation->id);
    });

    it('inventoryable relationship', function (): void {
        $movement = InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        expect($movement->inventoryable)->not->toBeNull();
        expect($movement->inventoryable->id)->toBe($this->item->id);
    });

    it('scope of type filters by movement type', function (): void {
        InventoryMovement::factory()->create(['type' => MovementType::Receipt->value]);
        InventoryMovement::factory()->create(['type' => MovementType::Receipt->value]);
        InventoryMovement::factory()->create(['type' => MovementType::Shipment->value]);

        $receipts = InventoryMovement::ofType(MovementType::Receipt)->get();

        expect($receipts)->toHaveCount(2);
    });

    it('scope for reference filters by reference', function (): void {
        InventoryMovement::factory()->create(['reference' => 'ORDER-123']);
        InventoryMovement::factory()->create(['reference' => 'ORDER-123']);
        InventoryMovement::factory()->create(['reference' => 'ORDER-456']);

        $movements = InventoryMovement::forReference('ORDER-123')->get();

        expect($movements)->toHaveCount(2);
    });

    it('scope at location filters by location', function (): void {
        InventoryMovement::factory()->create(['from_location_id' => $this->fromLocation->id]);
        InventoryMovement::factory()->create(['to_location_id' => $this->fromLocation->id]);
        InventoryMovement::factory()->create(['to_location_id' => $this->toLocation->id]);

        $movements = InventoryMovement::atLocation($this->fromLocation->id)->get();

        expect($movements)->toHaveCount(2);
    });

    it('occurred at is cast to datetime', function (): void {
        $movement = InventoryMovement::factory()->create([
            'occurred_at' => '2025-01-01 12:00:00',
        ]);

        expect($movement->occurred_at)->toBeInstanceOf(CarbonImmutable::class);
    });

    it('user relationship', function (): void {
        $movement = new InventoryMovement;
        $relation = $movement->user();

        expect($relation)->toBeInstanceOf(BelongsTo::class);
    });
});
