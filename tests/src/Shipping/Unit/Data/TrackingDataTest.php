<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\TrackingData;
use AIArmada\Shipping\Data\TrackingEventData;
use AIArmada\Shipping\Enums\TrackingStatus;
use Illuminate\Support\Collection;

describe('TrackingData', function (): void {
    it('can create tracking data with events', function (): void {
        $events = collect([
            new TrackingEventData(
                code: 'PU',
                description: 'Package picked up',
                timestamp: now()->subHours(2),
                normalizedStatus: TrackingStatus::PickedUp,
                location: 'Warehouse'
            ),
            new TrackingEventData(
                code: 'IT',
                description: 'In transit',
                timestamp: now()->subHour(),
                normalizedStatus: TrackingStatus::InTransit,
                location: 'Distribution Center'
            ),
        ]);

        $tracking = new TrackingData(
            trackingNumber: 'TRACK123',
            status: TrackingStatus::InTransit,
            events: $events,
            carrier: 'FedEx',
            estimatedDelivery: now()->addDays(2),
            currentLocation: 'Distribution Center'
        );

        expect($tracking->trackingNumber)->toBe('TRACK123');
        expect($tracking->status)->toBe(TrackingStatus::InTransit);
        expect($tracking->events)->toBeInstanceOf(Collection::class);
        expect($tracking->events)->toHaveCount(2);
        expect($tracking->carrier)->toBe('FedEx');
        expect($tracking->estimatedDelivery)->not->toBeNull();
        expect($tracking->currentLocation)->toBe('Distribution Center');
    });

    it('checks if package is delivered', function (): void {
        $delivered = new TrackingData(
            trackingNumber: 'TRACK123',
            status: TrackingStatus::Delivered,
            events: collect()
        );

        $inTransit = new TrackingData(
            trackingNumber: 'TRACK456',
            status: TrackingStatus::InTransit,
            events: collect()
        );

        expect($delivered->isDelivered())->toBeTrue();
        expect($inTransit->isDelivered())->toBeFalse();
    });

    it('checks if tracking has events', function (): void {
        $withEvents = new TrackingData(
            trackingNumber: 'TRACK123',
            status: TrackingStatus::InTransit,
            events: collect([new TrackingEventData(
                code: 'PU',
                description: 'Picked up',
                timestamp: now(),
                normalizedStatus: TrackingStatus::PickedUp
            )])
        );

        $withoutEvents = new TrackingData(
            trackingNumber: 'TRACK456',
            status: TrackingStatus::LabelCreated,
            events: collect()
        );

        expect($withEvents->hasEvents())->toBeTrue();
        expect($withoutEvents->hasEvents())->toBeFalse();
    });

    it('returns latest event', function (): void {
        $event1 = new TrackingEventData(
            code: 'PU',
            description: 'Picked up',
            timestamp: now()->subHours(2),
            normalizedStatus: TrackingStatus::PickedUp
        );

        $event2 = new TrackingEventData(
            code: 'IT',
            description: 'In transit',
            timestamp: now()->subHour(),
            normalizedStatus: TrackingStatus::InTransit
        );

        $tracking = new TrackingData(
            trackingNumber: 'TRACK123',
            status: TrackingStatus::InTransit,
            events: collect([$event1, $event2])
        );

        $latest = $tracking->getLatestEvent();
        expect($latest)->toBe($event1); // First in collection is latest
    });

    it('returns null for latest event when no events', function (): void {
        $tracking = new TrackingData(
            trackingNumber: 'TRACK123',
            status: TrackingStatus::LabelCreated,
            events: collect()
        );

        expect($tracking->getLatestEvent())->toBeNull();
    });
});
