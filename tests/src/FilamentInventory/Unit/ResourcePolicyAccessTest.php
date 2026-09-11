<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentInventory\Fixtures\TestOwner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use AIArmada\FilamentInventory\Resources\InventoryMovementResource;
use AIArmada\FilamentInventory\Resources\InventorySerialResource;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventorySerial;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('filament_inventory_test_owners');

    Schema::create('filament_inventory_test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', false);
    config()->set('inventory.owner.auto_assign_on_create', true);
});

it('denies cross-owner location access', function (): void {
    $ownerA = TestOwner::create(['name' => 'Location Owner A']);
    $ownerB = TestOwner::create(['name' => 'Location Owner B']);
    $locationB = OwnerContext::withOwner(
        $ownerB,
        fn (): InventoryLocation => InventoryLocation::factory()->create(),
    );

    OwnerContext::withOwner($ownerA, function () use ($locationB): void {
        expect(InventoryLocationResource::canView($locationB))->toBeFalse()
            ->and(InventoryLocationResource::canEdit($locationB))->toBeFalse()
            ->and(InventoryLocationResource::canDelete($locationB))->toBeFalse();
    });
});

it('denies cross-owner batch access', function (): void {
    $ownerA = TestOwner::create(['name' => 'Batch Owner A']);
    $ownerB = TestOwner::create(['name' => 'Batch Owner B']);
    $locationB = OwnerContext::withOwner(
        $ownerB,
        fn (): InventoryLocation => InventoryLocation::factory()->create(),
    );
    $batchB = OwnerContext::withOwner(
        $ownerB,
        fn (): InventoryBatch => InventoryBatch::factory()->forLocation($locationB)->create(),
    );

    OwnerContext::withOwner($ownerA, function () use ($batchB): void {
        expect(InventoryBatchResource::canView($batchB))->toBeFalse()
            ->and(InventoryBatchResource::canEdit($batchB))->toBeFalse()
            ->and(InventoryBatchResource::canDelete($batchB))->toBeFalse();
    });
});

it('denies cross-owner serial access', function (): void {
    $ownerA = TestOwner::create(['name' => 'Serial Owner A']);
    $ownerB = TestOwner::create(['name' => 'Serial Owner B']);
    $locationB = OwnerContext::withOwner(
        $ownerB,
        fn (): InventoryLocation => InventoryLocation::factory()->create(),
    );
    $serialB = OwnerContext::withOwner(
        $ownerB,
        fn (): InventorySerial => InventorySerial::factory()->forLocation($locationB)->create(),
    );

    OwnerContext::withOwner($ownerA, function () use ($serialB): void {
        expect(InventorySerialResource::canView($serialB))->toBeFalse()
            ->and(InventorySerialResource::canEdit($serialB))->toBeFalse()
            ->and(InventorySerialResource::canDelete($serialB))->toBeFalse();
    });
});

it('denies cross-owner movement access', function (): void {
    $ownerA = TestOwner::create(['name' => 'Movement Owner A']);
    $ownerB = TestOwner::create(['name' => 'Movement Owner B']);
    $locationB = OwnerContext::withOwner(
        $ownerB,
        fn (): InventoryLocation => InventoryLocation::factory()->create(),
    );
    $movementB = OwnerContext::withOwner(
        $ownerB,
        fn (): InventoryMovement => InventoryMovement::factory()
            ->receipt()
            ->toLocation($locationB)
            ->create(),
    );

    OwnerContext::withOwner($ownerA, function () use ($movementB): void {
        expect(InventoryMovementResource::canView($movementB))->toBeFalse()
            ->and(InventoryMovementResource::canEdit($movementB))->toBeFalse()
            ->and(InventoryMovementResource::canDelete($movementB))->toBeFalse();
    });
});
