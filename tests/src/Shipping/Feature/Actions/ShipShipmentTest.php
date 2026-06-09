<?php

declare(strict_types=1);

use AIArmada\Shipping\Actions\GenerateLabel;
use AIArmada\Shipping\Actions\ShipShipment;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\LabelData;
use AIArmada\Shipping\Data\ShipmentResultData;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Exceptions\ShipmentAlreadyShippedException;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\States\Pending;
use AIArmada\Shipping\States\Shipped;

describe('ShipShipment Action', function (): void {
    it('ships a pending shipment via driver', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP-ACTION',
            'carrier_code' => 'null',
            'status' => Pending::class,
            'origin_address' => [
                'name' => 'Origin', 'phone' => '123', 'line1' => '123 St',
                'postcode' => '12345', 'country' => 'US',
            ],
            'destination_address' => [
                'name' => 'Dest', 'phone' => '456', 'line1' => '456 St',
                'postcode' => '67890', 'country' => 'US',
            ],
        ]);

        $mockResult = new ShipmentResultData(
            success: true,
            trackingNumber: 'TRACK-SHIP-001',
            carrierReference: 'CARRIER-001',
            labelUrl: 'https://example.com/label.pdf',
        );

        $mockDriver = Mockery::mock(ShippingDriverInterface::class);
        $mockDriver->shouldReceive('createShipment')->andReturn($mockResult);
        $mockDriver->shouldReceive('supports')->with(DriverCapability::LabelGeneration)->andReturn(false);

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        $action = new ShipShipment($manager);
        $shipped = $action->handle($shipment);

        expect($shipped->status)->toBeInstanceOf(Shipped::class);
        expect($shipped->tracking_number)->toBe('TRACK-SHIP-001');
        expect($shipped->carrier_reference)->toBe('CARRIER-001');
        expect($shipped->shipped_at)->not->toBeNull();
        expect($shipped->events)->toHaveCount(1);
    });

    it('throws when shipment is not pending', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP-FAIL',
            'carrier_code' => 'null',
            'status' => Shipped::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $action = app(ShipShipment::class);

        expect(fn () => $action->handle($shipment))
            ->toThrow(ShipmentAlreadyShippedException::class);
    });

    it('generates label when driver supports it and no URL returned', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-LABEL-GEN',
            'carrier_code' => 'null',
            'status' => Pending::class,
            'origin_address' => [
                'name' => 'Origin', 'phone' => '123', 'line1' => '123 St',
                'postcode' => '12345', 'country' => 'US',
            ],
            'destination_address' => [
                'name' => 'Dest', 'phone' => '456', 'line1' => '456 St',
                'postcode' => '67890', 'country' => 'US',
            ],
        ]);

        $mockResult = new ShipmentResultData(
            success: true,
            trackingNumber: 'TRACK-LABEL-001',
            carrierReference: 'CARRIER-001',
            labelUrl: null,
        );

        $labelData = new LabelData(
            format: 'pdf',
            url: 'https://example.com/label-auto.pdf',
            size: 'a4',
        );

        $mockDriver = Mockery::mock(ShippingDriverInterface::class);
        $mockDriver->shouldReceive('createShipment')->andReturn($mockResult);
        $mockDriver->shouldReceive('supports')->with(DriverCapability::LabelGeneration)->andReturn(true);
        $mockDriver->shouldReceive('generateLabel')->with('TRACK-LABEL-001', [])->andReturn($labelData);

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        $action = new ShipShipment($manager);
        $shipped = $action->handle($shipment);

        expect($shipped->status)->toBeInstanceOf(Shipped::class);
        expect($shipped->tracking_number)->toBe('TRACK-LABEL-001');
        expect($shipped->label_url)->toBe('https://example.com/label-auto.pdf');
    });
});
