<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Resources\CustomerResource;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('inventory and customers filament navigation badges are owner-scoped (no cross-tenant aggregation)', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', false);

    config()->set('customers.owner.enabled', true);
    config()->set('customers.owner.include_global', false);

    $ownerA = \App\Models\User::factory()->create();
    $ownerB = \App\Models\User::factory()->create();

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        $locationA = InventoryLocation::create([
            'name' => 'Warehouse A',
            'code' => 'WH-A',
            'address' => '123 A St',
            'is_active' => true,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]);

        InventoryLevel::create([
            'inventoryable_type' => 'product',
            'inventoryable_id' => 'prod-a-001',
            'location_id' => $locationA->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 2,
            'reorder_point' => 15, // Low stock: 10 - 2 <= 15
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]);

        Customer::create([
            'first_name' => 'Customer',
            'last_name' => 'A',
            'email' => 'customer-a@example.com',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($ownerB): void {
        $locationB = InventoryLocation::create([
            'name' => 'Warehouse B',
            'code' => 'WH-B',
            'address' => '456 B St',
            'is_active' => true,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

        InventoryLevel::create([
            'inventoryable_type' => 'product',
            'inventoryable_id' => 'prod-b-001',
            'location_id' => $locationB->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 4,
            'reorder_point' => 25, // Low stock: 20 - 4 <= 25
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

        InventoryLevel::create([
            'inventoryable_type' => 'product',
            'inventoryable_id' => 'prod-b-002',
            'location_id' => $locationB->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 6,
            'reorder_point' => 35, // Low stock: 30 - 6 <= 35
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

        Customer::create([
            'first_name' => 'Customer',
            'last_name' => 'B1',
            'email' => 'customer-b1@example.com',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

        Customer::create([
            'first_name' => 'Customer',
            'last_name' => 'B2',
            'email' => 'customer-b2@example.com',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        expect(InventoryLocationResource::getNavigationBadge())->toBe('1');
        expect(InventoryLevelResource::getNavigationBadge())->toBe('1');
        expect(CustomerResource::getNavigationBadge())->toBe('1');
    });

    OwnerContext::withOwner($ownerB, function (): void {
        expect(InventoryLocationResource::getNavigationBadge())->toBe('1');
        expect(InventoryLevelResource::getNavigationBadge())->toBe('2');
        expect(CustomerResource::getNavigationBadge())->toBe('2');
    });
});
