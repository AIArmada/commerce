<?php

declare(strict_types=1);

use AIArmada\Shipping\Features\Shipments\Events\ShipmentCancelled;
use AIArmada\Shipping\Features\Shipments\Events\ShipmentCreated;
use AIArmada\Shipping\Features\Shipments\Events\ShipmentDelivered;
use AIArmada\Shipping\Features\Shipments\Events\ShipmentShipped;
use AIArmada\Shipping\Features\Shipments\Events\ShipmentStatusChanged;
use AIArmada\Shipping\Features\Shipments\Events\TrackingUpdated;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use AIArmada\Shipping\Features\Shipments\Models\ShipmentEvent;
use AIArmada\Shipping\Features\Shipments\States\Pending;
use AIArmada\Shipping\Features\Shipments\States\Shipped;

// ============================================
// ShipmentCreated Event Tests
// ============================================

describe('ShipmentCreated', function (): void {
    it('stores shipment reference', function (): void {
        $shipment = Mockery::mock(Shipment::class);

        $event = new ShipmentCreated($shipment);

        expect($event->shipment)->toBe($shipment);
    });
});

// ============================================
// ShipmentShipped Event Tests
// ============================================

describe('ShipmentShipped', function (): void {
    it('stores shipment reference', function (): void {
        $shipment = Mockery::mock(Shipment::class);

        $event = new ShipmentShipped($shipment);

        expect($event->shipment)->toBe($shipment);
    });
});

// ============================================
// ShipmentDelivered Event Tests
// ============================================

describe('ShipmentDelivered', function (): void {
    it('stores shipment reference', function (): void {
        $shipment = Mockery::mock(Shipment::class);

        $event = new ShipmentDelivered($shipment);

        expect($event->shipment)->toBe($shipment);
    });
});

// ============================================
// ShipmentCancelled Event Tests
// ============================================

describe('ShipmentCancelled', function (): void {
    it('stores shipment and reason', function (): void {
        $shipment = Mockery::mock(Shipment::class);

        $event = new ShipmentCancelled($shipment, 'Customer requested cancellation');

        expect($event->shipment)->toBe($shipment);
        expect($event->reason)->toBe('Customer requested cancellation');
    });

});

// ============================================
// ShipmentStatusChanged Event Tests
// ============================================

describe('ShipmentStatusChanged', function (): void {
    it('stores shipment and status transition', function (): void {
        $shipment = Mockery::mock(Shipment::class);

        $event = new ShipmentStatusChanged(
            $shipment,
            new Pending($shipment),
            new Shipped($shipment)
        );

        expect($event->shipment)->toBe($shipment);
        expect($event->oldStatus)->toBeInstanceOf(Pending::class);
        expect($event->newStatus)->toBeInstanceOf(Shipped::class);
    });
});

// ============================================
// TrackingUpdated Event Tests
// ============================================

describe('TrackingUpdated', function (): void {
    it('stores shipment and new events', function (): void {
        $shipment = Mockery::mock(Shipment::class);
        $trackingEvent = Mockery::mock(ShipmentEvent::class);
        $newEvents = collect([$trackingEvent]);

        $event = new TrackingUpdated($shipment, $newEvents);

        expect($event->shipment)->toBe($shipment);
        expect($event->newEvents)->toHaveCount(1);
        expect($event->newEvents->first())->toBe($trackingEvent);
    });
});
