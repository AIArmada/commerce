<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Models\Shipment;
use Filament\Support\Icons\Heroicon;

uses(TestCase::class);

// ============================================
// ShipmentResource Tests
// ============================================

it('has correct navigation icon', function (): void {
    expect(ShipmentResource::getNavigationIcon())->toBe(Heroicon::OutlinedTruck);
});

it('has correct navigation group', function (): void {
    expect(ShipmentResource::getNavigationGroup())->toBe('Shipping');
});

it('uses shipment model', function (): void {
    expect(ShipmentResource::getModel())->toBe(Shipment::class);
});

it('has standard CRUD pages', function (): void {
    $pages = ShipmentResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('view');
    expect($pages)->toHaveKey('edit');
});

it('has relation managers configured', function (): void {
    $relations = ShipmentResource::getRelations();

    expect($relations)->toHaveCount(2);
});
