<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Data\AllocationContext;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Strategies\FefoStrategy;

describe('FefoStrategy', function (): void {
    beforeEach(function (): void {
        $this->strategy = new FefoStrategy;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    });

    it('allocate returns empty array when no batches', function (): void {
        $allocations = $this->strategy->allocate($this->item, 10);

        expect($allocations)->toBeEmpty();
    });

    it('can fulfill returns false when no batches', function (): void {
        $canFulfill = $this->strategy->canFulfill($this->item, 10);

        expect($canFulfill)->toBeFalse();
    });

    it('get recommended order returns empty collection when no batches', function (): void {
        $order = $this->strategy->getRecommendedOrder($this->item);

        expect($order)->toBeEmpty();
    });

    it('get recommended order returns fefo ordered batches', function (): void {
        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active->value,
            'expires_at' => now()->addDays(30),
        ]);

        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active->value,
            'expires_at' => now()->addDays(10),
        ]);

        $order = $this->strategy->getRecommendedOrder($this->item);

        expect($order)->toHaveCount(2);
        expect($order->first()->id)->toBe($batch2->id); // Expires sooner
    });

    it('uses the same expiry and available quantity constraints for allocation and fulfillment', function (): void {
        $eligible = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 3,
            'status' => BatchStatus::Active->value,
            'expires_at' => now()->addDays(30),
        ]);

        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active->value,
            'expires_at' => now()->addDays(2),
        ]);

        $context = new AllocationContext(excludeExpiringSoon: true, allowPartialAllocation: false);

        expect($this->strategy->canFulfill($this->item, 7, $context))->toBeTrue()
            ->and($this->strategy->canFulfill($this->item, 8, $context))->toBeFalse()
            ->and($this->strategy->allocate($this->item, 8, $context))->toBeEmpty()
            ->and($this->strategy->allocate($this->item, 7, $context)[0]['quantity'])->toBe(7)
            ->and($this->strategy->getRecommendedOrder($this->item, $context)->modelKeys())->toBe([$eligible->id]);
    });
});
