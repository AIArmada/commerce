<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Services\JntStatusMapper;

uses(FilamentJntTestCase::class);

it('memoizes normalized status per model instance', function (): void {
    $order = new JntOrder([
        'last_status_code' => '602',
        'last_status' => 'Parcel signed by recipient',
    ]);

    $mapperCalls = 0;
    $mapper = Mockery::mock(JntStatusMapper::class);
    $mapper->shouldReceive('resolve')->andReturnUsing(function () use (&$mapperCalls): TrackingStatus {
        $mapperCalls++;

        return TrackingStatus::Delivered;
    });
    app()->instance(JntStatusMapper::class, $mapper);

    expect($order->getNormalizedStatus())->toBe(TrackingStatus::Delivered)
        ->and($order->getNormalizedStatus())->toBe(TrackingStatus::Delivered)
        ->and($order->getNormalizedStatus())->toBe(TrackingStatus::Delivered)
        ->and($mapperCalls)->toBe(1);

    $order->last_status = 'Out for delivery';

    expect($order->getNormalizedStatus())->toBe(TrackingStatus::Delivered)
        ->and($mapperCalls)->toBe(2);

    Mockery::close();
});

it('memoizes tracking event status per model instance', function (): void {
    $event = new JntTrackingEvent([
        'scan_type_code' => '100',
        'description' => 'Delivered',
    ]);

    $mapperCalls = 0;
    $mapper = Mockery::mock(JntStatusMapper::class);
    $mapper->shouldReceive('resolve')->andReturnUsing(function () use (&$mapperCalls): TrackingStatus {
        $mapperCalls++;

        return TrackingStatus::Delivered;
    });
    app()->instance(JntStatusMapper::class, $mapper);

    $event->getNormalizedStatus();
    $event->getNormalizedStatus();

    expect($mapperCalls)->toBe(1);

    Mockery::close();
});

it('caps infolist repeatable state via config', function (): void {
    config()->set('filament-jnt.infolist.tracking_events_limit', 2);
    config()->set('filament-jnt.infolist.items_limit', 1);

    expect((int) config('filament-jnt.infolist.tracking_events_limit'))->toBe(2)
        ->and((int) config('filament-jnt.infolist.items_limit'))->toBe(1);

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-INFOLIST-1',
        'customer_code' => 'CUST',
    ]);

    foreach (range(1, 5) as $i) {
        JntTrackingEvent::query()->create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-INFOLIST-1',
            'scan_type_code' => '20',
            'scan_time' => now()->addMinutes($i),
        ]);
    }

    $events = $order->trackingEvents()
        ->latest('scan_time')
        ->limit((int) config('filament-jnt.infolist.tracking_events_limit', 50))
        ->get();

    expect($events)->toHaveCount(2)
        ->and(JntTrackingEvent::query()->where('order_id', $order->id)->count())->toBe(5);
});
