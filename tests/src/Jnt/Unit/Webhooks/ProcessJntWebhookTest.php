<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\ParcelDelivered;
use AIArmada\Jnt\Events\ParcelInTransit;
use AIArmada\Jnt\Events\ParcelOutForDelivery;
use AIArmada\Jnt\Events\ParcelPickedUp;
use AIArmada\Jnt\Events\TrackingUpdated;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Webhooks\ProcessJntWebhook;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\WebhookClient\Models\WebhookCall;

describe('ProcessJntWebhook', function (): void {
    it('extracts event type from latest detail scanType when bizContent is present', function (): void {
        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('extractEventType');

        $payload = [
            'bizContent' => json_encode([
                'billCode' => 'JNTMY123',
                'details' => [
                    ['scanType' => 'PICKUP'],
                ],
            ]),
        ];

        expect($method->invoke($processor, $payload))->toBe('PICKUP');
    });

    it('falls back to scantype/event/type fields when bizContent is missing', function (): void {
        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('extractEventType');

        expect($method->invoke($processor, ['scantype' => 'PICKUP']))->toBe('PICKUP');
        expect($method->invoke($processor, ['event' => 'tracking.update']))->toBe('tracking.update');
        expect($method->invoke($processor, ['type' => 'DELIVERY']))->toBe('DELIVERY');
        expect($method->invoke($processor, []))->toBe('tracking.update');
    });

    it('maps scan types to tracking statuses', function (): void {
        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('mapToStatus');

        expect($method->invoke($processor, 'PICKUP', []))->toBe(TrackingStatus::PickedUp);
        expect($method->invoke($processor, 'COLLECTED', []))->toBe(TrackingStatus::PickedUp);
        expect($method->invoke($processor, 'IN_TRANSIT', []))->toBe(TrackingStatus::InTransit);
        expect($method->invoke($processor, 'TRANSIT', []))->toBe(TrackingStatus::InTransit);
        expect($method->invoke($processor, 'ARRIVED', []))->toBe(TrackingStatus::InTransit);
        expect($method->invoke($processor, 'DEPARTED', []))->toBe(TrackingStatus::InTransit);
        expect($method->invoke($processor, 'OUT_FOR_DELIVERY', []))->toBe(TrackingStatus::OutForDelivery);
        expect($method->invoke($processor, 'DELIVERING', []))->toBe(TrackingStatus::OutForDelivery);
        expect($method->invoke($processor, 'DELIVERED', []))->toBe(TrackingStatus::Delivered);
        expect($method->invoke($processor, 'POD', []))->toBe(TrackingStatus::Delivered);
        expect($method->invoke($processor, 'FAILED', []))->toBe(TrackingStatus::Exception);
        expect($method->invoke($processor, 'UNDELIVERED', []))->toBe(TrackingStatus::Exception);
        expect($method->invoke($processor, 'RETURNED', []))->toBe(TrackingStatus::Returned);
        expect($method->invoke($processor, 'RTS', []))->toBe(TrackingStatus::Returned);
        expect($method->invoke($processor, 'UNKNOWN', []))->toBeNull();
    });

    it('dispatches PickedUp event for pickup status', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id', 'status' => 'pending']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');

        $method->invoke($processor, $shipment, TrackingStatus::PickedUp, ['test' => 'data']);

        Event::assertDispatched(ParcelPickedUp::class);
    });

    it('dispatches InTransit event for transit status', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');

        $method->invoke($processor, $shipment, TrackingStatus::InTransit, []);

        Event::assertDispatched(ParcelInTransit::class);
    });

    it('dispatches OutForDelivery event', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');

        $method->invoke($processor, $shipment, TrackingStatus::OutForDelivery, []);

        Event::assertDispatched(ParcelOutForDelivery::class);
    });

    it('dispatches Delivered event', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');

        $method->invoke($processor, $shipment, TrackingStatus::Delivered, []);

        Event::assertDispatched(ParcelDelivered::class);
    });

    it('does not dispatch event for unhandled status', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');

        $method->invoke($processor, $shipment, TrackingStatus::Exception, []);

        Event::assertNotDispatched(ParcelPickedUp::class);
        Event::assertNotDispatched(ParcelInTransit::class);
        Event::assertNotDispatched(ParcelOutForDelivery::class);
        Event::assertNotDispatched(ParcelDelivered::class);
    });

    it('dispatches TrackingUpdated when shipment not found', function (): void {
        Event::fake();
        config(['jnt.logging.channel' => 'stack']);

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();
        $webhookCall->payload = [
            'bizContent' => json_encode([
                'billCode' => 'UNKNOWN123',
                'details' => [],
            ]),
        ];

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('processEvent');

        // Since there's no shipment with this billCode, it should dispatch TrackingUpdated
        $method->invoke($processor, 'TRANSIT', $webhookCall->payload);

        Event::assertDispatched(TrackingUpdated::class);
    });

    it('syncs canonical order fields and tracking events from webhook details', function (): void {
        config()->set('jnt.owner.enabled', true);
        config()->set('jnt.owner.include_global', false);
        config()->set('jnt.owner.auto_assign_on_create', true);

        $owner = User::query()->create([
            'name' => 'Webhook Owner',
            'email' => 'webhook-owner@example.test',
            'password' => 'secret',
        ]);

        $shipment = OwnerContext::withOwner($owner, fn () => JntOrder::query()->create([
            'order_id' => 'ORDER-WEBHOOK-1',
            'tracking_number' => 'JNTMY123456',
            'customer_code' => 'CUST',
            'status' => 'pending',
        ]));

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();
        $webhookCall->id = 999;

        $processor = new ProcessJntWebhook($webhookCall);
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('processEvent');

        $payload = [
            'bizContent' => json_encode([
                'billCode' => 'JNTMY123456',
                'txlogisticId' => 'ORDER-WEBHOOK-1',
                'details' => [
                    [
                        'scanTime' => '2024-01-15 09:00:00',
                        'desc' => 'Package collected',
                        'scanTypeCode' => '301',
                        'scanTypeName' => 'Collection',
                        'scanType' => 'PICKUP',
                        'scanNetworkName' => 'KL Hub',
                    ],
                    [
                        'scanTime' => '2024-01-16 12:34:56',
                        'desc' => 'Parcel signed by recipient',
                        'scanTypeCode' => '602',
                        'scanTypeName' => 'Delivered',
                        'scanType' => 'POD',
                        'scanNetworkName' => 'Penang Branch',
                    ],
                ],
            ]),
        ];

        $method->invoke($processor, 'POD', $payload);

        $shipment->refresh();

        expect($shipment->status)->toBe(TrackingStatus::Delivered->value)
            ->and($shipment->last_status_code)->toBe('602')
            ->and($shipment->last_status)->toBe('Parcel signed by recipient')
            ->and($shipment->delivered_at?->format('Y-m-d H:i:s'))->toBe('2024-01-16 12:34:56')
            ->and($shipment->last_tracked_at)->not()->toBeNull();

        $events = JntTrackingEvent::query()
            ->withoutOwnerScope()
            ->where('order_id', $shipment->id)
            ->orderBy('scan_time')
            ->get();

        expect($events)->toHaveCount(2)
            ->and($events->pluck('scan_type_code')->all())->toBe(['301', '602'])
            ->and($events->pluck('owner_id')->unique()->all())->toBe([(string) $owner->getKey()]);
    });

    it('derives a deterministic event id from bizContent payloads', function (): void {
        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('extractEventId');

        $payload = [
            'bizContent' => json_encode([
                'billCode' => 'JNTMY123456',
                'details' => [
                    [
                        'scanTime' => '2024-01-16 12:34:56',
                        'scanTypeCode' => '602',
                        'scanTypeName' => 'Delivered',
                        'scanType' => 'POD',
                        'desc' => 'Parcel signed by recipient',
                    ],
                ],
            ]),
        ];

        $eventId = $method->invoke($processor, $payload);

        expect($eventId)
            ->toBeString()
            ->toStartWith('jnt:')
            ->and(Str::length($eventId))->toBeGreaterThan(10)
            ->and($method->invoke($processor, $payload))->toBe($eventId);
    });

    it('enforces unique tracking event hashes for webhook idempotency', function (): void {
        config()->set('jnt.owner.enabled', true);
        config()->set('jnt.owner.include_global', false);
        config()->set('jnt.owner.auto_assign_on_create', true);

        $owner = User::query()->create([
            'name' => 'Idempotency Owner',
            'email' => 'idempotency-owner@example.test',
            'password' => 'secret',
        ]);

        $shipment = OwnerContext::withOwner($owner, fn () => JntOrder::query()->create([
            'order_id' => 'ORDER-UNIQUE-1',
            'tracking_number' => 'JNTMYUNIQUE001',
            'customer_code' => 'CUST',
            'status' => 'pending',
        ]));

        $attributes = [
            'event_hash' => hash('sha256', 'ORDER-UNIQUE-1:602:2024-02-01 10:00:00'),
            'order_id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'order_reference' => $shipment->order_id,
            'scan_type_code' => '602',
            'scan_type_name' => 'Delivered',
            'scan_type' => 'POD',
            'scan_time' => '2024-02-01 10:00:00',
            'description' => 'Delivered',
            'payload' => ['scanTypeCode' => '602'],
            'owner_type' => $shipment->owner_type,
            'owner_id' => $shipment->owner_id,
        ];

        OwnerContext::withOwner($owner, function () use ($attributes): void {
            JntTrackingEvent::query()->create($attributes);
        });

        expect(fn () => OwnerContext::withOwner($owner, function () use ($attributes): void {
            JntTrackingEvent::query()->create($attributes);
        }))->toThrow(QueryException::class);
    });
});
