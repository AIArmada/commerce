<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\TrackingEventData;
use AIArmada\Shipping\Enums\TrackingStatus;

describe('TrackingEventData', function (): void {
    it('can create tracking event data with required fields', function (): void {
        $event = new TrackingEventData(
            code: 'PU',
            description: 'Package picked up',
            timestamp: now()
        );

        expect($event->code)->toBe('PU');
        expect($event->description)->toBe('Package picked up');
        expect($event->timestamp)->toBeInstanceOf(DateTimeInterface::class);
        expect($event->normalizedStatus)->toBeNull();
        expect($event->location)->toBeNull();
        expect($event->city)->toBeNull();
        expect($event->state)->toBeNull();
        expect($event->country)->toBeNull();
        expect($event->raw)->toBeNull();
    });

    it('can create tracking event data with all fields', function (): void {
        $timestamp = now();
        $event = new TrackingEventData(
            code: 'IT',
            description: 'In transit to destination',
            timestamp: $timestamp,
            normalizedStatus: TrackingStatus::InTransit,
            location: 'Distribution Center',
            city: 'New York',
            state: 'NY',
            country: 'US',
            raw: ['carrier_ref' => 'ABC123']
        );

        expect($event->code)->toBe('IT');
        expect($event->description)->toBe('In transit to destination');
        expect($event->timestamp)->toBe($timestamp);
        expect($event->normalizedStatus)->toBe(TrackingStatus::InTransit);
        expect($event->location)->toBe('Distribution Center');
        expect($event->city)->toBe('New York');
        expect($event->state)->toBe('NY');
        expect($event->country)->toBe('US');
        expect($event->raw)->toBe(['carrier_ref' => 'ABC123']);
    });

    it('formats location with all components', function (): void {
        $event = new TrackingEventData(
            code: 'DE',
            description: 'Delivered',
            timestamp: now(),
            city: 'Los Angeles',
            state: 'CA',
            country: 'US'
        );

        expect($event->getFormattedLocation())->toBe('Los Angeles, CA, US');
    });

    it('formats location with missing components', function (): void {
        $event = new TrackingEventData(
            code: 'PU',
            description: 'Picked up',
            timestamp: now(),
            city: 'Chicago',
            country: 'US'
        );

        expect($event->getFormattedLocation())->toBe('Chicago, US');
    });

    it('formats location with only country', function (): void {
        $event = new TrackingEventData(
            code: 'EX',
            description: 'Export customs',
            timestamp: now(),
            country: 'US'
        );

        expect($event->getFormattedLocation())->toBe('US');
    });

    it('returns empty string when no location components', function (): void {
        $event = new TrackingEventData(
            code: 'PU',
            description: 'Picked up',
            timestamp: now()
        );

        expect($event->getFormattedLocation())->toBe('');
    });
});