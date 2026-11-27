<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\CarrierOperationResult;
use AIArmada\Shipping\Enums\ShipmentOperationStatus;
use AIArmada\Shipping\Exceptions\ShipmentNotCancellableException;
use AIArmada\Shipping\Features\Shipments\Actions\CancelShipment;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use AIArmada\Shipping\Features\Shipments\Models\ShipmentOperation;
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
        expect($cancelled->operations()->where('operation_type', 'cancel')->firstOrFail()->status())->toBe(ShipmentOperationStatus::Succeeded);
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

    it('preserves a definite carrier cancellation rejection', function (): void {
        $shipment = Shipment::create([
            'reference' => 'TEST-CANCEL-REJECTED',
            'carrier_code' => 'null',
            'tracking_number' => 'TRACK-REJECTED',
            'status' => Pending::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $driver = Mockery::mock(ShippingDriverInterface::class);
        $driver->shouldReceive('cancelShipment')->once()->with('TRACK-REJECTED')
            ->andReturn(CarrierOperationResult::failed('Carrier rejected cancellation'));

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('driver')->with('null')->andReturn($driver);

        expect(fn () => (new CancelShipment($manager))->handle($shipment))
            ->toThrow(RuntimeException::class, 'Carrier rejected cancellation');
        expect($shipment->refresh()->status)->toBeInstanceOf(Pending::class);
        expect($shipment->operations()->where('operation_type', 'cancel')->firstOrFail()->status())->toBe(ShipmentOperationStatus::Failed);
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
        $mockDriver->shouldReceive('cancelShipment')->once()
            ->with('TRACK-NOT-CANCELLED')
            ->andThrow(new RuntimeException('Carrier API unavailable'));

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        $action = new CancelShipment($manager);
        expect(fn () => $action->handle($shipment, 'Carrier unavailable'))
            ->toThrow(RuntimeException::class, 'Carrier API unavailable');

        expect($shipment->refresh()->status)->not->toBeInstanceOf(Cancelled::class);

        $operation = ShipmentOperation::where('shipment_id', $shipment->id)
            ->where('operation_type', 'cancel')
            ->where('status', ShipmentOperationStatus::Unknown->value)
            ->first();

        expect($operation)->not->toBeNull();
        expect(fn () => $action->handle($shipment))->toThrow(RuntimeException::class, 'Reconcile the existing cancellation');
        expect($shipment->operations()->count())->toBe(1);
    });
    it('blocks ordinary shipment cancellation when creation is unresolved', function (ShipmentOperationStatus $status): void {
        $shipment = Shipment::create([
            'reference' => 'TEST-CANCEL-UNRESOLVED-CREATE',
            'carrier_code' => 'null',
            'status' => Pending::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $shipment->operations()->create([
            'operation_type' => 'create',
            'status' => $status->value,
            'operation_started_at' => now(),
        ]);

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldNotReceive('driver');

        expect(fn () => (new CancelShipment($manager))->handle($shipment))
            ->toThrow(RuntimeException::class, 'Reconcile the carrier operation');
        expect($shipment->refresh()->status)->toBeInstanceOf(Pending::class);
        expect($shipment->operations()->count())->toBe(1);
    })->with([ShipmentOperationStatus::Pending, ShipmentOperationStatus::Unknown, ShipmentOperationStatus::Succeeded]);
});
