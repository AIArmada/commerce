<?php

declare(strict_types=1);

use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Events\ParcelDelivered;
use AIArmada\Jnt\Events\ParcelInTransit;
use AIArmada\Jnt\Events\ParcelOutForDelivery;
use AIArmada\Jnt\Events\ParcelPickedUp;
use AIArmada\Jnt\Events\TrackingUpdated;
use AIArmada\Jnt\Models\JntOrder;

describe('JntOrderStatusChanged event', function (): void {
    it('can be instantiated with order and status', function (): void {
        $order = new JntOrder;
        $order->forceFill([
            'id' => 'test-uuid',
            'order_id' => 'ORDER123',
            'tracking_number' => 'JNT123456',
        ]);

        $event = new JntOrderStatusChanged($order, TrackingStatus::Delivered);

        expect($event->order)->toBe($order);
        expect($event->currentStatus)->toBe(TrackingStatus::Delivered);
        expect($event->previousStatusCode)->toBeNull();
    });

    it('can be instantiated with previous status code', function (): void {
        $order = new JntOrder;
        $order->forceFill(['id' => 'test-uuid', 'order_id' => 'ORDER123']);

        $event = new JntOrderStatusChanged($order, TrackingStatus::InTransit, 'pending');

        expect($event->previousStatusCode)->toBe('pending');
    });

    it('gets order ID correctly', function (): void {
        $order = new JntOrder;
        $order->forceFill(['id' => 'test-uuid', 'order_id' => 'ORDER123']);

        $event = new JntOrderStatusChanged($order, TrackingStatus::PickedUp);

        expect($event->getOrderId())->toBe('ORDER123');
    });

    it('gets tracking number correctly', function (): void {
        $order = new JntOrder;
        $order->forceFill([
            'id' => 'test-uuid',
            'order_id' => 'ORDER123',
            'tracking_number' => 'JNT123456',
        ]);

        $event = new JntOrderStatusChanged($order, TrackingStatus::InTransit);

        expect($event->getTrackingNumber())->toBe('JNT123456');
    });

    it('identifies delivered status', function (): void {
        $order = new JntOrder;
        $order->forceFill(['id' => 'test-uuid', 'order_id' => 'ORDER123']);

        $event = new JntOrderStatusChanged($order, TrackingStatus::Delivered);
        expect($event->isDelivered())->toBeTrue();

        $event2 = new JntOrderStatusChanged($order, TrackingStatus::InTransit);
        expect($event2->isDelivered())->toBeFalse();
    });

    it('identifies exception status', function (): void {
        $order = new JntOrder;
        $order->forceFill(['id' => 'test-uuid', 'order_id' => 'ORDER123']);

        $event = new JntOrderStatusChanged($order, TrackingStatus::Exception);
        expect($event->hasException())->toBeTrue();

        $event2 = new JntOrderStatusChanged($order, TrackingStatus::Delivered);
        expect($event2->hasException())->toBeFalse();
    });

    it('identifies returning status', function (): void {
        $order = new JntOrder;
        $order->forceFill(['id' => 'test-uuid', 'order_id' => 'ORDER123']);

        $event = new JntOrderStatusChanged($order, TrackingStatus::ReturnInitiated);
        expect($event->isReturning())->toBeTrue();

        $event2 = new JntOrderStatusChanged($order, TrackingStatus::Returned);
        expect($event2->isReturning())->toBeTrue();

        $event3 = new JntOrderStatusChanged($order, TrackingStatus::InTransit);
        expect($event3->isReturning())->toBeFalse();
    });

    it('identifies states requiring attention', function (): void {
        $order = new JntOrder;
        $order->forceFill(['id' => 'test-uuid', 'order_id' => 'ORDER123']);

        $event = new JntOrderStatusChanged($order, TrackingStatus::DeliveryAttempted);
        expect($event->requiresAttention())->toBeTrue();

        $event2 = new JntOrderStatusChanged($order, TrackingStatus::Delivered);
        expect($event2->requiresAttention())->toBeFalse();
    });

    it('identifies terminal status', function (): void {
        $order = new JntOrder;
        $order->forceFill(['id' => 'test-uuid', 'order_id' => 'ORDER123']);

        $event = new JntOrderStatusChanged($order, TrackingStatus::Delivered);
        expect($event->isTerminal())->toBeTrue();

        $event2 = new JntOrderStatusChanged($order, TrackingStatus::InTransit);
        expect($event2->isTerminal())->toBeFalse();
    });
});

describe('ParcelDelivered event', function (): void {
    it('can be instantiated with shipment', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123', 'tracking_number' => 'JNT123']);

        $event = new ParcelDelivered($shipment);

        expect($event->shipment)->toBe($shipment);
        expect($event->payload)->toBe([]);
    });

    it('can be instantiated with payload', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123', 'tracking_number' => 'JNT123']);

        $payload = ['key' => 'value', 'status' => 'delivered'];
        $event = new ParcelDelivered($shipment, $payload);

        expect($event->payload)->toBe($payload);
    });

    it('gets shipment ID', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123', 'tracking_number' => 'JNT123']);

        $event = new ParcelDelivered($shipment);

        expect($event->getShipmentId())->toBe('SHIP123');
    });

    it('gets tracking number', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123', 'tracking_number' => 'JNT123']);

        $event = new ParcelDelivered($shipment);

        expect($event->getTrackingNumber())->toBe('JNT123');
    });
});

describe('ParcelInTransit event', function (): void {
    it('can be instantiated with shipment', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123']);

        $event = new ParcelInTransit($shipment);

        expect($event->shipment)->toBe($shipment);
        expect($event->payload)->toBe([]);
    });

    it('can be instantiated with payload', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123']);

        $payload = ['location' => 'Hub A', 'timestamp' => '2024-01-15'];
        $event = new ParcelInTransit($shipment, $payload);

        expect($event->payload)->toBe($payload);
    });
});

describe('ParcelOutForDelivery event', function (): void {
    it('can be instantiated with shipment', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123']);

        $event = new ParcelOutForDelivery($shipment);

        expect($event->shipment)->toBe($shipment);
        expect($event->payload)->toBe([]);
    });

    it('can be instantiated with payload', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123']);

        $payload = ['driver' => 'John', 'eta' => '14:00'];
        $event = new ParcelOutForDelivery($shipment, $payload);

        expect($event->payload)->toBe($payload);
    });
});

describe('ParcelPickedUp event', function (): void {
    it('can be instantiated with shipment', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123']);

        $event = new ParcelPickedUp($shipment);

        expect($event->shipment)->toBe($shipment);
        expect($event->payload)->toBe([]);
    });

    it('can be instantiated with payload', function (): void {
        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'SHIP123']);

        $payload = ['pickup_time' => '2024-01-15 10:00:00', 'hub' => 'Central'];
        $event = new ParcelPickedUp($shipment, $payload);

        expect($event->payload)->toBe($payload);
    });
});

describe('TrackingUpdated event', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $event = new TrackingUpdated('JNT123456', 'DELIVERY_SCAN', ['status' => 'in_transit']);

        expect($event->billcode)->toBe('JNT123456');
        expect($event->eventType)->toBe('DELIVERY_SCAN');
        expect($event->payload)->toBe(['status' => 'in_transit']);
    });

    it('creates with empty payload by default', function (): void {
        $event = new TrackingUpdated('JNT123', 'PICKUP');

        expect($event->payload)->toBe([]);
    });
});
