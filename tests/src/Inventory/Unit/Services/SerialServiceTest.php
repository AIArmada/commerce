<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Services\SerialService;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\Disposed;
use AIArmada\Inventory\States\InRepair;
use AIArmada\Inventory\States\Reserved;
use AIArmada\Inventory\States\Returned;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Shipped;
use AIArmada\Inventory\States\Sold;

class SerialServiceTest extends InventoryTestCase
{
    protected SerialService $service;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SerialService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    }

    public function test_register_serial(): void
    {
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
    }

    public function test_find_by_serial_number(): void
    {
        InventorySerial::factory()->create([
            'serial_number' => 'SN-FIND',
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $found = $this->service->findBySerialNumber('SN-FIND');

        expect($found)->not->toBeNull();
        expect($found->serial_number)->toBe('SN-FIND');
    }

    public function test_find_by_serial_number_returns_null_when_not_found(): void
    {
        $found = $this->service->findBySerialNumber('NONEXISTENT');

        expect($found)->toBeNull();
    }

    public function test_get_serials_for_model(): void
    {
        InventorySerial::factory()->count(3)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $serials = $this->service->getSerialsForModel($this->item);

        expect($serials)->toHaveCount(3);
    }

    public function test_get_available_serials(): void
    {
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
    }

    public function test_transfer_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);
        $newLocation = InventoryLocation::factory()->create();

        $transferred = $this->service->transfer($serial, $newLocation->id, 'user-123', 'Moving to warehouse B');

        expect($transferred->location_id)->toBe($newLocation->id);
    }

    public function test_reserve_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Available::class),
        ]);

        $reserved = $this->service->reserve($serial, 'order-123', 'user-123');

        expect($reserved->status)->toBeInstanceOf(Reserved::class);
    }

    public function test_reserve_serial_throws_for_invalid_status(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Sold::class),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->reserve($serial, 'order-123');
    }

    public function test_release_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Reserved::class),
        ]);

        $released = $this->service->release($serial, 'user-123');

        expect($released->status)->toBeInstanceOf(Available::class);
    }

    public function test_sell_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Reserved::class),
        ]);

        $sold = $this->service->sell($serial, 'order-123', 'customer-456', 'user-123');

        expect($sold->status)->toBeInstanceOf(Sold::class);
        expect($sold->order_id)->toBe('order-123');
        expect($sold->customer_id)->toBe('customer-456');
    }

    public function test_ship_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => SerialStatus::normalize(Sold::class),
        ]);

        $shipped = $this->service->ship($serial, 'TRACK-123', 'user-123');

        expect($shipped->status)->toBeInstanceOf(Shipped::class);
        expect($shipped->location_id)->toBeNull();
    }

    public function test_process_return(): void
    {
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
    }

    public function test_start_repair(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(Returned::class),
        ]);

        $inRepair = $this->service->startRepair($serial, 'Screen damage', 'user-123');

        expect($inRepair->status)->toBeInstanceOf(InRepair::class);
    }

    public function test_complete_repair(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::normalize(InRepair::class),
            'condition' => SerialCondition::Damaged->value,
        ]);

        $repaired = $this->service->completeRepair($serial, SerialCondition::Refurbished, 'Repaired successfully', 'user-123');

        expect($repaired->status)->toBeInstanceOf(Available::class);
        expect($repaired->condition)->toBe(SerialCondition::Refurbished->value);
    }

    public function test_dispose(): void
    {
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
    }

    public function test_update_warranty(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addYear(),
        ]);

        $newExpiry = now()->addYears(2);
        $updated = $this->service->updateWarranty($serial, $newExpiry, 'Extended warranty', 'user-123');

        expect($updated->warranty_expires_at->toDateString())->toBe($newExpiry->toDateString());
    }

    public function test_get_history(): void
    {
        $serial = $this->service->register($this->item, 'SN-HISTORY', $this->location->id);
        $this->service->reserve($serial->fresh(), 'order-123');
        $this->service->release($serial->fresh());

        $history = $this->service->getHistory($serial, 10);

        expect($history)->toHaveCount(3);
    }

    public function test_get_history_with_limit(): void
    {
        $serial = $this->service->register($this->item, 'SN-LIMIT', $this->location->id);
        $this->service->reserve($serial->fresh(), 'order-123');
        $this->service->release($serial->fresh());
        $this->service->reserve($serial->fresh(), 'order-456');

        $history = $this->service->getHistory($serial, 2);

        expect($history)->toHaveCount(2);
    }
}
