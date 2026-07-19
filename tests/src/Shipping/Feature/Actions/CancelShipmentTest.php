<?php

declare(strict_types=1);

use AIArmada\Shipping\Actions\CancelShipment;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Enums\ShipmentOperationStatus;
use AIArmada\Shipping\Exceptions\ShipmentNotCancellableException;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentOperation;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\States\Cancelled;
use AIArmada\Shipping\States\Pending;
use AIArmada\Shipping\States\Shipped;

describe('CancelShipment Action', function (): void {
    it('cancels a cancellable shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-CANCEL-DRAFT',
            'carrier_code' => 'null',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $action = new CancelShipment(app(ShippingManager::class));
        $cancelled = $action->handle($shipment, 'Customer request');

        expect($cancelled->status)->toBeInstanceOf(Cancelled::class);
        expect($cancelled->events)->toHaveCount(1);
        expect($cancelled->events->first()->description)->toBe('Customer request');
    });

    it('cancels a pending shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-CANCEL-PENDING',
            'carrier_code' => 'null',
            'status' => Pending::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $cancelled = CancelShipment::run($shipment, 'No longer needed');

        expect($cancelled->status)->toBeInstanceOf(Cancelled::class);
        expect($cancelled->isCancellable())->toBeFalse();
    });

    it('throws for non-cancellable shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-CANCEL-FAIL',
            'carrier_code' => 'null',
            'status' => Shipped::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect(fn () => CancelShipment::run($shipment))
            ->toThrow(ShipmentNotCancellableException::class);
    });

    it('does not mark shipment cancelled when carrier throws unknown', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-CANCEL-CARRIER-THROW',
            'carrier_code' => 'null',
            'tracking_number' => 'TRACK-NOT-CANCELLED',
            'status' => Pending::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $mockDriver = Mockery::mock(ShippingDriverInterface::class);
        $mockDriver->shouldReceive('cancelShipment')
            ->with('TRACK-NOT-CANCELLED')
            ->andThrow(new RuntimeException('Carrier API unavailable'));

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        $action = new CancelShipment($manager);
        $result = $action->handle($shipment, 'Carrier unavailable');

        expect($result->status)->not->toBeInstanceOf(Cancelled::class);

        $operation = ShipmentOperation::where('shipment_id', $shipment->id)
            ->where('operation_type', 'cancel')
            ->where('status', ShipmentOperationStatus::Unknown->value)
            ->first();

        expect($operation)->not->toBeNull();
    });
});
