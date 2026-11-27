<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use AIArmada\Inventory\Features\Traceability\Persistence\SerialService;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Sold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * @return array{
 *     ownerA: InventoryItem,
 *     ownerB: InventoryItem,
 *     sku: InventoryItem,
 *     locationA: InventoryLocation,
 *     locationB: InventoryLocation,
 *     locationGlobal: InventoryLocation,
 *     setOwnerContext: Closure(?Model): void,
 *     service: SerialService
 * }
 */
function makeOwnerScopedSerialServiceFixture(): array
{
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $ownerB = InventoryItem::create(['name' => 'Owner B']);

    $setOwnerContext = static function (?Model $owner): void {
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
    };

    $sku = InventoryItem::create(['name' => 'SKU']);

    $setOwnerContext($ownerA);

    $locationA = InventoryLocation::factory()->create([
        'code' => 'LOC-A',
        'is_active' => true,
    ]);

    $setOwnerContext($ownerB);

    $locationB = InventoryLocation::factory()->create([
        'code' => 'LOC-B',
        'is_active' => true,
    ]);

    $setOwnerContext(null);

    $locationGlobal = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create([
        'code' => 'LOC-G',
        'is_active' => true,
    ]));

    $setOwnerContext($ownerA);

    $service = new SerialService;

    return [
        'ownerA' => $ownerA,
        'ownerB' => $ownerB,
        'sku' => $sku,
        'locationA' => $locationA,
        'locationB' => $locationB,
        'locationGlobal' => $locationGlobal,
        'setOwnerContext' => $setOwnerContext,
        'service' => $service,
    ];
}

it('scopes SerialService::findBySerialNumber to current owner (and global when enabled)', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $ownerA = $fixture['ownerA'];
    $ownerB = $fixture['ownerB'];
    $locationA = $fixture['locationA'];
    $locationB = $fixture['locationB'];
    $locationGlobal = $fixture['locationGlobal'];
    $setOwnerContext = $fixture['setOwnerContext'];
    $service = $fixture['service'];

    $setOwnerContext($ownerA);

    InventorySerial::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'serial_number' => 'SN-A',
        'location_id' => $locationA->id,
        'status' => SerialStatus::normalize(Available::class),
        'condition' => SerialCondition::New->value,
    ]);

    $setOwnerContext($ownerB);

    InventorySerial::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'serial_number' => 'SN-B',
        'location_id' => $locationB->id,
        'status' => SerialStatus::normalize(Available::class),
        'condition' => SerialCondition::New->value,
    ]);

    $setOwnerContext($ownerA);

    OwnerContext::withOwner(null, function () use ($sku, $locationGlobal): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $sku->getMorphClass(),
            'inventoryable_id' => $sku->getKey(),
            'serial_number' => 'SN-G',
            'location_id' => $locationGlobal->id,
            'status' => SerialStatus::normalize(Available::class),
            'condition' => SerialCondition::New->value,
        ]);
    });

    expect($service->findBySerialNumber('SN-A'))
        ->not->toBeNull();

    expect($service->findBySerialNumber('SN-B'))
        ->toBeNull();

    expect($service->findBySerialNumber('SN-G'))
        ->not->toBeNull();
});

it('can exclude global serials when include_global is false', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $locationGlobal = $fixture['locationGlobal'];
    $service = $fixture['service'];

    OwnerContext::withOwner(null, function () use ($sku, $locationGlobal): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $sku->getMorphClass(),
            'inventoryable_id' => $sku->getKey(),
            'serial_number' => 'SN-G2',
            'location_id' => $locationGlobal->id,
            'status' => SerialStatus::normalize(Available::class),
            'condition' => SerialCondition::New->value,
        ]);
    });

    config()->set('inventory.owner.include_global', false);

    expect($service->findBySerialNumber('SN-G2'))
        ->toBeNull();
});

it('prevents registering serials at locations outside current owner scope', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $locationB = $fixture['locationB'];
    $service = $fixture['service'];

    expect(fn () => $service->register(
        $sku,
        'SN-X',
        $locationB->id
    ))->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');
});

it('prevents transferring serials to locations outside current owner scope', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $locationA = $fixture['locationA'];
    $locationB = $fixture['locationB'];
    $service = $fixture['service'];

    $serial = InventorySerial::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'serial_number' => 'SN-T',
        'location_id' => $locationA->id,
        'status' => SerialStatus::normalize(Available::class),
        'condition' => SerialCondition::New->value,
    ]);

    expect(fn () => $service->transfer($serial, $locationB->id))
        ->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');
});

it('allows finding shipped serials by serial number using history scope', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $locationA = $fixture['locationA'];
    $service = $fixture['service'];

    $serial = $service->register(
        $sku,
        'SN-SHIPPED',
        $locationA->id,
        null,
        SerialCondition::New
    );

    $serial->update(['status' => SerialStatus::normalize(Sold::class)]);

    $service->ship($serial->fresh(), 'TRACK-1');

    $found = $service->findBySerialNumber('SN-SHIPPED');

    expect($found)->not->toBeNull();
});

it('does not let a tenant register a serial at a visible global location', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();

    expect(fn () => $fixture['service']->register($fixture['sku'], 'SN-GLOBAL-WRITE', $fixture['locationGlobal']->id))
        ->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');
});

it('does not let a tenant mutate a visible global serial', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();

    $serial = OwnerContext::withOwner(null, fn (): InventorySerial => $fixture['service']->register(
        $fixture['sku'],
        'SN-GLOBAL-RESERVE',
        $fixture['locationGlobal']->id,
    ));

    expect($fixture['service']->findBySerialNumber('SN-GLOBAL-RESERVE'))->not->toBeNull();

    expect(fn () => $fixture['service']->reserve($serial))
        ->toThrow(ModelNotFoundException::class);

    expect($serial->fresh()->status)->toBeInstanceOf(Available::class);
});

it('enforces serial uniqueness for explicit global records on the fresh schema', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();

    OwnerContext::withOwner(null, function () use ($fixture): void {
        $fixture['service']->register($fixture['sku'], 'SN-UNIQUE-GLOBAL', $fixture['locationGlobal']->id);

        expect(fn () => $fixture['service']->register($fixture['sku'], 'SN-UNIQUE-GLOBAL', $fixture['locationGlobal']->id))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

it('allows different owners to register the same serial identifier', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();

    $first = $fixture['service']->register($fixture['sku'], 'SN-SHARED', $fixture['locationA']->id);
    ($fixture['setOwnerContext'])($fixture['ownerB']);
    $second = $fixture['service']->register($fixture['sku'], 'SN-SHARED', $fixture['locationB']->id);

    expect($second->id)->not->toBe($first->id)
        ->and($second->owner_id)->toBe($fixture['ownerB']->getKey());
});
