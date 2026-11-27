<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\TrackingEventData;
use AIArmada\Shipping\Data\TrackingStatus;
use AIArmada\Shipping\Features\Shipments\Actions\CreateShipment;
use AIArmada\Shipping\Features\Shipments\Actions\RecordTrackingEvent;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use AIArmada\Shipping\States\Delivered;
use AIArmada\Shipping\States\InTransit;
use AIArmada\Shipping\States\Shipped;
use Carbon\CarbonImmutable;

describe('RecordTrackingEvent', function (): void {
    it('records a new tracking event for a shipment', function (): void {
        $shipment = createShipment();

        $event = RecordTrackingEvent::run(
            $shipment,
            new TrackingEventData(
                code: 'pickup_scan',
                description: 'Package picked up',
                timestamp: CarbonImmutable::now(),
                normalizedStatus: TrackingStatus::InTransit,
                location: 'Kuala Lumpur Hub',
            ),
        );

        expect($event)->not->toBeNull();
        expect($event->carrier_event_code)->toBe('pickup_scan');
        expect($event->description)->toBe('Package picked up');
        expect($event->location)->toBe('Kuala Lumpur Hub');
        expect($event->normalized_status)->toBe(TrackingStatus::InTransit);
        expect($shipment->fresh()->events)->toHaveCount(1);
    });

    it('does not duplicate an existing tracking event', function (): void {
        $shipment = createShipment();
        $timestamp = CarbonImmutable::now();

        RecordTrackingEvent::run(
            $shipment,
            new TrackingEventData(
                code: 'pickup_scan',
                description: 'Package picked up',
                timestamp: $timestamp,
                normalizedStatus: TrackingStatus::InTransit,
            ),
        );

        $duplicate = RecordTrackingEvent::run(
            $shipment,
            new TrackingEventData(
                code: 'pickup_scan',
                description: 'Package picked up',
                timestamp: $timestamp,
                normalizedStatus: TrackingStatus::InTransit,
            ),
        );

        expect($duplicate)->toBeNull();
        expect($shipment->fresh()->events)->toHaveCount(1);
    });

    it('records late tracking events without regressing a delivered shipment', function (): void {
        $shipment = createShipment();
        $shipment->forceFill(['status' => Delivered::class])->saveQuietly();

        $shipment->events()->create([
            'carrier_event_code' => 'delivered',
            'normalized_status' => TrackingStatus::Delivered,
            'description' => 'Delivered',
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $event = RecordTrackingEvent::run($shipment, new TrackingEventData(
            code: 'late-transit',
            description: 'Late transit event',
            timestamp: CarbonImmutable::now()->subDay(),
            normalizedStatus: TrackingStatus::InTransit,
        ));

        expect($event)->not->toBeNull()
            ->and($shipment->fresh()->status->equals(Delivered::class))->toBeTrue()
            ->and($shipment->events()->count())->toBe(2);
    });

    it('updates shipment status based on tracking event', function (): void {
        $shipment = createShipment();

        RecordTrackingEvent::run(
            $shipment,
            new TrackingEventData(
                code: 'in_transit',
                description: 'In transit',
                timestamp: CarbonImmutable::now(),
                normalizedStatus: TrackingStatus::InTransit,
            ),
        );

        $fresh = $shipment->fresh();
        expect($fresh->status->equals(InTransit::class))->toBeTrue();
    });
});

function createShipment(): Shipment
{
    $data = ShipmentData::from([
        'reference' => 'SHP-TRACK-EVT',
        'carrierCode' => 'manual',
        'serviceCode' => 'standard',
        'origin' => AddressData::from([
            'name' => 'Warehouse',
            'phone' => '+60123456789',
            'line1' => '123 Storage St',
            'postcode' => '50000',
            'country' => 'MY',
        ]),
        'destination' => AddressData::from([
            'name' => 'Buyer',
            'phone' => '+60198765432',
            'line1' => '456 Buyer Ave',
            'postcode' => '47800',
            'country' => 'MY',
        ]),
    ]);

    $shipment = CreateShipment::run($data);
    $shipment->forceFill(['status' => Shipped::class])->saveQuietly();

    return $shipment->refresh();
}
