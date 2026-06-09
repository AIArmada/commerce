<?php

declare(strict_types=1);

use AIArmada\Shipping\Actions\CancelShipment;
use AIArmada\Shipping\Exceptions\ShipmentNotCancellableException;
use AIArmada\Shipping\Models\Shipment;
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
});
