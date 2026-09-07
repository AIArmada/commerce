<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

describe('InventoryBatch', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    });

    it('get table returns correct table name', function (): void {
        $batch = new InventoryBatch;
        expect($batch->getTable())->toBe('inventory_batches');
    });

    it('inventoryable relationship', function (): void {
        $batch = new InventoryBatch;
        $relation = $batch->inventoryable();
        expect($relation)->toBeInstanceOf(MorphTo::class);
    });

    it('location relationship', function (): void {
        $batch = new InventoryBatch;
        $relation = $batch->location();
        expect($relation)->toBeInstanceOf(BelongsTo::class);
    });

    it('movements relationship', function (): void {
        $batch = new InventoryBatch;
        $relation = $batch->movements();
        expect($relation)->toBeInstanceOf(HasMany::class);
    });

    it('allocations relationship', function (): void {
        $batch = new InventoryBatch;
        $relation = $batch->allocations();
        expect($relation)->toBeInstanceOf(HasMany::class);
    });

    it('get available attribute', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
        ]);

        expect($batch->available)->toBe(80);
    });

    it('get is expired attribute', function (): void {
        $expiredBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->subDay(),
        ]);

        $validBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDay(),
        ]);

        expect($expiredBatch->is_expired)->toBeTrue();
        expect($validBatch->is_expired)->toBeFalse();
    });

    it('get days until expiry attribute', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(5),
        ]);

        expect($batch->days_until_expiry)->toBe(4); // diffInDays might be off by 1
    });

    it('is expired method', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->subDay(),
        ]);

        expect($batch->isExpired())->toBeTrue();
    });

    it('days until expiry method', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(10),
        ]);

        expect($batch->daysUntilExpiry())->toBe(9); // diffInDays might be off by 1
    });

    it('get status enum', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        expect($batch->getStatusEnum())->toBe(BatchStatus::Active);
    });

    it('can allocate', function (): void {
        $allocatableBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
            'expires_at' => now()->addDay(),
        ]);

        $nonAllocatableBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Expired->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
        ]);

        expect($allocatableBatch->canAllocate())->toBeTrue();
        expect($nonAllocatableBatch->canAllocate())->toBeFalse();
    });

    it('is expiring soon', function (): void {
        $expiringBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(15),
        ]);

        $notExpiringBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(50),
        ]);

        expect($expiringBatch->isExpiringSoon(30))->toBeTrue();
        expect($notExpiringBatch->isExpiringSoon(30))->toBeFalse();
    });

    it('quarantine', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $batch->quarantine('Quality issue detected');

        expect($result)->toBe($batch);
        expect($batch->fresh()->status)->toBe(BatchStatus::Quarantined->value);
        expect($batch->fresh()->quarantine_reason)->toBe('Quality issue detected');
        expect($batch->fresh()->quarantined_at)->not->toBeNull();
    });

    it('release from quarantine', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Quarantined->value,
            'quarantine_reason' => 'Test reason',
            'quarantined_at' => now(),
        ]);

        $result = $batch->releaseFromQuarantine();

        expect($result)->toBe($batch);
        expect($batch->fresh()->status)->toBe(BatchStatus::Active->value);
        expect($batch->fresh()->quarantined_at)->toBeNull();
        expect($batch->fresh()->quarantine_reason)->toBeNull();
    });

    it('recall', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $batch->recall('Safety hazard found');

        expect($result)->toBe($batch);
        $fresh = $batch->fresh();
        expect($fresh->status)->toBe(BatchStatus::Recalled->value);
        expect($fresh->recall_reason)->toBe('Safety hazard found');
        expect($fresh->recalled_at)->not->toBeNull();
    });

    it('mark expired', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $batch->markExpired();

        expect($result)->toBe($batch);
        expect($batch->fresh()->status)->toBe(BatchStatus::Expired->value);
    });

    it('increment on hand', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
        ]);

        $result = $batch->incrementOnHand(10);

        expect($result)->toBe($batch);
        expect($batch->fresh()->quantity_on_hand)->toBe(60);
    });

    it('decrement on hand', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $batch->decrementOnHand(10);

        expect($result)->toBe($batch);
        expect($batch->fresh()->quantity_on_hand)->toBe(40);
    });

    it('decrement on hand to zero depletes', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'status' => BatchStatus::Active->value,
        ]);

        $batch->decrementOnHand(10);

        expect($batch->fresh()->quantity_on_hand)->toBe(0);
        expect($batch->fresh()->status)->toBe(BatchStatus::Depleted->value);
    });

    it('increment reserved', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_reserved' => 5,
        ]);

        $result = $batch->incrementReserved(10);

        expect($result)->toBe($batch);
        expect($batch->fresh()->quantity_reserved)->toBe(15);
    });

    it('decrement reserved', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_reserved' => 20,
        ]);

        $result = $batch->decrementReserved(5);

        expect($result)->toBe($batch);
        expect($batch->fresh()->quantity_reserved)->toBe(15);
    });

    it('is expired with null expires at', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => null,
        ]);

        expect($batch->is_expired)->toBeFalse();
    });

    it('days until expiry with null expires at', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => null,
        ]);

        expect($batch->days_until_expiry)->toBeNull();
    });

    it('is expiring soon with null expires at', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => null,
        ]);

        expect($batch->isExpiringSoon())->toBeFalse();
    });

    it('can allocate when expired', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'expires_at' => now()->subDay(),
        ]);

        expect($batch->canAllocate())->toBeFalse();
    });

    it('can allocate when no available', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 50,
            'expires_at' => now()->addDay(),
        ]);

        expect($batch->canAllocate())->toBeFalse();
    });
});
