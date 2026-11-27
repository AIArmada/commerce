<?php

declare(strict_types=1);

use AIArmada\Shipping\Features\Shipments\Exceptions\InvalidStatusTransitionException;
use AIArmada\Shipping\Exceptions\ShipmentAlreadyShippedException;
use AIArmada\Shipping\Exceptions\ShipmentCreationFailedException;
use AIArmada\Shipping\Exceptions\ShipmentNotCancellableException;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use AIArmada\Shipping\Features\Shipments\States\Cancelled;
use AIArmada\Shipping\Features\Shipments\States\Delivered;
use AIArmada\Shipping\Features\Shipments\States\InTransit;
use AIArmada\Shipping\Features\Shipments\States\Pending;

// ============================================
// InvalidStatusTransitionException Tests
// ============================================

describe('InvalidStatusTransitionException', function (): void {
    it('contains from and to statuses', function (): void {
        $shipment = Mockery::mock(Shipment::class);
        $exception = new InvalidStatusTransitionException(
            new Delivered($shipment),
            new InTransit($shipment)
        );

        expect($exception->from)->toBeInstanceOf(Delivered::class);
        expect($exception->to)->toBeInstanceOf(InTransit::class);
    });

    it('has descriptive message', function (): void {
        $shipment = Mockery::mock(Shipment::class);
        $exception = new InvalidStatusTransitionException(
            new Cancelled($shipment),
            new Pending($shipment)
        );

        expect($exception->getMessage())->toContain('cancelled');
        expect($exception->getMessage())->toContain('pending');
        expect($exception->getMessage())->toContain('Cannot transition');
    });
});

// ============================================
// ShipmentAlreadyShippedException Tests
// ============================================

describe('ShipmentAlreadyShippedException', function (): void {
    it('contains shipment reference in message', function (): void {
        $exception = new ShipmentAlreadyShippedException('TEST-001');

        expect($exception->getMessage())->toContain('TEST-001');
        expect($exception->getMessage())->toContain('already been shipped');
        expect($exception->reference)->toBe('TEST-001');
    });
});

// ============================================
// ShipmentCreationFailedException Tests
// ============================================

describe('ShipmentCreationFailedException', function (): void {
    it('contains reason in message', function (): void {
        $exception = new ShipmentCreationFailedException('Invalid address format');

        expect($exception->getMessage())->toContain('Invalid address format');
        expect($exception->getMessage())->toContain('Failed to create shipment');
    });
});

// ============================================
// ShipmentNotCancellableException Tests
// ============================================

describe('ShipmentNotCancellableException', function (): void {
    it('contains shipment and status in message', function (): void {
        $exception = new ShipmentNotCancellableException('TEST-002', 'delivered');

        expect($exception->getMessage())->toContain('TEST-002');
        expect($exception->getMessage())->toContain('delivered');
        expect($exception->getMessage())->toContain('cannot be cancelled');
        expect($exception->reference)->toBe('TEST-002');
        expect($exception->status)->toBe('delivered');
    });
});
