<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Persistence\BatchAllocationService;
use AIArmada\Inventory\Features\Traceability\Persistence\BatchService;
use AIArmada\Inventory\Features\Traceability\Queries\BatchLookupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

function setBatchServiceOwnerResolver(?Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });
}

/**
 * @return array{
 *     ownerA: InventoryItem,
 *     ownerB: InventoryItem,
 *     sku: InventoryItem,
 *     locationA: InventoryLocation,
 *     locationB: InventoryLocation,
 *     locationGlobal: InventoryLocation,
 *     service: BatchService
 * }
 */
function makeOwnerScopedBatchServiceFixture(): array
{
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $ownerB = InventoryItem::create(['name' => 'Owner B']);

    setBatchServiceOwnerResolver($ownerA);

    $sku = InventoryItem::create(['name' => 'SKU']);

    $locationA = InventoryLocation::factory()->create([
        'code' => 'LOC-A',
    ]);

    setBatchServiceOwnerResolver($ownerB);
    $locationB = InventoryLocation::factory()->create([
        'code' => 'LOC-B',
    ]);

    setBatchServiceOwnerResolver(null);
    $locationGlobal = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create([
        'code' => 'LOC-G',
    ]));

    setBatchServiceOwnerResolver($ownerA);

    $service = new BatchService;

    return [
        'ownerA' => $ownerA,
        'ownerB' => $ownerB,
        'sku' => $sku,
        'locationA' => $locationA,
        'locationB' => $locationB,
        'locationGlobal' => $locationGlobal,
        'service' => $service,
    ];
}

it('scopes BatchLookupService::findByBatchNumber to current owner (and global when enabled)', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();
    $sku = $fixture['sku'];
    $locationA = $fixture['locationA'];
    $locationB = $fixture['locationB'];
    $locationGlobal = $fixture['locationGlobal'];

    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-A',
        'location_id' => $locationA->id,
    ]);

    setBatchServiceOwnerResolver($fixture['ownerB']);
    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-B',
        'location_id' => $locationB->id,
    ]);

    setBatchServiceOwnerResolver($fixture['ownerA']);

    setBatchServiceOwnerResolver(null);
    OwnerContext::withOwner(null, function () use ($sku, $locationGlobal): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $sku->getMorphClass(),
            'inventoryable_id' => $sku->getKey(),
            'batch_number' => 'BATCH-G',
            'location_id' => $locationGlobal->id,
        ]);
    });

    setBatchServiceOwnerResolver($fixture['ownerA']);

    expect(app(BatchLookupService::class)->findByBatchNumber('BATCH-A'))
        ->not->toBeNull();

    expect(app(BatchLookupService::class)->findByBatchNumber('BATCH-B'))
        ->toBeNull();

    expect(app(BatchLookupService::class)->findByBatchNumber('BATCH-G'))
        ->not->toBeNull();
});

it('can exclude global batches when include_global is false', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();
    $sku = $fixture['sku'];
    $locationGlobal = $fixture['locationGlobal'];

    config()->set('inventory.owner.include_global', false);

    setBatchServiceOwnerResolver(null);
    OwnerContext::withOwner(null, function () use ($sku, $locationGlobal): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $sku->getMorphClass(),
            'inventoryable_id' => $sku->getKey(),
            'batch_number' => 'BATCH-G2',
            'location_id' => $locationGlobal->id,
        ]);
    });

    setBatchServiceOwnerResolver($fixture['ownerA']);

    expect(app(BatchLookupService::class)->findByBatchNumber('BATCH-G2'))
        ->toBeNull();
});

it('scopes BatchLookupService::getBatchesForModel to current owner (and global when enabled)', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();
    $sku = $fixture['sku'];
    $locationA = $fixture['locationA'];
    $locationB = $fixture['locationB'];
    $locationGlobal = $fixture['locationGlobal'];

    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-A1',
        'location_id' => $locationA->id,
    ]);

    setBatchServiceOwnerResolver($fixture['ownerB']);
    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-B1',
        'location_id' => $locationB->id,
    ]);

    setBatchServiceOwnerResolver($fixture['ownerA']);

    setBatchServiceOwnerResolver(null);
    OwnerContext::withOwner(null, function () use ($sku, $locationGlobal): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $sku->getMorphClass(),
            'inventoryable_id' => $sku->getKey(),
            'batch_number' => 'BATCH-G1',
            'location_id' => $locationGlobal->id,
        ]);
    });

    setBatchServiceOwnerResolver($fixture['ownerA']);

    $batches = app(BatchLookupService::class)->getBatchesForModel($sku);

    expect($batches->pluck('batch_number')->all())
        ->toContain('BATCH-A1', 'BATCH-G1')
        ->not->toContain('BATCH-B1');
});

it('prevents creating batches at locations outside current owner scope', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();
    $sku = $fixture['sku'];
    $locationB = $fixture['locationB'];
    $service = $fixture['service'];

    expect(fn () => $service->createBatch(
        $sku,
        'BATCH-X',
        $locationB->id,
        10
    ))->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');
});

it('does not allow a tenant to create stock in a visible global location', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();

    expect(fn () => $fixture['service']->createBatch($fixture['sku'], 'GLOBAL-WRITE', $fixture['locationGlobal']->id, 10))
        ->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');
});

it('rejects direct batch creation in a tenant-visible global location', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();

    expect(fn () => InventoryBatch::factory()->create([
        'inventoryable_type' => $fixture['sku']->getMorphClass(),
        'inventoryable_id' => $fixture['sku']->getKey(),
        'location_id' => $fixture['locationGlobal']->id,
        'batch_number' => 'DIRECT-GLOBAL-WRITE',
    ]))->toThrow(AuthorizationException::class);
});

it('excludes visible global batches from tenant allocation plans and availability', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();
    $sku = $fixture['sku'];

    $owned = $fixture['service']->createBatch($sku, 'OWNED-ALLOC', $fixture['locationA']->id, 5);

    setBatchServiceOwnerResolver(null);
    $global = OwnerContext::withOwner(null, fn (): InventoryBatch => app(BatchService::class)
        ->createBatch($sku, 'GLOBAL-ALLOC', $fixture['locationGlobal']->id, 100));

    setBatchServiceOwnerResolver($fixture['ownerA']);
    $lookup = app(BatchLookupService::class);
    $allocation = app(BatchAllocationService::class);

    expect($lookup->findByBatchNumber('GLOBAL-ALLOC')?->id)->toBe($global->id)
        ->and($lookup->getTotalAvailable($sku))->toBe(5)
        ->and($allocation->checkAvailability($sku, 6))->toBeFalse()
        ->and($allocation->getFefoAllocationPlan($sku, 6)['available'])->toBeFalse()
        ->and($allocation->allocateFefo($sku, 5)[0]['batch']->id)->toBe($owned->id)
        ->and(fn () => $allocation->allocateFifo($sku, 1, $fixture['locationGlobal']->id))
        ->toThrow(InvalidArgumentException::class);
});
