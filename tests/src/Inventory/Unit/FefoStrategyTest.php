<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Strategies\AllocationContext;
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

    it('name returns correct value', function (): void {
        expect($this->strategy->name())->toBe('fefo');
    });

    it('label returns correct value', function (): void {
        expect($this->strategy->label())->toBe('First Expired, First Out');
    });

    it('description returns correct value', function (): void {
        expect($this->strategy->description())->toContain('Allocates inventory from batches with the earliest expiry dates first');
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

    it('allocate with context location filter', function (): void {
        $context = new AllocationContext;
        $context->locationId = $this->location->id;

        $allocations = $this->strategy->allocate($this->item, 10, $context);

        expect($allocations)->toBeEmpty();
    });

    it('can fulfill with context location filter', function (): void {
        $context = new AllocationContext;
        $context->locationId = $this->location->id;

        $canFulfill = $this->strategy->canFulfill($this->item, 10, $context);

        expect($canFulfill)->toBeFalse();
    });

    it('get recommended order with context location filter', function (): void {
        $context = new AllocationContext;
        $context->locationId = $this->location->id;

        $order = $this->strategy->getRecommendedOrder($this->item, $context);

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

    it('context default values', function (): void {
        $context = new AllocationContext;

        expect($context->locationId)->toBeNull();
        expect($context->excludeExpiringSoon)->toBeFalse();
        expect($context->minDaysToExpiry)->toBe(7);
    });

    it('context can set location', function (): void {
        $context = new AllocationContext;
        $context->locationId = 'test-location-id';

        expect($context->locationId)->toBe('test-location-id');
    });

    it('context can exclude expiring soon', function (): void {
        $context = new AllocationContext;
        $context->excludeExpiringSoon = true;
        $context->minDaysToExpiry = 14;

        expect($context->excludeExpiringSoon)->toBeTrue();
        expect($context->minDaysToExpiry)->toBe(14);
    });
});
