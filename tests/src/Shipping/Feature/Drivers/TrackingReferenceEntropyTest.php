<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Drivers;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Drivers\ManualShippingDriver;
use AIArmada\Shipping\Drivers\ZoneBasedShippingDriver;
use AIArmada\Shipping\Services\ShippingZoneResolver;

function trackingReferenceShipmentPayload(): ShipmentData
{
    $address = new AddressData(
        name: 'Reference Tester',
        phone: '+60123456789',
        line1: '1 Test Road',
        postcode: '50000',
        country: 'MY',
    );

    return ShipmentData::from([
        'reference' => 'REF-ENTROPY',
        'carrierCode' => 'manual',
        'serviceCode' => 'standard',
        'origin' => $address,
        'destination' => $address,
    ]);
}

describe('Local tracking references', function (): void {
    it('issues unguessable manual tracking references', function (): void {
        $driver = new ManualShippingDriver;

        $first = $driver->createShipment(trackingReferenceShipmentPayload())->trackingNumber;
        $second = $driver->createShipment(trackingReferenceShipmentPayload())->trackingNumber;

        expect($first)->toMatch('/^MAN-[0-9A-HJKMNP-TV-Z]{26}$/')
            ->and($second)->toMatch('/^MAN-[0-9A-HJKMNP-TV-Z]{26}$/')
            ->and($first)->not->toBe($second);
    });

    it('issues unguessable zone tracking references', function (): void {
        $driver = new ZoneBasedShippingDriver(new ShippingZoneResolver);

        $first = $driver->createShipment(trackingReferenceShipmentPayload())->trackingNumber;
        $second = $driver->createShipment(trackingReferenceShipmentPayload())->trackingNumber;

        expect($first)->toMatch('/^ZONE-[0-9A-HJKMNP-TV-Z]{26}$/')
            ->and($second)->toMatch('/^ZONE-[0-9A-HJKMNP-TV-Z]{26}$/')
            ->and($first)->not->toBe($second);
    });
});
