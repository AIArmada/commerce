<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Models\JntOrder;

describe('JntOrderStatusChanged event', function (): void {
    it('captures order and status data', function (): void {
        $order = new JntOrder;
        $order->forceFill([
            'id' => 'test-uuid',
            'order_id' => 'ORDER123',
            'tracking_number' => 'JNT123456',
        ]);

        $event = JntOrderStatusChanged::fromOrder($order, TrackingStatus::Delivered);

        expect($event->orderKey)->toBe('test-uuid')
            ->and($event->orderReference)->toBe('ORDER123')
            ->and($event->trackingNumber)->toBe('JNT123456')
            ->and($event->currentStatus)->toBe(TrackingStatus::Delivered)
            ->and($event->previousStatusCode)->toBeNull();
    });

    it('retains the previous status code', function (): void {
        $order = new JntOrder;
        $order->forceFill(['id' => 'test-uuid', 'order_id' => 'ORDER123']);

        $event = JntOrderStatusChanged::fromOrder($order, TrackingStatus::InTransit, 'pending');

        expect($event->previousStatusCode)->toBe('pending');
    });

    it('exposes status predicates through the canonical event', function (): void {
        $order = new JntOrder;
        $order->forceFill(['id' => 'test-uuid', 'order_id' => 'ORDER123']);

        expect(JntOrderStatusChanged::fromOrder($order, TrackingStatus::Delivered)->isDelivered())->toBeTrue()
            ->and(JntOrderStatusChanged::fromOrder($order, TrackingStatus::Exception)->hasException())->toBeTrue()
            ->and(JntOrderStatusChanged::fromOrder($order, TrackingStatus::Returned)->isReturning())->toBeTrue()
            ->and(JntOrderStatusChanged::fromOrder($order, TrackingStatus::DeliveryAttempted)->requiresAttention())->toBeTrue()
            ->and(JntOrderStatusChanged::fromOrder($order, TrackingStatus::Delivered)->isTerminal())->toBeTrue()
            ->and(JntOrderStatusChanged::fromOrder($order, TrackingStatus::InTransit)->isTerminal())->toBeFalse();
    });

    it('returns the public order and tracking identifiers', function (): void {
        $order = new JntOrder;
        $order->forceFill([
            'id' => 'test-uuid',
            'order_id' => 'ORDER123',
            'tracking_number' => 'JNT123456',
        ]);

        $event = JntOrderStatusChanged::fromOrder($order, TrackingStatus::PickedUp);

        expect($event->getOrderId())->toBe('ORDER123')
            ->and($event->getTrackingNumber())->toBe('JNT123456');
    });

    it('does not resolve a cross-tenant order from tampered event ownership', function (): void {
        config()->set('jnt.owner.enabled', true);
        config()->set('jnt.owner.include_global', false);

        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'evt-owner-a@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'evt-owner-b@example.com',
            'password' => 'secret',
        ]);

        $order = OwnerContext::withOwner($ownerA, fn (): JntOrder => JntOrder::query()->create([
            'order_id' => 'ORD-EVT-A',
            'customer_code' => 'CUST',
        ]));

        $tamperedEvent = new JntOrderStatusChanged(
            orderKey: (string) $order->getKey(),
            orderReference: $order->order_id,
            trackingNumber: $order->tracking_number,
            ownerType: $ownerB->getMorphClass(),
            ownerId: $ownerB->getKey(),
            currentStatus: TrackingStatus::InTransit,
        );

        expect($tamperedEvent->resolveOrder())->toBeNull();
    });
});
