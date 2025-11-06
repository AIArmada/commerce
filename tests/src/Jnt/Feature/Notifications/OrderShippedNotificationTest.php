<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Notifications\OrderShippedNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

test('OrderShippedNotification → it uses mail and database channels', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderShippedNotification($tracking);

    expect($notification->via(new stdClass()))->toBe(['mail', 'database']);
});

test('OrderShippedNotification → it generates correct mail message', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 10:00:00',
                description: 'In transit',
                scanTypeCode: 'T',
                scanTypeName: 'Transfer',
                scanType: 'TRANSFER',
                scanNetworkProvince: 'Wilayah Persekutuan',
                scanNetworkCity: 'Kuala Lumpur',
            ),
        ],
        orderId: 'ORDER123',
    );

    $notification = new OrderShippedNotification($tracking, '2025-01-05');
    $mail = $notification->toMail(new stdClass());

    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toBe('Your Order Has Been Shipped')
        ->and($mail->greeting)->toBe('Good news!')
        ->and($mail->introLines)->toContain('Your order has been shipped and is on its way to you.')
        ->and($mail->introLines)->toContain('Tracking Number: TRACK123')
        ->and($mail->introLines)->toContain('Order ID: ORDER123')
        ->and($mail->introLines)->toContain('Estimated Delivery: 2025-01-05')
        ->and($mail->introLines)->toContain('Current Location: Kuala Lumpur, Wilayah Persekutuan');
});

test('OrderShippedNotification → it handles tracking without order ID', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
        orderId: null,
    );

    $notification = new OrderShippedNotification($tracking);
    $mail = $notification->toMail(new stdClass());

    expect($mail->introLines)->toContain('Tracking Number: TRACK123')
        ->and($mail->introLines)->not->toContain('Order ID:');
});

test('OrderShippedNotification → it handles tracking without estimated delivery', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderShippedNotification($tracking);
    $mail = $notification->toMail(new stdClass());

    expect($mail->introLines)->not->toContain('Estimated Delivery:');
});

test('OrderShippedNotification → it generates correct array representation', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 10:00:00',
                description: 'In transit',
                scanTypeCode: 'T',
                scanTypeName: 'Transfer',
                scanType: 'TRANSFER',
                scanNetworkProvince: 'Wilayah Persekutuan',
                scanNetworkCity: 'Kuala Lumpur',
            ),
        ],
        orderId: 'ORDER123',
    );

    $notification = new OrderShippedNotification($tracking, '2025-01-05');
    $array = $notification->toArray(new stdClass());

    expect($array)->toBe([
        'type' => 'order_shipped',
        'tracking_number' => 'TRACK123',
        'order_id' => 'ORDER123',
        'estimated_delivery' => '2025-01-05',
        'current_location' => 'Kuala Lumpur, Wilayah Persekutuan',
        'message' => 'Your order has been shipped and is on its way to you.',
    ]);
});

test('OrderShippedNotification → it implements ShouldQueue', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderShippedNotification($tracking);

    expect($notification)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});
