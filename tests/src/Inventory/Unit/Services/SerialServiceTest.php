<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Services\Serial\SerialService;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\Disposed;
use AIArmada\Inventory\States\InRepair;
use AIArmada\Inventory\States\Reserved;
use AIArmada\Inventory\States\Returned;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Shipped;
use AIArmada\Inventory\States\Sold;

describe('SerialService', function (): void {
    beforeEach(function (): void {
        $this->service = new SerialService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    });

    it('register serial', function (): void {
        $serial = $this->service->register(
            $this->item,
            'SN-001',
            $this->location->id,
            null,
            SerialCondition::New,
            1000,
            now()->addYear(),
            'user-123'
        );

        expect($serial)->toBeInstanceOf(InventorySerial::class);
        expect($serial->serial_number)->toBe('SN-001');
        expect($serial->status)->toBeInstanceOf(Available::class);
        expect($serial->condition)->toBe(SerialCondition::New->value);
        expect($serial->history)->toHaveCount(1);
    });

    it('find by serial number', function (): void {
        InventorySerial::factory()->create([
            'serial_number' => 'SN-FIND',
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $found = $this->service->findBySerialNumber('SN-FIND');

        expect($found)->not->toBeNull();
        expect($found->serial_number)->toBe('SN-FIND');
    });

    it('find by serial number returns null when not found', function (): void {
        $found = $this->service->findBySerialNumber('NONEXISTENT');

        expect($found)->toBeNull();
    });

    it('get serials for model', function (): void {
        InventorySerial::factory()->count(3)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $serials = $this->service->getSerialsForModel($this->item);

        expect($serials)->toHaveCount(3);
    });

    it('get available serials', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => SerialStatus::normalize(Available::class),
            'condition' => SerialCondition::New->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => SerialStatus::normalize(Sold::class),
        ]);

        $available = $this->service->getAvailableSerials($this->item, $this->location->id);

        expect($available)->toHaveCount(1);
    });

    it('transfer serial', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);
        $newLocation = InventoryLocation::factory()->create();

        $transferred = $this->service->transfer($serial, $newLocation->id, 'user-123', 'Moving to warehouse B');

        expect($transferred->location_id)->toBe($newLocation->id);
    });

    it('reserve serial', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
        ]);

        $reserved = $this->service->reserve($serial, 'order-123', 'user-123');

        expect($reserved->status)->toBeInstanceOf(Reserved::class);
    });

    it('reserve serial throws for invalid status', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Sold::class),
        ]);

        $this->service->reserve($serial, 'order-123');
    })->throws(InvalidArgumentException::class);

    it('release serial', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Reserved::class),
        ]);

        $released = $this->service->release($serial, 'user-123');

        expect($released->status)->toBeInstanceOf(Available::class);
    });

    it('sell serial', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Reserved::class),
        ]);

        $sold = $this->service->sell($serial, 'order-123', 'customer-456', 'user-123');

        expect($sold->status)->toBeInstanceOf(Sold::class);
        expect($sold->order_id)->toBe('order-123');
        expect($sold->customer_id)->toBe('customer-456');
    });

    it('ship serial', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => SerialStatus::normalize(Sold::class),
        ]);

        $shipped = $this->service->ship($serial, 'TRACK-123', 'user-123');

        expect($shipped->status)->toBeInstanceOf(Shipped::class);
        expect($shipped->location_id)->toBeNull();
    });

    it('process return', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Shipped::class),
        ]);

        $returned = $this->service->processReturn(
            $serial,
            $this->location->id,
            SerialCondition::Used,
            'Customer returned',
            'user-123'
        );

        expect($returned->status)->toBeInstanceOf(Returned::class);
        expect($returned->condition)->toBe(SerialCondition::Used->value);
        expect($returned->location_id)->toBe($this->location->id);
    });

    it('start repair', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Returned::class),
        ]);

        $inRepair = $this->service->startRepair($serial, 'Screen damage', 'user-123');

        expect($inRepair->status)->toBeInstanceOf(InRepair::class);
    });

    it('complete repair', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(InRepair::class),
            'condition' => SerialCondition::Damaged->value,
        ]);

        $repaired = $this->service->completeRepair($serial, SerialCondition::Refurbished, 'Repaired successfully', 'user-123');

        expect($repaired->status)->toBeInstanceOf(Available::class);
        expect($repaired->condition)->toBe(SerialCondition::Refurbished->value);
    });

    it('dispose', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => SerialStatus::normalize(Returned::class),
            'condition' => SerialCondition::Damaged->value,
        ]);

        $disposed = $this->service->dispose($serial, 'Beyond repair', 'user-123');

        expect($disposed->status)->toBeInstanceOf(Disposed::class);
        expect($disposed->location_id)->toBeNull();
    });

    it('update warranty', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addYear(),
        ]);

        $newExpiry = now()->addYears(2);
        $updated = $this->service->updateWarranty($serial, $newExpiry, 'Extended warranty', 'user-123');

        expect($updated->warranty_expires_at->toDateString())->toBe($newExpiry->toDateString());
    });

    it('get history', function (): void {
        $serial = $this->service->register($this->item, 'SN-HISTORY', $this->location->id);
        $this->service->reserve($serial->fresh(), 'order-123');
        $this->service->release($serial->fresh());

        $history = $this->service->getHistory($serial, 10);

        expect($history)->toHaveCount(3);
    });

    it('get history with limit', function (): void {
        $serial = $this->service->register($this->item, 'SN-LIMIT', $this->location->id);
        $this->service->reserve($serial->fresh(), 'order-123');
        $this->service->release($serial->fresh());
        $this->service->reserve($serial->fresh(), 'order-456');

        $history = $this->service->getHistory($serial, 2);

        expect($history)->toHaveCount(2);
    });
});
