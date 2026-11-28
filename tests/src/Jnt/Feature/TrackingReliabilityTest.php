<?php

declare(strict_types=1);

use AIArmada\Jnt\Features\Orders\Api\UpdateTrackingSnapshot;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Tracking\Models\JntTrackingEvent;
use AIArmada\Jnt\Support\TrackingEventHash;
use AIArmada\Jnt\Support\TrackingScanTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('jnt.owner.enabled', false);
    config()->set('app.timezone', 'UTC');
});

function trackingSnapshotOrder(string $trackingNumber): JntOrder
{
    return JntOrder::query()->create([
        'order_id' => 'ORDER-' . $trackingNumber,
        'tracking_number' => $trackingNumber,
        'customer_code' => 'CUST',
        'status' => 'in_transit',
    ]);
}

function insertTrackingScan(JntOrder $order, string $scanTime): void
{
    DB::table((new JntTrackingEvent)->getTable())->insert([
        'id' => (string) str()->uuid(),
        'order_id' => $order->id,
        'tracking_number' => $order->tracking_number,
        'scan_type_code' => '602',
        'scan_time' => $scanTime,
        'owner_type' => $order->owner_type,
        'owner_id' => $order->owner_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('compares stored scan timestamps as instants including database offsets', function (): void {
    $order = trackingSnapshotOrder('JNT-SNAPSHOT-1');
    insertTrackingScan($order, '2024-01-16 12:00:00+08:00');

    $snapshot = app(UpdateTrackingSnapshot::class);

    $snapshot->handle((string) $order->id, ['last_status' => 'older'], CarbonImmutable::parse('2024-01-16 03:59:59+00:00'));

    expect($order->refresh()->last_status)->toBeNull();

    $snapshot->handle((string) $order->id, ['last_status' => 'equal'], CarbonImmutable::parse('2024-01-16 04:00:00+00:00'));

    expect($order->refresh()->last_status)->toBe('equal');
});

it('normalizes carrier scan times to the storage timezone', function (): void {
    expect(TrackingScanTime::forStorage(CarbonImmutable::parse('2024-01-16 12:00:00+08:00')))
        ->toBe('2024-01-16 04:00:00.000000');
});

it('hashes identical instants identically regardless of offset', function (): void {
    $first = TrackingEventHash::forIdentity(
        'order-1',
        'JNT1',
        '602',
        '2024-01-16 12:00:00+08:00',
        'Delivered',
        'User',
        'user-1',
    );

    $second = TrackingEventHash::forIdentity(
        'order-1',
        'JNT1',
        '602',
        '2024-01-16 04:00:00+00:00',
        'Delivered',
        'User',
        'user-1',
    );

    expect($first)->toBe($second);
});
