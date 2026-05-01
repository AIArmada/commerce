<?php

declare(strict_types=1);

use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Payment;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

uses(CashierChipTestCase::class);

it('can create payment succeeded event', function (): void {
    $billable = new User(['id' => 1, 'name' => 'Test']);
    $purchase = ['id' => 'test-purchase'];

    $event = new PaymentSucceeded($billable, $purchase);

    expect($event->billable)->toBe($billable);
    expect($event->purchase)->toBe($purchase);
});

it('can create payment failed event', function (): void {
    $billable = new User(['id' => 1, 'name' => 'Test']);
    $purchase = ['id' => 'test-purchase', 'error' => 'declined'];

    $event = new PaymentFailed($billable, $purchase);

    expect($event->billable)->toBe($billable);
    expect($event->purchase)->toBe($purchase);
});

it('can create subscription created event', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
    ]);

    $event = new SubscriptionCreated($subscription);

    expect($event->subscription)->toBe($subscription);
});

it('can create subscription canceled event', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
    ]);

    $event = new SubscriptionCanceled($subscription);

    expect($event->subscription)->toBe($subscription);
});

it('can create subscription renewal failed event', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
    ]);
    $event = new SubscriptionRenewalFailed($subscription, 'Payment declined');

    expect($event->subscription)->toBe($subscription);
    expect($event->reason)->toBe('Payment declined');
});

it('can create subscription renewed event', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
    ]);
    $payment = new Payment(PurchaseData::from([
        'id' => 'test-purchase',
        'purchase' => ['total' => 1000],
    ]));

    $event = new SubscriptionRenewed($subscription, $payment);

    expect($event->subscription)->toBe($subscription);
    expect($event->payment)->toBe($payment);
});
