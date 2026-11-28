<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\TrackingData;
use AIArmada\Shipping\Data\TrackingStatus;
use AIArmada\Shipping\Enums\ShipmentOperationStatus;
use AIArmada\Shipping\Features\Shipments\Actions\ReconcileShipmentOperation;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use AIArmada\Shipping\Features\Shipments\Models\ShipmentOperation;
use AIArmada\Shipping\ShippingManager;

function reconcileShipment(string $trackingNumber): Shipment
{
    return Shipment::query()->create([
        'reference' => 'SHP-RECONCILE-' . $trackingNumber,
        'carrier_code' => 'manual',
        'tracking_number' => $trackingNumber,
        'origin_address' => ['name' => 'Origin'],
        'destination_address' => ['name' => 'Dest'],
    ]);
}

function reconcileTracking(string $trackingNumber, TrackingStatus $status): TrackingData
{
    return new TrackingData(
        trackingNumber: $trackingNumber,
        status: $status,
        events: [],
        carrier: 'manual',
    );
}

it('reconciles an unknown create when tracking proves pre-delivery creation', function (TrackingStatus $status): void {
    $shipment = reconcileShipment('TRK-RECONCILE-1');
    $operation = ShipmentOperation::query()->create([
        'shipment_id' => $shipment->id,
        'operation_type' => 'create',
        'status' => ShipmentOperationStatus::Unknown->value,
        'tracking_number' => 'TRK-RECONCILE-1',
        'operation_started_at' => now(),
    ]);

    $driver = Mockery::mock(ShippingDriverInterface::class);
    $driver->shouldReceive('track')->once()->with('TRK-RECONCILE-1')->andReturn(reconcileTracking('TRK-RECONCILE-1', $status));

    $manager = Mockery::mock(ShippingManager::class);
    $manager->shouldReceive('driver')->once()->with('manual')->andReturn($driver);
    app()->instance(ShippingManager::class, $manager);

    $result = ReconcileShipmentOperation::run($operation);

    expect($result->success)->toBeTrue()
        ->and($operation->refresh()->status())->toBe(ShipmentOperationStatus::Succeeded);
})->with([
    'in transit' => [TrackingStatus::InTransit],
    'out for delivery' => [TrackingStatus::OutForDelivery],
    'delivered' => [TrackingStatus::Delivered],
]);

it('does not reconcile when the tracking number does not match the operation', function (): void {
    $shipment = reconcileShipment('TRK-RECONCILE-MISMATCH');
    $operation = ShipmentOperation::query()->create([
        'shipment_id' => $shipment->id,
        'operation_type' => 'create',
        'status' => ShipmentOperationStatus::Unknown->value,
        'tracking_number' => 'TRK-RECONCILE-MISMATCH',
        'operation_started_at' => now(),
    ]);

    $driver = Mockery::mock(ShippingDriverInterface::class);
    $driver->shouldReceive('track')->once()->andReturn(reconcileTracking('OTHER-TRACK', TrackingStatus::InTransit));

    $manager = Mockery::mock(ShippingManager::class);
    $manager->shouldReceive('driver')->once()->with('manual')->andReturn($driver);
    app()->instance(ShippingManager::class, $manager);

    $result = ReconcileShipmentOperation::run($operation);

    expect($result->success)->toBeFalse()
        ->and($operation->refresh()->status())->toBe(ShipmentOperationStatus::Unknown);
});
