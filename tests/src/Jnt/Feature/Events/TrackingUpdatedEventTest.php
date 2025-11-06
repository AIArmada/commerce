<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Events\TrackingUpdatedEvent;
use Illuminate\Support\Facades\Event;

test('TrackingUpdatedEvent → it exposes tracking data', function (): void {
    $trackingData = new TrackingData(
        trackingNumber: 'TRACK456',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 10:00:00',
                description: 'Delivered',
                scanTypeCode: 'D',
                scanTypeName: 'Delivered',
                scanType: 'DELIVER',
                scanNetworkProvince: 'Wilayah Persekutuan',
                scanNetworkCity: 'Kuala Lumpur',
            ),
        ],
        orderId: 'ORDER123',
    );

    $event = new TrackingUpdatedEvent($trackingData);

    expect($event->tracking)->toBe($trackingData)
        ->and($event->getOrderId())->toBe('ORDER123')
        ->and($event->getTrackingNumber())->toBe('TRACK456')
        ->and($event->getLatestStatus())->toBe('DELIVER')
        ->and($event->getLatestDescription())->toBe('Delivered')
        ->and($event->getLatestLocation())->toBe('Kuala Lumpur, Wilayah Persekutuan');
});

test('TrackingUpdatedEvent → it detects delivered status', function (): void {
    $trackingData = new TrackingData(
        trackingNumber: 'TRACK456',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 10:00:00',
                description: 'Delivered',
                scanTypeCode: 'D',
                scanTypeName: 'Delivered',
                scanType: 'DELIVER',
            ),
        ],
        orderId: 'ORDER123',
    );

    $event = new TrackingUpdatedEvent($trackingData);

    expect($event->isDelivered())->toBeTrue()
        ->and($event->isInTransit())->toBeFalse()
        ->and($event->hasProblems())->toBeFalse();
});

test('TrackingUpdatedEvent → it detects in-transit status', function (): void {
    $trackingData = new TrackingData(
        trackingNumber: 'TRACK456',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 10:00:00',
                description: 'In transit',
                scanTypeCode: 'T',
                scanTypeName: 'Transfer',
                scanType: 'TRANSFER',
            ),
        ],
        orderId: 'ORDER123',
    );

    $event = new TrackingUpdatedEvent($trackingData);

    expect($event->isInTransit())->toBeTrue()
        ->and($event->isDelivered())->toBeFalse()
        ->and($event->hasProblems())->toBeFalse();
});

test('TrackingUpdatedEvent → it detects problem status', function (): void {
    $trackingData = new TrackingData(
        trackingNumber: 'TRACK456',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 10:00:00',
                description: 'Return to sender',
                scanTypeCode: 'R',
                scanTypeName: 'Return',
                scanType: 'RETURN',
            ),
        ],
        orderId: 'ORDER123',
    );

    $event = new TrackingUpdatedEvent($trackingData);

    expect($event->hasProblems())->toBeTrue()
        ->and($event->isDelivered())->toBeFalse()
        ->and($event->isInTransit())->toBeFalse();
});

test('TrackingUpdatedEvent → it detects collected status', function (): void {
    $trackingData = new TrackingData(
        trackingNumber: 'TRACK456',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 10:00:00',
                description: 'Collected',
                scanTypeCode: 'C',
                scanTypeName: 'Collect',
                scanType: 'COLLECT',
            ),
        ],
        orderId: 'ORDER123',
    );

    $event = new TrackingUpdatedEvent($trackingData);

    expect($event->isCollected())->toBeTrue();
});

test('TrackingUpdatedEvent → it provides details array', function (): void {
    $detail1 = new TrackingDetailData(
        scanTime: '2025-01-01 10:00:00',
        description: 'Delivered',
        scanTypeCode: 'D',
        scanTypeName: 'Delivered',
        scanType: 'DELIVER',
    );
    $detail2 = new TrackingDetailData(
        scanTime: '2025-01-01 09:00:00',
        description: 'In transit',
        scanTypeCode: 'T',
        scanTypeName: 'Transfer',
        scanType: 'TRANSFER',
    );

    $trackingData = new TrackingData(
        trackingNumber: 'TRACK456',
        details: [$detail1, $detail2],
        orderId: 'ORDER123',
    );

    $event = new TrackingUpdatedEvent($trackingData);

    expect($event->getDetails())->toBe([$detail1, $detail2])
        ->and($event->getDetailCount())->toBe(2);
});

test('TrackingUpdatedEvent → it can be dispatched', function (): void {
    Event::fake();

    $trackingData = new TrackingData(
        trackingNumber: 'TRACK456',
        details: [],
        orderId: 'ORDER123',
    );

    TrackingUpdatedEvent::dispatch($trackingData);

    Event::assertDispatched(TrackingUpdatedEvent::class);
});
