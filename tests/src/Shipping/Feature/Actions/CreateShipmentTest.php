<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Shipping\Actions\CreateShipment;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\States\Draft;
use Illuminate\Auth\Access\AuthorizationException;

describe('CreateShipment Action', function (): void {
    it('can create a shipment with minimal data', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'reference' => 'TEST-CREATE-001',
            'carrierCode' => 'test-carrier',
            'serviceCode' => 'standard',
            'origin' => ['name' => 'Origin', 'phone' => '123', 'line1' => '123 St', 'postcode' => '12345'],
            'destination' => ['name' => 'Dest', 'phone' => '456', 'line1' => '456 St', 'postcode' => '67890'],
        ];

        $shipment = $action->handle(ShipmentData::from($data));

        expect($shipment)->toBeInstanceOf(Shipment::class);
        expect($shipment->reference)->toBe('TEST-CREATE-001');
        expect($shipment->carrier_code)->toBe('test-carrier');
        expect($shipment->status)->toBeInstanceOf(Draft::class);
    });

    it('can create a shipment with full data', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'reference' => 'TEST-CREATE-002',
            'carrierCode' => 'test-carrier',
            'serviceCode' => 'express',
            'origin' => ['name' => 'Origin', 'phone' => '123', 'line1' => '123 St', 'postcode' => '12345', 'city' => 'Origin City'],
            'destination' => ['name' => 'Dest', 'phone' => '456', 'line1' => '456 St', 'postcode' => '67890', 'city' => 'Dest City'],
            'declaredValue' => 5000,
            'currency' => 'USD',
            'metadata' => ['test' => 'data'],
        ];

        $shipment = $action->handle(ShipmentData::from($data));

        expect($shipment)->toBeInstanceOf(Shipment::class);
        expect($shipment->reference)->toBe('TEST-CREATE-002');
        expect($shipment->carrier_code)->toBe('test-carrier');
        expect($shipment->service_code)->toBe('express');
        expect($shipment->declared_value)->toBe(5000);
        expect($shipment->currency)->toBe('USD');
        expect($shipment->metadata)->toBe(['test' => 'data']);
    });

    it('creates shipment with an empty reference', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'carrierCode' => 'test-carrier',
            'serviceCode' => 'standard',
            'origin' => ['name' => 'Origin', 'phone' => '123', 'line1' => '123 St', 'postcode' => '12345'],
            'destination' => ['name' => 'Dest', 'phone' => '456', 'line1' => '456 St', 'postcode' => '67890'],
            'reference' => 'SHP-AUTO-TEST',
        ];

        $shipment = $action->handle(ShipmentData::from($data));

        expect($shipment->reference)->toBeString();
        expect($shipment->reference)->toBe('SHP-AUTO-TEST');
    });

    it('creates shipment with draft status', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'reference' => 'TEST-STATUS',
            'carrierCode' => 'test-carrier',
            'serviceCode' => 'standard',
            'origin' => ['name' => 'Origin', 'phone' => '123', 'line1' => '123 St', 'postcode' => '12345'],
            'destination' => ['name' => 'Dest', 'phone' => '456', 'line1' => '456 St', 'postcode' => '67890'],
        ];

        $shipment = $action->handle(ShipmentData::from($data));

        expect($shipment->status)->toBeInstanceOf(Draft::class);
    });

    it('defaults to draft status', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'reference' => 'TEST-INVALID-STATUS',
            'carrierCode' => 'test-carrier',
            'serviceCode' => 'standard',
            'origin' => ['name' => 'Origin', 'phone' => '123', 'line1' => '123 St', 'postcode' => '12345'],
            'destination' => ['name' => 'Dest', 'phone' => '456', 'line1' => '456 St', 'postcode' => '67890'],
        ];

        $shipment = $action->handle(ShipmentData::from($data));

        expect($shipment->status)->toBeInstanceOf(Draft::class);
    });

    it('requires owner context when owner scoping is enabled', function (): void {
        config(['shipping.features.owner.enabled' => true]);

        try {
            OwnerContext::setForRequest(null);

            $action = app(CreateShipment::class);

            $data = [
                'reference' => 'TEST-OWNER-CONTEXT',
                'carrierCode' => 'test-carrier',
                'serviceCode' => 'standard',
                'origin' => ['name' => 'Origin', 'phone' => '123', 'line1' => '123 St', 'postcode' => '12345'],
                'destination' => ['name' => 'Dest', 'phone' => '456', 'line1' => '456 St', 'postcode' => '67890'],
            ];

            expect(fn () => $action->handle(ShipmentData::from($data)))
                ->toThrow(AuthorizationException::class);
        } finally {
            config(['shipping.features.owner.enabled' => false]);
        }
    });
});
