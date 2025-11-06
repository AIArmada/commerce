<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Notifications\OrderDeliveredNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

test('OrderDeliveredNotification → it uses mail and database channels', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderDeliveredNotification($tracking);

    expect($notification->via(new stdClass()))->toBe(['mail', 'database']);
});

test('OrderDeliveredNotification → it generates correct mail message', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 15:30:00',
                description: 'Delivered',
                scanTypeCode: 'D',
                scanTypeName: 'Delivered',
                scanType: 'DELIVER',
                staffName: 'John Delivery',
                scanNetworkProvince: 'Wilayah Persekutuan',
                scanNetworkCity: 'Kuala Lumpur',
                signaturePictureUrl: 'https://example.com/signature.jpg',
            ),
        ],
        orderId: 'ORDER123',
    );

    $notification = new OrderDeliveredNotification($tracking);
    $mail = $notification->toMail(new stdClass());

    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toBe('Your Order Has Been Delivered')
        ->and($mail->greeting)->toBe('Great news!')
        ->and($mail->introLines)->toContain('Your order has been successfully delivered.')
        ->and($mail->introLines)->toContain('Tracking Number: TRACK123')
        ->and($mail->introLines)->toContain('Order ID: ORDER123')
        ->and($mail->introLines)->toContain('Delivered At: 2025-01-01 15:30:00')
        ->and($mail->introLines)->toContain('Delivery Location: Kuala Lumpur, Wilayah Persekutuan')
        ->and($mail->introLines)->toContain('Delivered By: John Delivery')
        ->and($mail->introLines)->toContain('Signature Available: Yes');
});

test('OrderDeliveredNotification → it handles tracking without order ID', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
        orderId: null,
    );

    $notification = new OrderDeliveredNotification($tracking);
    $mail = $notification->toMail(new stdClass());

    expect($mail->introLines)->toContain('Tracking Number: TRACK123')
        ->and($mail->introLines)->not->toContain('Order ID:');
});

test('OrderDeliveredNotification → it handles tracking without delivery details', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderDeliveredNotification($tracking);
    $mail = $notification->toMail(new stdClass());

    expect($mail->introLines)->not->toContain('Delivered At:')
        ->and($mail->introLines)->not->toContain('Delivery Location:')
        ->and($mail->introLines)->not->toContain('Delivered By:')
        ->and($mail->introLines)->not->toContain('Signature Available:');
});

test('OrderDeliveredNotification → it generates correct array representation', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 15:30:00',
                description: 'Delivered',
                scanTypeCode: 'D',
                scanTypeName: 'Delivered',
                scanType: 'DELIVER',
                staffName: 'John Delivery',
                scanNetworkProvince: 'Wilayah Persekutuan',
                scanNetworkCity: 'Kuala Lumpur',
                signaturePictureUrl: 'https://example.com/signature.jpg',
            ),
        ],
        orderId: 'ORDER123',
    );

    $notification = new OrderDeliveredNotification($tracking);
    $array = $notification->toArray(new stdClass());

    expect($array)->toBe([
        'type' => 'order_delivered',
        'tracking_number' => 'TRACK123',
        'order_id' => 'ORDER123',
        'delivery_time' => '2025-01-01 15:30:00',
        'delivery_location' => 'Kuala Lumpur, Wilayah Persekutuan',
        'delivered_by' => 'John Delivery',
        'has_signature' => true,
        'message' => 'Your order has been successfully delivered.',
    ]);
});

test('OrderDeliveredNotification → it implements ShouldQueue', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderDeliveredNotification($tracking);

    expect($notification)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});
