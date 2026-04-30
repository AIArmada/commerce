<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages\CreateReturnAuthorization;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages\EditReturnAuthorization;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages\ListReturnAuthorizations;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages\ViewReturnAuthorization;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\FilamentShipping\Resources\ShipmentResource\Pages\CreateShipment;
use AIArmada\FilamentShipping\Resources\ShipmentResource\Pages\EditShipment;
use AIArmada\FilamentShipping\Resources\ShipmentResource\Pages\ListShipments;
use AIArmada\FilamentShipping\Resources\ShipmentResource\Pages\ViewShipment;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Pages\CreateShippingZone;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Pages\EditShippingZone;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Pages\ListShippingZones;

uses(TestCase::class);

it('wires shipment pages to the correct resource', function (): void {
    expect(CreateShipment::getResource())->toBe(ShipmentResource::class);
    expect(ListShipments::getResource())->toBe(ShipmentResource::class);
    expect(ViewShipment::getResource())->toBe(ShipmentResource::class);
    expect(EditShipment::getResource())->toBe(ShipmentResource::class);
});

it('wires return authorization pages to the correct resource', function (): void {
    expect(CreateReturnAuthorization::getResource())->toBe(ReturnAuthorizationResource::class);
    expect(ListReturnAuthorizations::getResource())->toBe(ReturnAuthorizationResource::class);
    expect(ViewReturnAuthorization::getResource())->toBe(ReturnAuthorizationResource::class);
    expect(EditReturnAuthorization::getResource())->toBe(ReturnAuthorizationResource::class);
});

it('wires shipping zone pages to the correct resource', function (): void {
    expect(CreateShippingZone::getResource())->toBe(ShippingZoneResource::class);
    expect(ListShippingZones::getResource())->toBe(ShippingZoneResource::class);
    expect(EditShippingZone::getResource())->toBe(ShippingZoneResource::class);
});

it('defines header actions for list/view/edit resource pages', function (): void {
    $pages = [
        new ListShipments,
        new ViewShipment,
        new EditShipment,
        new ListReturnAuthorizations,
        new ViewReturnAuthorization,
        new EditReturnAuthorization,
        new ListShippingZones,
        new EditShippingZone,
    ];

    $getHeaderActions = static function (object $page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');

        /** @var array $actions */
        $actions = $method->invoke($page);

        return $actions;
    };

    foreach ($pages as $page) {
        expect($getHeaderActions($page))->not()->toBeEmpty();
    }
});
