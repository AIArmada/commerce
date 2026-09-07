<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Events\BatchCreated;
use AIArmada\Inventory\Models\InventoryBatch;

describe('BatchCreated', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-001',
            'quantity_received' => 100,
        ]);
    });

    it('event stores properties correctly', function (): void {
        $event = new BatchCreated($this->batch, $this->item);

        expect($event->batch)->toBe($this->batch);
        expect($event->inventoryable)->toBe($this->item);
    });

    it('get batch number returns correct value', function (): void {
        $event = new BatchCreated($this->batch, $this->item);

        expect($event->getBatchNumber())->toBe('BATCH-001');
    });

    it('get quantity received returns correct value', function (): void {
        $event = new BatchCreated($this->batch, $this->item);

        expect($event->getQuantityReceived())->toBe(100);
    });
});
