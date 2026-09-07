<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Models\InventorySerialHistory;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\Reserved;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Sold;
use Carbon\CarbonImmutable;

describe('InventorySerial', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create();
    });

    it('can create serial', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'serial_number' => 'SN-12345',
            'status' => SerialStatus::normalize(Available::class),
            'condition' => SerialCondition::New->value,
        ]);

        expect($serial)->toBeInstanceOf(InventorySerial::class);
        expect($serial->serial_number)->toBe('SN-12345');
    });

    it('inventoryable relationship', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        expect($serial->inventoryable)->not->toBeNull();
        expect($serial->inventoryable->id)->toBe($this->item->id);
    });

    it('location relationship', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);

        expect($serial->location)->not->toBeNull();
        expect($serial->location->id)->toBe($this->location->id);
    });

    it('batch relationship', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_id' => $batch->id,
        ]);

        expect($serial->batch)->not->toBeNull();
        expect($serial->batch->id)->toBe($batch->id);
    });

    it('get status enum', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
        ]);

        expect($serial->getStatusEnum())->toBeInstanceOf(Available::class);
    });

    it('get condition enum', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'condition' => SerialCondition::New->value,
        ]);

        expect($serial->getConditionEnum())->toBe(SerialCondition::New);
    });

    it('is warranty active returns true when future', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addYear(),
        ]);

        expect($serial->is_warranty_active)->toBeTrue();
    });

    it('is warranty active returns false when past', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->subDay(),
        ]);

        expect($serial->is_warranty_active)->toBeFalse();
    });

    it('is warranty active returns false when null', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => null,
        ]);

        expect($serial->is_warranty_active)->toBeFalse();
    });

    it('days until warranty expires', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addDays(30),
        ]);

        expect($serial->days_until_warranty_expires)->toBeGreaterThanOrEqual(29);
        expect($serial->days_until_warranty_expires)->toBeLessThanOrEqual(30);
    });

    it('is under warranty method', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addYear(),
        ]);

        expect($serial->isUnderWarranty())->toBeTrue();
    });

    it('warranty days remaining method', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addDays(45),
        ]);

        expect($serial->warrantyDaysRemaining())->toBeGreaterThanOrEqual(44);
        expect($serial->warrantyDaysRemaining())->toBeLessThanOrEqual(45);
    });

    it('is available', function (): void {
        $available = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
            'condition' => SerialCondition::New->value,
        ]);

        $sold = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Sold::class),
            'condition' => SerialCondition::New->value,
        ]);

        expect($available->isAvailable())->toBeTrue();
        expect($sold->isAvailable())->toBeFalse();
    });

    it('scope with status', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Sold::class),
        ]);

        $available = InventorySerial::withStatus(Available::class)->get();

        expect($available)->toHaveCount(1);
    });

    it('scope available', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Sold::class),
        ]);

        $available = InventorySerial::available()->get();

        expect($available)->toHaveCount(1);
    });

    it('scope in stock', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Reserved::class),
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Sold::class),
        ]);

        $inStock = InventorySerial::inStock()->get();

        expect($inStock)->toHaveCount(2);
    });

    it('scope at location', function (): void {
        $location2 = InventoryLocation::factory()->create();

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
        ]);

        $atLocation = InventorySerial::atLocation($this->location->id)->get();

        expect($atLocation)->toHaveCount(1);
    });

    it('scope with condition', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'condition' => SerialCondition::New->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'condition' => SerialCondition::Used->value,
        ]);

        $newCondition = InventorySerial::withCondition(SerialCondition::New)->get();

        expect($newCondition)->toHaveCount(1);
    });

    it('scope sellable', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
            'condition' => SerialCondition::New->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
            'condition' => SerialCondition::Damaged->value,
        ]);

        $sellable = InventorySerial::sellable()->get();

        expect($sellable)->toHaveCount(1);
    });

    it('scope under warranty', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addYear(),
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->subDay(),
        ]);

        $underWarranty = InventorySerial::underWarranty()->get();

        expect($underWarranty)->toHaveCount(1);
    });

    it('scope warranty expiring soon', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addDays(15),
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addDays(60),
        ]);

        $expiringSoon = InventorySerial::warrantyExpiringSoon(30)->get();

        expect($expiringSoon)->toHaveCount(1);
    });

    it('history relationship', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        InventorySerialHistory::create([
            'serial_id' => $serial->id,
            'event_type' => 'received',
            'from_location_id' => null,
            'to_location_id' => $this->location->id,
            'occurred_at' => now(),
        ]);

        expect($serial->history)->toHaveCount(1);
    });

    it('deleting serial cascades to history', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $history = InventorySerialHistory::create([
            'serial_id' => $serial->id,
            'event_type' => 'received',
            'occurred_at' => now(),
        ]);
        $historyId = $history->id;

        $serial->delete();

        expect(InventorySerialHistory::find($historyId))->toBeNull();
    });

    it('casts are correct', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'unit_cost_minor' => 5000,
            'warranty_expires_at' => now()->addYear(),
            'metadata' => ['key' => 'value'],
        ]);

        expect($serial->unit_cost_minor)->toBeInt();
        expect($serial->warranty_expires_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($serial->metadata)->toBeArray();
    });

    it('assigned to relationship', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'assigned_to_type' => $this->item->getMorphClass(),
            'assigned_to_id' => $this->item->getKey(),
        ]);

        expect($serial->assignedTo)->not->toBeNull();
    });

    it('can transition to', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
        ]);

        // Available can transition to Reserved
        expect($serial->canTransitionTo(Reserved::class))->toBeTrue();
    });

    it('transition to changes status', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
        ]);

        $result = $serial->transitionStatusTo(Reserved::class);

        expect($result)->toBe($serial);
        expect($serial->fresh()->status)->toBeInstanceOf(Reserved::class);
    });

    it('transition to throws on invalid transition', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Sold::class),
        ]);

        expect(fn () => $serial->transitionStatusTo(Available::class))
            ->toThrow(InvalidArgumentException::class);
    });

    it('days until warranty expires with null', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => null,
        ]);

        expect($serial->days_until_warranty_expires)->toBeNull();
    });
});
