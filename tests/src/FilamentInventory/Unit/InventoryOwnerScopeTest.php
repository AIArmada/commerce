<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentInventory\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\FilamentInventory\Fixtures\TestOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('filament_inventory_test_owners');

    Schema::create('filament_inventory_test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('inventory.owner.enabled', false);
    config()->set('inventory.owner.include_global', true);

});

it('does not scope queries when owner scoping is disabled', function (): void {
    $global = InventoryLocation::factory()->create();

    config()->set('inventory.owner.enabled', false);

    expect(InventoryOwnerScope::isEnabled())->toBeFalse();
    expect(InventoryOwnerScope::resolveOwner())->toBeNull();
    expect(InventoryOwnerScope::cacheKeySuffix())->toBe('owner=disabled');

    $count = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
        ->whereKey($global->id)
        ->count();

    expect($count)->toBe(1);
});

it('scopes to global-only when enabled but no resolver is bound', function (): void {
    config()->set('inventory.owner.enabled', true);

    $global = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create());

    $ownedOwner = TestOwner::create(['name' => 'Synthetic Owner']);
    $owned = OwnerContext::withOwner($ownedOwner, fn (): InventoryLocation => InventoryLocation::factory()->create());

    OwnerContext::withOwner(null, function () use ($global, $owned): void {
        expect(InventoryOwnerScope::resolveOwner())->toBeNull();

        $query = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());

        expect($query->whereKey($global->id)->exists())->toBeTrue();
        expect($query->whereKey($owned->id)->exists())->toBeFalse();

        expect(InventoryOwnerScope::cacheKeySuffix())
            ->toBe('owner=null|includeGlobal=1');
    });
});

it('scopes to the resolved owner and optionally includes global rows', function (): void {
    $ownerA = TestOwner::create(['name' => 'Owner A']);
    $ownerB = TestOwner::create(['name' => 'Owner B']);

    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', false);

    $global = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create());
    $locationA = OwnerContext::withOwner($ownerA, fn (): InventoryLocation => InventoryLocation::factory()->create());
    $locationB = OwnerContext::withOwner($ownerB, fn (): InventoryLocation => InventoryLocation::factory()->create());

    app()->bind(OwnerResolverInterface::class, fn () => new TestOwnerResolver($ownerA));

    $ownerOnlyQuery = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());

    expect($ownerOnlyQuery->whereKey($locationA->id)->exists())->toBeTrue();
    expect($ownerOnlyQuery->whereKey($global->id)->exists())->toBeFalse();
    expect($ownerOnlyQuery->whereKey($locationB->id)->exists())->toBeFalse();

    expect(InventoryOwnerScope::cacheKeySuffix())
        ->toBe('owner=' . $ownerA->getMorphClass() . ':' . $ownerA->getKey() . '|includeGlobal=0');

    config()->set('inventory.owner.include_global', true);

    expect(InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
        ->whereKey($locationA->id)
        ->exists())->toBeTrue();

    expect(InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
        ->whereKey($global->id)
        ->exists())->toBeTrue();

    expect(InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
        ->whereKey($locationB->id)
        ->exists())->toBeFalse();
});
