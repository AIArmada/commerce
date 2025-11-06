<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Notifications\OrderProblemNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

test('OrderProblemNotification → it uses mail and database channels', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderProblemNotification($tracking);

    expect($notification->via(new stdClass()))->toBe(['mail', 'database']);
});

test('OrderProblemNotification → it generates correct mail message', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 12:00:00',
                description: 'Return to sender',
                scanTypeCode: 'R',
                scanTypeName: 'Return',
                scanType: 'RETURN',
                remark: 'Recipient not available at address',
                problemType: 'Address Issue',
            ),
        ],
        orderId: 'ORDER123',
    );

    $notification = new OrderProblemNotification($tracking, 'support@example.com');
    $mail = $notification->toMail(new stdClass());

    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toBe('Issue with Your Order')
        ->and($mail->greeting)->toBe('Attention Required')
        ->and($mail->introLines)->toContain('There is an issue with your order that requires attention.')
        ->and($mail->introLines)->toContain('Tracking Number: TRACK123')
        ->and($mail->introLines)->toContain('Order ID: ORDER123')
        ->and($mail->introLines)->toContain('Issue: Return to sender')
        ->and($mail->introLines)->toContain('Problem Type: Address Issue')
        ->and($mail->introLines)->toContain('Details: Recipient not available at address')
        ->and($mail->introLines)->toContain('Reported At: 2025-01-01 12:00:00')
        ->and($mail->introLines)->toContain('For assistance, please contact: support@example.com');
});

test('OrderProblemNotification → it handles tracking without order ID', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
        orderId: null,
    );

    $notification = new OrderProblemNotification($tracking);
    $mail = $notification->toMail(new stdClass());

    expect($mail->introLines)->toContain('Tracking Number: TRACK123')
        ->and($mail->introLines)->not->toContain('Order ID:');
});

test('OrderProblemNotification → it handles tracking without support contact', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderProblemNotification($tracking);
    $mail = $notification->toMail(new stdClass());

    expect($mail->introLines)->not->toContain('For assistance, please contact:');
});

test('OrderProblemNotification → it handles tracking without problem details', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderProblemNotification($tracking);
    $mail = $notification->toMail(new stdClass());

    expect($mail->introLines)->not->toContain('Issue:')
        ->and($mail->introLines)->not->toContain('Problem Type:')
        ->and($mail->introLines)->not->toContain('Details:')
        ->and($mail->introLines)->not->toContain('Reported At:');
});

test('OrderProblemNotification → it generates correct array representation', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [
            new TrackingDetailData(
                scanTime: '2025-01-01 12:00:00',
                description: 'Return to sender',
                scanTypeCode: 'R',
                scanTypeName: 'Return',
                scanType: 'RETURN',
                remark: 'Recipient not available at address',
                problemType: 'Address Issue',
            ),
        ],
        orderId: 'ORDER123',
    );

    $notification = new OrderProblemNotification($tracking, 'support@example.com');
    $array = $notification->toArray(new stdClass());

    expect($array)->toBe([
        'type' => 'order_problem',
        'tracking_number' => 'TRACK123',
        'order_id' => 'ORDER123',
        'problem_description' => 'Return to sender',
        'problem_type' => 'Address Issue',
        'problem_details' => 'Recipient not available at address',
        'reported_at' => '2025-01-01 12:00:00',
        'support_contact' => 'support@example.com',
        'message' => 'There is an issue with your order that requires attention.',
    ]);
});

test('OrderProblemNotification → it implements ShouldQueue', function (): void {
    $tracking = new TrackingData(
        trackingNumber: 'TRACK123',
        details: [],
    );

    $notification = new OrderProblemNotification($tracking);

    expect($notification)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});
