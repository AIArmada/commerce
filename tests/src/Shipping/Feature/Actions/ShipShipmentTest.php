<?php

declare(strict_types=1);

use AIArmada\Shipping\Actions\ShipShipment;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\CarrierOperationResult;
use AIArmada\Shipping\Data\LabelData;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Enums\ShipmentOperationStatus;
use AIArmada\Shipping\Exceptions\ShipmentAlreadyShippedException;
use AIArmada\Shipping\Exceptions\ShipmentCreationFailedException;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentOperation;
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

        $mockResult = CarrierOperationResult::succeeded(
            trackingNumber: 'TRACK-SHIP-001',
            carrierReference: 'CARRIER-001',
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

        $mockResult = CarrierOperationResult::succeeded(
            trackingNumber: 'TRACK-LABEL-001',
            carrierReference: 'CARRIER-001',
        );

        $labelData = new LabelData(
            format: 'pdf',
            url: 'https://example.com/label-auto.pdf',
            size: 'a4',
        );

        $mockDriver = Mockery::mock(ShippingDriverInterface::class);
        $mockDriver->shouldReceive('createShipment')->andReturn($mockResult);
        $mockDriver->shouldReceive('supports')->with(DriverCapability::LabelGeneration)->andReturn(false);

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        $action = new ShipShipment($manager);
        $shipped = $action->handle($shipment);

        expect($shipped->status)->toBeInstanceOf(Shipped::class);
        expect($shipped->tracking_number)->toBe('TRACK-LABEL-001');
    });

    it('does not call carrier twice on concurrent submit', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP-CONCURRENT',
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

        Cache::spy();

        $mockResult = CarrierOperationResult::succeeded(
            trackingNumber: 'TRACK-SHIP-001',
            carrierReference: 'CARRIER-001',
        );

        $mockDriver = Mockery::mock(ShippingDriverInterface::class);
        $mockDriver->shouldReceive('createShipment')->once()->andReturn($mockResult);
        $mockDriver->shouldReceive('supports')->with(DriverCapability::LabelGeneration)->andReturn(false);

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        $action = new ShipShipment($manager);
        $shipped = $action->handle($shipment);

        Cache::shouldHaveReceived('lock')
            ->with("shipment:{$shipment->id}:ship", 30)
            ->once();

        expect($shipped->status)->toBeInstanceOf(Shipped::class);
    });

    it('reuses existing pending operation on retry', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP-RETRY',
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

        ShipmentOperation::create([
            'shipment_id' => $shipment->id,
            'operation_type' => 'create',
            'status' => ShipmentOperationStatus::Pending->value,
            'reference' => $shipment->reference,
            'operation_started_at' => now(),
        ]);

        $mockResult = CarrierOperationResult::succeeded(
            trackingNumber: 'TRACK-RETRY-001',
            carrierReference: 'CARRIER-001',
        );

        $mockDriver = Mockery::mock(ShippingDriverInterface::class);
        $mockDriver->shouldReceive('createShipment')->andReturn($mockResult);
        $mockDriver->shouldReceive('supports')->with(DriverCapability::LabelGeneration)->andReturn(false);

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        $action = new ShipShipment($manager);
        $shipped = $action->handle($shipment);

        $operations = ShipmentOperation::where('shipment_id', $shipment->id)
            ->where('operation_type', 'create')
            ->get();

        expect($operations)->toHaveCount(1);
        expect($shipped->status)->toBeInstanceOf(Shipped::class);
    });
});
