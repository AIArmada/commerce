<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Services;

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\TrackingData;
use AIArmada\Shipping\Data\TrackingEventData;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Enums\TrackingStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\TrackingAggregator;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\States\InTransit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;

function batchDedupShipment(): Shipment
{
    $shipment = Shipment::query()->create([
        'reference' => 'SHP-BATCH-DEDUP',
        'carrier_code' => 'test',
        'tracking_number' => 'TEST-BATCH-1',
        'origin_address' => ['name' => 'Origin'],
        'destination_address' => ['name' => 'Dest'],
    ]);

    $shipment->forceFill(['status' => InTransit::class])->save();

    return $shipment->refresh();
}

function batchDedupAggregator(TrackingData $trackingData): TrackingAggregator
{
    $driver = Mockery::mock(ShippingDriverInterface::class);
    $driver->shouldReceive('supports')->with(DriverCapability::Tracking)->andReturn(true);
    $driver->shouldReceive('track')->andReturn($trackingData);

    $manager = Mockery::mock(ShippingManager::class);
    $manager->shouldReceive('driver')->with('test')->andReturn($driver);

    return new TrackingAggregator($manager);
}

describe('Tracking event batch deduplication', function (): void {
    it('fetches existing events once per shipment and skips duplicates', function (): void {
        $shipment = batchDedupShipment();
        $seenAt = CarbonImmutable::parse('2026-09-01 10:00:00');
        $firstNewAt = CarbonImmutable::parse('2026-09-02 10:00:00');
        $secondNewAt = CarbonImmutable::parse('2026-09-03 10:00:00');

        $shipment->events()->create([
            'carrier_event_code' => 'picked-up',
            'normalized_status' => TrackingStatus::InTransit,
            'description' => 'Picked up',
            'occurred_at' => $seenAt,
        ]);

        $trackingData = new TrackingData(
            trackingNumber: 'TEST-BATCH-1',
            status: TrackingStatus::InTransit,
            events: collect([
                new TrackingEventData(code: 'picked-up', description: 'Picked up', timestamp: $seenAt, normalizedStatus: TrackingStatus::InTransit),
                new TrackingEventData(code: 'in-transit', description: 'In transit', timestamp: $firstNewAt, normalizedStatus: TrackingStatus::InTransit),
                new TrackingEventData(code: 'in-transit', description: 'In transit', timestamp: $firstNewAt, normalizedStatus: TrackingStatus::InTransit),
                new TrackingEventData(code: 'arrived', description: 'Arrived', timestamp: $secondNewAt, normalizedStatus: TrackingStatus::InTransit),
            ]),
        );

        $dedupSelects = 0;

        DB::listen(function ($query) use (&$dedupSelects): void {
            if (str_starts_with(mb_ltrim($query->sql), 'select') && str_contains($query->sql, 'carrier_event_code')) {
                $dedupSelects++;
            }
        });

        batchDedupAggregator($trackingData)->syncTracking($shipment);

        expect($dedupSelects)->toBe(1)
            ->and($shipment->events()->count())->toBe(3);
    });
});
