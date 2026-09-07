<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Events\BatchRecalled;
use AIArmada\Inventory\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Collection;

describe('BatchRecalled', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-003',
            'quantity_on_hand' => 25,
        ]);
        $this->batches = new Collection([$batch]);
    });

    it('event stores properties correctly', function (): void {
        $event = new BatchRecalled($this->batches, 'Safety recall', $this->item);

        expect($event->batches)->toBe($this->batches);
        expect($event->reason)->toBe('Safety recall');
        expect($event->inventoryable)->toBe($this->item);
    });

    it('get total quantity affected returns correct value', function (): void {
        $event = new BatchRecalled($this->batches, 'Safety recall', $this->item);

        expect($event->getTotalQuantityAffected())->toBe(25);
    });

    it('get batch numbers returns correct value', function (): void {
        $event = new BatchRecalled($this->batches, 'Safety recall', $this->item);

        expect($event->getBatchNumbers())->toBe(['BATCH-003']);
    });
});
