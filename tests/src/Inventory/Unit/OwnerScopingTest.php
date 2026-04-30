<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Exceptions\InsufficientStockException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function setInventoryOwnerResolver(?Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('scopes availability to current owner and global locations when enabled', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $ownerB = InventoryItem::create(['name' => 'Owner B']);

    setInventoryOwnerResolver($ownerA);

    app()->forgetInstance(InventoryService::class);

    $inventoryable = InventoryItem::create(['name' => 'SKU']);

    $locationA = InventoryLocation::factory()->create([
        'code' => 'OWN-A',
    ]);

    setInventoryOwnerResolver($ownerB);
    $locationB = InventoryLocation::factory()->create([
        'code' => 'OWN-B',
    ]);

    setInventoryOwnerResolver(null);
    $globalLocation = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create([
        'code' => 'GLOBAL',
    ]));

    setInventoryOwnerResolver($ownerA);

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($locationA)
        ->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

    setInventoryOwnerResolver($ownerB);
    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($locationB)
        ->create([
            'quantity_on_hand' => 7,
            'quantity_reserved' => 0,
        ]);

    setInventoryOwnerResolver(null);
    OwnerContext::withOwner(null, function () use ($inventoryable, $globalLocation): void {
        InventoryLevel::factory()
            ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
            ->forLocation($globalLocation)
            ->create([
                'quantity_on_hand' => 11,
                'quantity_reserved' => 0,
            ]);
    });

    setInventoryOwnerResolver($ownerA);

    $service = app(InventoryService::class);

    expect($service->getTotalAvailable($inventoryable))->toBe(16);

    expect($service->getAvailability($inventoryable))->toMatchArray([
        $locationA->id => 5,
        $globalLocation->id => 11,
    ]);
});

it('excludes global inventory when include_global is false', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', false);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);

    setInventoryOwnerResolver($ownerA);

    app()->forgetInstance(InventoryService::class);

    $inventoryable = InventoryItem::create(['name' => 'SKU']);

    $locationA = InventoryLocation::factory()->create([
        'code' => 'OWN-A-ONLY',
    ]);

    setInventoryOwnerResolver(null);
    $globalLocation = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create([
        'code' => 'GLOBAL-ONLY',
    ]));

    setInventoryOwnerResolver($ownerA);

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($locationA)
        ->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

    setInventoryOwnerResolver(null);
    OwnerContext::withOwner(null, function () use ($inventoryable, $globalLocation): void {
        InventoryLevel::factory()
            ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
            ->forLocation($globalLocation)
            ->create([
                'quantity_on_hand' => 11,
                'quantity_reserved' => 0,
            ]);
    });

    setInventoryOwnerResolver($ownerA);

    $service = app(InventoryService::class);

    expect($service->getTotalAvailable($inventoryable))->toBe(5);

    expect($service->getAvailability($inventoryable))->toMatchArray([
        $locationA->id => 5,
    ]);
});

it('blocks mutations against locations outside current owner scope', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $ownerB = InventoryItem::create(['name' => 'Owner B']);

    setInventoryOwnerResolver($ownerA);

    app()->forgetInstance(InventoryService::class);

    $inventoryable = InventoryItem::create(['name' => 'SKU']);

    setInventoryOwnerResolver($ownerB);
    $locationB = InventoryLocation::factory()->create([
        'code' => 'OWN-B-MUT',
    ]);

    setInventoryOwnerResolver($ownerA);

    $service = app(InventoryService::class);

    expect(fn () => $service->receive($inventoryable, $locationB->id, 1))
        ->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');

    setInventoryOwnerResolver($ownerB);
    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($locationB)
        ->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

    setInventoryOwnerResolver($ownerA);

    expect(fn () => $service->ship($inventoryable, $locationB->id, 1))
        ->toThrow(InsufficientStockException::class);
});

it('treats a null resolved owner as global-only (never owner_type-only corrupt rows)', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    setInventoryOwnerResolver(null);

    app()->forgetInstance(InventoryService::class);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $inventoryable = InventoryItem::create(['name' => 'SKU']);

    $globalLocation = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create([
        'code' => 'GLOBAL-NULL-OWNER',
    ]));

    $corruptLocationId = (string) Str::uuid();

    DB::table(config('inventory.database.tables.locations', 'inventory_locations'))
        ->insert([
            'id' => $corruptLocationId,
            'owner_scope' => 'global',
            'name' => 'Corrupt Null Owner',
            'code' => 'CORRUPT-NULL-OWNER',
            'is_active' => true,
            'priority' => 0,
            'depth' => 0,
            'is_hazmat_certified' => false,
            'current_utilization' => 0,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    /** @var InventoryLocation $corruptLocation */
    $corruptLocation = InventoryLocation::withoutGlobalScopes()->findOrFail($corruptLocationId);

    OwnerContext::withOwner(null, function () use ($inventoryable, $globalLocation): void {
        InventoryLevel::factory()
            ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
            ->forLocation($globalLocation)
            ->create([
                'quantity_on_hand' => 11,
                'quantity_reserved' => 0,
            ]);
    });

    DB::table(config('inventory.database.tables.levels', 'inventory_levels'))
        ->insert([
            'id' => (string) Str::uuid(),
            'inventoryable_type' => $inventoryable->getMorphClass(),
            'inventoryable_id' => $inventoryable->getKey(),
            'location_id' => $corruptLocation->id,
            'quantity_on_hand' => 7,
            'quantity_reserved' => 0,
            'unit_of_measure' => 'each',
            'unit_conversion_factor' => 1,
            'owner_type' => null,
            'owner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $service = app(InventoryService::class);

    expect(fn () => $service->getTotalAvailable($inventoryable))
        ->toThrow(RuntimeException::class, 'AIArmada\Inventory\Models\InventoryLocation requires an owner context or explicit global context.');

    OwnerContext::withOwner(null, function () use ($service, $inventoryable, $globalLocation): void {
        expect($service->getTotalAvailable($inventoryable))->toBe(11);
        expect($service->getAvailability($inventoryable))->toMatchArray([
            $globalLocation->id => 11,
        ]);
    });
});
