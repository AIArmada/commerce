<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\Stock\StockThresholdService;

describe('StockThresholdService', function (): void {
    beforeEach(function (): void {
        $this->service = app(StockThresholdService::class);
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    });

    it('calculate status returns out of stock when available is zero', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::OutOfStock);
    });

    it('calculate status returns safety breached when below safety stock', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::SafetyBreached);
    });

    it('calculate status returns low stock when below reorder point', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::LowStock);
    });

    it('calculate status returns over stock when above max stock', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'max_stock' => 50,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::OverStock);
    });

    it('calculate status returns none when normal', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
            'max_stock' => 100,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::None);
    });

    it('needs attention returns true for critical statuses', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $needsAttention = $this->service->needsAttention($level);

        expect($needsAttention)->toBeTrue();
    });

    it('needs attention returns false for normal and over stock', function (): void {
        $location2 = InventoryLocation::factory()->create([
            'name' => 'Test Location 2',
            'code' => 'TEST2',
        ]);
        $location3 = InventoryLocation::factory()->create([
            'name' => 'Test Location 3',
            'code' => 'TEST3',
        ]);
        $normalLevel = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $overStockLevel = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location3->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'max_stock' => 50,
        ]);

        expect($this->service->needsAttention($normalLevel))->toBeFalse();
        expect($this->service->needsAttention($overStockLevel))->toBeFalse();
    });
});
