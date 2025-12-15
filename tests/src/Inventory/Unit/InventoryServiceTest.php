<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAdjusted;
use AIArmada\Inventory\Events\InventoryReceived;
use AIArmada\Inventory\Events\InventoryShipped;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Exceptions\InsufficientStockException;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\Event;

class InventoryServiceTest extends InventoryTestCase
{
    protected InventoryService $inventoryService;

    protected InventoryItem $item;

    protected InventoryLocation $locationA;

    protected InventoryLocation $locationB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);
        $this->item = InventoryItem::create(['name' => 'Test Inventory Item']);
        $this->locationA = InventoryLocation::factory()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'priority' => 100,
        ]);
        $this->locationB = InventoryLocation::factory()->create([
            'name' => 'Overflow Warehouse',
            'code' => 'OVER',
            'priority' => 50,
        ]);
    }

    public function test_receives_inventory_and_records_movement(): void
    {
        Event::fake();

        $movement = $this->inventoryService->receive($this->item, $this->locationA->id, 15, 'restock', 'batch-1', 'user-1');
        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);

        expect($level)->not->toBeNull();
        expect($level->quantity_on_hand)->toBe(15);
        expect($movement->type)->toBe(MovementType::Receipt->value);

        Event::assertDispatched(InventoryReceived::class);
    }

    public function test_ships_inventory_and_dispatches_low_or_out_of_inventory_events_when_depleted(): void
    {
        Event::fake();

        $this->inventoryService->receive($this->item, $this->locationA->id, 3);
        $movement = $this->inventoryService->ship($this->item, $this->locationA->id, 3, 'sale', 'ORDER-1');
        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);

        expect($movement->type)->toBe(MovementType::Shipment->value);
        expect($level?->quantity_on_hand)->toBe(0);

        Event::assertDispatched(InventoryShipped::class);
        Event::assertDispatched(OutOfInventory::class);
    }

    public function test_throws_when_shipping_more_than_available(): void
    {
        $this->expectException(InsufficientStockException::class);

        $this->inventoryService->receive($this->item, $this->locationA->id, 2);

        $this->inventoryService->ship($this->item, $this->locationA->id, 3);
    }

    public function test_shipping_considers_reserved_quantity(): void
    {
        $this->inventoryService->receive($this->item, $this->locationA->id, 20);

        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);
        expect($level)->not->toBeNull();

        $level->update(['quantity_reserved' => 15]);

        $this->expectException(InsufficientStockException::class);

        $this->inventoryService->ship($this->item, $this->locationA->id, 10);
    }

    public function test_transfers_inventory_between_locations_and_updates_levels(): void
    {
        Event::fake();

        $this->inventoryService->receive($this->item, $this->locationA->id, 5);
        $this->inventoryService->transfer($this->item, $this->locationA->id, $this->locationB->id, 2, 'restock-b');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id);
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id);

        expect($levelA?->quantity_on_hand)->toBe(3);
        expect($levelB?->quantity_on_hand)->toBe(2);

        Event::assertDispatched(InventoryTransferred::class);
        Event::assertNotDispatched(InventoryShipped::class);
    }

    public function test_adjusts_inventory_to_a_target_quantity(): void
    {
        Event::fake();

        $this->inventoryService->receive($this->item, $this->locationA->id, 10);
        $movement = $this->inventoryService->adjust($this->item, $this->locationA->id, 4, 'cycle_count');

        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);

        expect($movement->type)->toBe(MovementType::Adjustment->value);
        expect($level?->quantity_on_hand)->toBe(4);

        Event::assertDispatched(InventoryAdjusted::class);
        Event::assertDispatched(LowInventoryDetected::class);
    }

    public function test_reports_aggregated_availability_across_locations(): void
    {
        $this->inventoryService->receive($this->item, $this->locationA->id, 7);
        $this->inventoryService->receive($this->item, $this->locationB->id, 5);

        $availability = $this->inventoryService->getAvailability($this->item);

        expect($availability)->toMatchArray([
            $this->locationA->id => 7,
            $this->locationB->id => 5,
        ]);
        expect($this->inventoryService->hasInventory($this->item, 10))->toBeTrue();
        expect($this->inventoryService->hasInventory($this->item, 20))->toBeFalse();
    }
}
