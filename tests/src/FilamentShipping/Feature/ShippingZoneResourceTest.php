<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource;
use AIArmada\Shipping\Models\ShippingZone;
use Filament\Support\Icons\Heroicon;

uses(TestCase::class);

// ============================================
// ShippingZoneResource Tests
// ============================================

it('has correct navigation icon', function (): void {
    expect(ShippingZoneResource::getNavigationIcon())->toBe(Heroicon::OutlinedMap);
});

it('has correct navigation group', function (): void {
    expect(ShippingZoneResource::getNavigationGroup())->toBe('Shipping');
});

it('uses shipping zone model', function (): void {
    expect(ShippingZoneResource::getModel())->toBe(ShippingZone::class);
});

it('has standard CRUD pages', function (): void {
    $pages = ShippingZoneResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('edit');
});
