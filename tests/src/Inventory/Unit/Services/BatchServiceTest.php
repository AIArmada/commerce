<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\BatchCreated;
use AIArmada\Inventory\Events\BatchExpired;
use AIArmada\Inventory\Events\BatchRecalled;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryReservation;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Persistence\BatchService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;

describe('BatchService', function (): void {
    beforeEach(function (): void {
        $this->service = new BatchService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create();
    });

    it('create batch', function (): void {
        Event::fake();

        $batch = $this->service->createBatch(
            $this->item,
            'BATCH-001',
            $this->location->id,
            100,
            CarbonImmutable::now()->addMonths(6),
            CarbonImmutable::now()->subMonth()
        );

        expect($batch)->toBeInstanceOf(InventoryBatch::class);
        expect($batch->batch_number)->toBe('BATCH-001');
        expect($batch->quantity_on_hand)->toBe(100);
        expect($batch->status)->toBe(BatchStatus::Active->value);

        Event::assertDispatched(BatchCreated::class);
    });

    it('create batch throws for zero quantity', function (): void {
        $this->service->createBatch(
            $this->item,
            'BATCH-001',
            $this->location->id,
            0
        );
    })->throws(InvalidArgumentException::class);

    it('create batch throws for negative quantity', function (): void {
        $this->service->createBatch(
            $this->item,
            'BATCH-001',
            $this->location->id,
            -10
        );
    })->throws(InvalidArgumentException::class);

    it('find by batch number', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-FIND',
            'location_id' => $this->location->id,
        ]);

        $found = $this->service->findByBatchNumber('BATCH-FIND');

        expect($found)->not->toBeNull();
        expect($found->batch_number)->toBe('BATCH-FIND');
    });

    it('find by batch number returns null when not found', function (): void {
        $found = $this->service->findByBatchNumber('NONEXISTENT');

        expect($found)->toBeNull();
    });

    it('get batches for model', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-1',
            'location_id' => $this->location->id,
        ]);
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-2',
            'location_id' => $this->location->id,
        ]);

        $batches = $this->service->getBatchesForModel($this->item);

        expect($batches)->toHaveCount(2);
    });

    it('get allocatable batches', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-ACTIVE',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-QUARANTINED',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Quarantined->value,
            'quantity_on_hand' => 50,
        ]);

        $allocatable = $this->service->getAllocatableBatches($this->item);

        expect($allocatable)->toHaveCount(1);
    });

    it('get total available', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-T1',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
            'expires_at' => null,
        ]);
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-T2',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
            'expires_at' => null,
        ]);

        $total = $this->service->getTotalAvailable($this->item);

        // (100-20) + (50-10) = 80 + 40 = 120
        expect($total)->toBe(120);
    });

    it('quarantine batch', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-Q',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $this->service->quarantine($batch, 'Quality issue found');

        expect($result->status)->toBe(BatchStatus::Quarantined->value);
    });

    it('recall batches', function (): void {
        Event::fake();

        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-R1',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);
        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-R2',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $batches = new Collection([$batch1, $batch2]);
        $result = $this->service->recallBatches($batches, 'Safety recall');

        expect($result)->toHaveCount(2);
        expect($batch1->fresh()->status)->toBe(BatchStatus::Recalled->value);
        expect($batch2->fresh()->status)->toBe(BatchStatus::Recalled->value);

        Event::assertDispatched(BatchRecalled::class);
    });

    it('releases an entire checkout reservation when one allocated batch is quarantined', function (): void {
        $batch = $this->service->createBatch($this->item, 'QUARANTINE-GROUP', $this->location->id, 20);
        $batch->update(['quantity_reserved' => 5]);
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 20,
        ]);
        $level->incrementReserved(8);

        $group = InventoryReservation::create([
            'reference' => 'quarantine-group',
            'status' => InventoryReservation::STATE_RESERVED,
            'line_snapshot' => [],
            'ttl_seconds' => 600,
            'expires_at' => now()->addMinutes(10),
        ]);
        foreach ([['batch_id' => $batch->id, 'quantity' => 5], ['batch_id' => null, 'quantity' => 3]] as $data) {
            InventoryAllocation::create([
                ...$data,
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'level_id' => $level->id,
                'reservation_group_id' => $group->id,
                'cart_id' => $group->reference,
                'expires_at' => $group->expires_at,
            ]);
        }

        $this->service->quarantine($batch, 'Quality hold');

        expect($batch->fresh()->status)->toBe(BatchStatus::Quarantined->value)
            ->and($batch->fresh()->quantity_reserved)->toBe(0)
            ->and($level->fresh()->quantity_reserved)->toBe(0)
            ->and($group->fresh()->status)->toBe(InventoryReservation::STATE_RELEASED)
            ->and($group->allocations()->count())->toBe(0);
    });

    it('transfer batch', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-TRANSFER',
            'location_id' => $this->location->id,
            'quantity_reserved' => 0,
        ]);

        $newLocation = InventoryLocation::factory()->create();

        $result = $this->service->transferBatch($batch, $newLocation);

        expect($result->location_id)->toBe($newLocation->id);
    });

    it('transfer batch throws when reserved', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-RESERVED',
            'location_id' => $this->location->id,
            'quantity_reserved' => 10,
        ]);

        $newLocation = InventoryLocation::factory()->create();

        $this->service->transferBatch($batch, $newLocation);
    })->throws(InvalidArgumentException::class);

    it('split batch', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-ORIGINAL',
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $newBatch = $this->service->splitBatch($batch, 30, 'BATCH-SPLIT');

        expect($newBatch->batch_number)->toBe('BATCH-SPLIT');
        expect($newBatch->quantity_on_hand)->toBe(30);
        expect($batch->fresh()->quantity_on_hand)->toBe(70);
    });

    it('split batch throws for invalid quantity', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-ORIGINAL',
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $this->service->splitBatch($batch, 0, 'BATCH-SPLIT');
    })->throws(InvalidArgumentException::class);

    it('merge batches', function (): void {
        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-M1',
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);
        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-M2',
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
        ]);

        $batches = new Collection([$batch1, $batch2]);
        $merged = $this->service->mergeBatches($batches, 'BATCH-MERGED');

        expect($merged->batch_number)->toBe('BATCH-MERGED');
        expect($merged->quantity_on_hand)->toBe(80);
        expect(InventoryBatch::find($batch1->id))->toBeNull();
        expect(InventoryBatch::find($batch2->id))->toBeNull();
    });

    it('rejects stale splits and transfers after stock is reserved', function (): void {
        $batch = $this->service->createBatch($this->item, 'STALE-BATCH', $this->location->id, 100);
        $stale = $batch->fresh();
        $batch->update(['quantity_reserved' => 80]);

        expect(fn () => $this->service->splitBatch($stale, 30, 'INVALID-SPLIT'))
            ->toThrow(InvalidArgumentException::class);
        expect(fn () => $this->service->transferBatch($stale, InventoryLocation::factory()->create()))
            ->toThrow(InvalidArgumentException::class);

        expect($batch->fresh()->quantity_on_hand)->toBe(100)
            ->and($batch->fresh()->quantity_reserved)->toBe(80);
    });

    it('rejects merging stock from different locations or duplicated batches', function (): void {
        $first = $this->service->createBatch($this->item, 'MERGE-A', $this->location->id, 50);
        $second = $this->service->createBatch($this->item, 'MERGE-B', InventoryLocation::factory()->create()->id, 30);

        expect(fn () => $this->service->mergeBatches(new Collection([$first, $second]), 'INVALID-MERGE'))
            ->toThrow(InvalidArgumentException::class);
        expect(fn () => $this->service->mergeBatches(new Collection([$first, $first]), 'INVALID-DUPLICATE'))
            ->toThrow(InvalidArgumentException::class);

        expect($first->fresh()->quantity_on_hand)->toBe(50)
            ->and($second->fresh()->quantity_on_hand)->toBe(30);
    });

    it('does not convert recalled stock back to active through a merge', function (): void {
        $first = $this->service->createBatch($this->item, 'RECALLED-A', $this->location->id, 50);
        $second = $this->service->createBatch($this->item, 'ACTIVE-B', $this->location->id, 30);
        $first->update(['status' => BatchStatus::Recalled->value]);

        expect(fn () => $this->service->mergeBatches(new Collection([$first, $second]), 'UNSAFE-MERGE'))
            ->toThrow(InvalidArgumentException::class);

        expect($first->fresh()->status)->toBe(BatchStatus::Recalled->value);
    });

    it('rejects merging a batch with movement history without changing either source', function (): void {
        $first = $this->service->createBatch($this->item, 'HISTORY-A', $this->location->id, 50);
        $second = $this->service->createBatch($this->item, 'HISTORY-B', $this->location->id, 30);
        $movement = InventoryMovement::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'to_location_id' => $this->location->id,
            'batch_id' => $first->id,
            'quantity' => 50,
            'type' => MovementType::Receipt->value,
            'occurred_at' => now(),
        ]);

        expect(fn () => $this->service->mergeBatches(new Collection([$first, $second]), 'HISTORY-MERGED'))
            ->toThrow(InvalidArgumentException::class)
            ->and($first->fresh()->quantity_on_hand)->toBe(50)
            ->and($second->fresh()->quantity_on_hand)->toBe(30)
            ->and($movement->fresh()->batch_id)->toBe($first->id)
            ->and(InventoryBatch::query()->where('batch_number', 'HISTORY-MERGED')->exists())->toBeFalse();
        expect($first->delete())->toBeFalse();
    });

    it('merge batches throws for single batch', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-SINGLE',
            'location_id' => $this->location->id,
        ]);

        $batches = new Collection([$batch]);
        $this->service->mergeBatches($batches, 'BATCH-MERGED');
    })->throws(InvalidArgumentException::class);

    it('get expiring batches', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-EXPIRING',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'expires_at' => CarbonImmutable::now()->addDays(15),
        ]);
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-SAFE',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'expires_at' => CarbonImmutable::now()->addDays(60),
        ]);

        $expiring = $this->service->getExpiringBatches(30);

        expect($expiring)->toHaveCount(1);
        expect($expiring->first()->batch_number)->toBe('BATCH-EXPIRING');
    });

    it('scope with status', function (): void {
        $activeBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-ACTIVE',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $quarantineBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-QUARANTINE',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Quarantined->value,
        ]);

        $activeBatches = InventoryBatch::withStatus(BatchStatus::Active)->get();
        $quarantineBatches = InventoryBatch::withStatus(BatchStatus::Quarantined)->get();

        expect($activeBatches)->toHaveCount(1);
        expect($activeBatches->first()->id)->toBe($activeBatch->id);

        expect($quarantineBatches)->toHaveCount(1);
        expect($quarantineBatches->first()->id)->toBe($quarantineBatch->id);
    });

    it('process expired batches', function (): void {
        Event::fake();

        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-EXPIRED',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'expires_at' => CarbonImmutable::now()->subDay(),
        ]);

        $count = $this->service->processExpiredBatches();

        expect($count)->toBe(1);
        Event::assertDispatched(BatchExpired::class);
    });
});
