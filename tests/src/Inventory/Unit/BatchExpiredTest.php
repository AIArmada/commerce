<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Events\BatchExpired;
use AIArmada\Inventory\Models\InventoryBatch;

describe('BatchExpired', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-002',
            'quantity_on_hand' => 50,
        ]);
    });

    it('event stores properties correctly', function (): void {
        $event = new BatchExpired($this->batch, $this->item);

        expect($event->batch)->toBe($this->batch);
        expect($event->inventoryable)->toBe($this->item);
    });

    it('get batch number returns correct value', function (): void {
        $event = new BatchExpired($this->batch, $this->item);

        expect($event->getBatchNumber())->toBe('BATCH-002');
    });

    it('get remaining quantity returns correct value', function (): void {
        $event = new BatchExpired($this->batch, $this->item);

        expect($event->getRemainingQuantity())->toBe(50);
    });
});
