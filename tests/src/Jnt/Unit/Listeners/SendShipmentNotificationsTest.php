<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Features\Notifications\Actions\NotifyShipmentStatusChanged;
use AIArmada\Jnt\Features\Notifications\EntryPoints\Listeners\SendShipmentNotifications;
use AIArmada\Jnt\Features\Orders\Api\NotificationOrder;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Tracking\Actions\JntTrackingService;
use Illuminate\Support\Facades\Notification;

describe('SendShipmentNotifications listener', function (): void {
    beforeEach(function (): void {
        config(['jnt.notifications.enabled' => true]);
        config(['jnt.notifications.queue' => false]);
    });

    it('can be instantiated', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications(new NotifyShipmentStatusChanged($trackingService));

        expect($listener)->toBeInstanceOf(SendShipmentNotifications::class);
    });

    it('should queue based on config', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications(new NotifyShipmentStatusChanged($trackingService));

        $order = new JntOrder;
        $order->forceFill(['id' => 'test-id', 'order_id' => 'ORDER123']);

        $event = new JntOrderStatusChanged((string) $order->getKey(), $order->order_id, $order->tracking_number, $order->owner_type, $order->owner_id, TrackingStatus::Delivered);

        config(['jnt.notifications.queue' => true]);
        expect($listener->shouldQueue($event))->toBeTrue();

        config(['jnt.notifications.queue' => false]);
        expect($listener->shouldQueue($event))->toBeFalse();
    });

    it('skips processing when notifications disabled', function (): void {
        Notification::fake();
        config(['jnt.notifications.enabled' => false]);

        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications(new NotifyShipmentStatusChanged($trackingService));

        $order = new JntOrder;
        $order->forceFill(['id' => 'test-id', 'order_id' => 'ORDER123']);

        $event = new JntOrderStatusChanged((string) $order->getKey(), $order->order_id, $order->tracking_number, $order->owner_type, $order->owner_id, TrackingStatus::Delivered);

        $listener->handle($event);

        Notification::assertNothingSent();
    });

    it('does not query carrier tracking for pending status notifications', function (): void {
        Notification::fake();

        $trackingService = Mockery::mock(JntTrackingService::class);
        $trackingService->shouldNotReceive('track');
        $listener = new SendShipmentNotifications(new NotifyShipmentStatusChanged($trackingService));

        $order = JntOrder::query()->create([
            'order_id' => 'ORDER-PENDING',
            'customer_code' => 'CUST',
            'metadata' => ['notification_email' => 'test@example.com'],
        ]);

        $listener->handle(new JntOrderStatusChanged((string) $order->getKey(), $order->order_id, $order->tracking_number, $order->owner_type, $order->owner_id, TrackingStatus::Pending));

        Notification::assertNothingSent();
    });

    it('skips processing when notifiable cannot be resolved', function (): void {
        Notification::fake();

        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications(new NotifyShipmentStatusChanged($trackingService));

        $order = JntOrder::query()->create([
            'order_id' => 'ORDER123',
            'customer_code' => 'CUST',
            'metadata' => [],
        ]);

        $event = new JntOrderStatusChanged((string) $order->getKey(), $order->order_id, $order->tracking_number, $order->owner_type, $order->owner_id, TrackingStatus::Delivered);

        $listener->handle($event);

        Notification::assertNothingSent();
    });

    it('resolves notifiable from metadata email', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new NotifyShipmentStatusChanged($trackingService);

        $reflection = new ReflectionClass($listener);
        $method = $reflection->getMethod('resolveNotifiable');

        $order = new JntOrder;
        $order->forceFill([
            'id' => 'test-id',
            'order_id' => 'ORDER123',
            'metadata' => ['notification_email' => 'test@example.com'],
        ]);

        $notifiable = $method->invoke($listener, new NotificationOrder('ORDER123', null, null, 'test@example.com'), null);

        expect($notifiable)->not->toBeNull();
        expect($notifiable->email)->toBe('test@example.com');
        expect($notifiable->routeNotificationForMail())->toBe(['mail' => 'test@example.com']);
    });

    it('returns null notification when tracking fails', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $trackingService->shouldReceive('track')
            ->andThrow(new Exception('Not found'));

        $listener = new NotifyShipmentStatusChanged($trackingService);

        $reflection = new ReflectionClass($listener);
        $method = $reflection->getMethod('createNotification');

        $order = JntOrder::query()->create([
            'order_id' => 'ORDER123',
            'customer_code' => 'CUST',
            'tracking_number' => 'JNT123',
        ]);

        $event = new JntOrderStatusChanged((string) $order->getKey(), $order->order_id, $order->tracking_number, $order->owner_type, $order->owner_id, TrackingStatus::PickedUp);

        $notification = $method->invoke($listener, $event, new NotificationOrder('ORDER123', 'JNT123', null, null));

        // Since tracking fails, notification will be null
        expect($notification)->toBeNull();
    });

    it('calculates estimated delivery from config', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new NotifyShipmentStatusChanged($trackingService);

        $reflection = new ReflectionClass($listener);
        $method = $reflection->getMethod('getEstimatedDelivery');

        config(['jnt.shipping.default_estimated_days' => 5]);

        $order = new JntOrder;
        $order->forceFill(['id' => 'test-id']);

        $result = $method->invoke($listener);

        expect($result)->toBe(now()->addDays(5)->format('Y-m-d'));
    });
});
