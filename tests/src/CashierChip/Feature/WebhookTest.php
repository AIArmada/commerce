<?php

declare(strict_types=1);

use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Event;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    $this->user = $this->createUser([
        'chip_id' => 'test-client-id',
    ]);
});

it('dispatches PaymentSucceeded on purchase.paid', function (): void {
    Event::fake([PaymentSucceeded::class]);

    $purchaseData = [
        'id' => 'test-purchase-id',
        'client_id' => 'test-client-id',
        'status' => 'paid',
        'purchase' => ['total' => 10000, 'currency' => 'MYR'],
    ];

    PurchasePaid::dispatch(PurchaseData::from($purchaseData), $purchaseData);

    Event::assertDispatched(PaymentSucceeded::class, function ($event) {
        return $event->billable->id === $this->user->id;
    });
});

it('dispatches PaymentFailed on purchase.payment_failure', function (): void {
    Event::fake([PaymentFailed::class]);

    $purchaseData = [
        'id' => 'test-purchase-id',
        'client_id' => 'test-client-id',
        'status' => 'failed',
        'purchase' => ['total' => 10000, 'currency' => 'MYR'],
    ];

    PurchasePaymentFailure::dispatch(PurchaseData::from($purchaseData), $purchaseData);

    Event::assertDispatched(PaymentFailed::class, function ($event) {
        return $event->billable->id === $this->user->id;
    });
});

it('stores recurring token from webhook when no default payment method', function (): void {
    expect($this->user->default_pm_id)->toBeNull();

    $purchaseData = [
        'id' => 'test-purchase-id',
        'client_id' => 'test-client-id',
        'status' => 'paid',
        'recurring_token' => 'new-recurring-token',
        'card' => ['brand' => 'Visa', 'last_4' => '4242'],
        'purchase' => ['total' => 10000, 'currency' => 'MYR'],
    ];

    PurchasePaid::dispatch(PurchaseData::from($purchaseData), $purchaseData);

    $this->user->refresh();

    expect($this->user->default_pm_id)->toBe('new-recurring-token');
    expect($this->user->pm_type)->toBe('Visa');
    expect($this->user->pm_last_four)->toBe('4242');
});

it('updates subscription to active on payment success', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_PAST_DUE,
        'chip_price' => 'price_monthly',
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
    ]);

    $purchaseData = [
        'id' => 'test-purchase-id',
        'client_id' => 'test-client-id',
        'status' => 'paid',
        'metadata' => ['subscription_type' => 'standard'],
        'purchase' => ['total' => 10000, 'currency' => 'MYR'],
    ];

    PurchasePaid::dispatch(PurchaseData::from($purchaseData), $purchaseData);

    $subscription = $this->user->subscription('standard');

    expect($subscription->chip_status)->toBe(Subscription::STATUS_ACTIVE);
    expect($subscription->next_billing_at)->not->toBeNull();
});

it('updates subscription to past due on payment failure', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $purchaseData = [
        'id' => 'test-purchase-id',
        'client_id' => 'test-client-id',
        'status' => 'failed',
        'metadata' => ['subscription_type' => 'standard'],
        'purchase' => ['total' => 10000, 'currency' => 'MYR'],
    ];

    PurchasePaymentFailure::dispatch(PurchaseData::from($purchaseData), $purchaseData);

    $subscription = $this->user->subscription('standard');

    expect($subscription->chip_status)->toBe(Subscription::STATUS_PAST_DUE);
});

it('handles missing client id gracefully', function (): void {
    Event::fake([PaymentSucceeded::class]);

    $purchaseData = [
        'id' => 'test-purchase-id',
        'status' => 'paid',
        // No client_id — listener bails early
    ];

    PurchasePaid::dispatch(PurchaseData::from($purchaseData), $purchaseData);

    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('handles non-existent billable gracefully', function (): void {
    Event::fake([PaymentSucceeded::class]);

    $purchaseData = [
        'id' => 'test-purchase-id',
        'client_id' => 'non-existent-chip-id',
        'status' => 'paid',
        'purchase' => ['total' => 10000, 'currency' => 'MYR'],
    ];

    PurchasePaid::dispatch(PurchaseData::from($purchaseData), $purchaseData);

    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('resolves billable by owner context when chip_id is duplicated across tenants', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);
    config()->set('cashier-chip.features.owner.validate_billable_owner', true);

    $ownerB = User::query()->create([
        'name' => 'Owner B Duplicate',
        'email' => 'cashier-chip-owner-b-dup@example.com',
        'chip_id' => 'duplicated-client-id',
    ]);

    $ownerA = User::query()->create([
        'name' => 'Owner A Duplicate',
        'email' => 'cashier-chip-owner-a-dup@example.com',
        'chip_id' => 'duplicated-client-id',
    ]);

    $purchaseData = [
        'id' => 'test-purchase-id-owner-a',
        'client_id' => 'duplicated-client-id',
        'status' => 'paid',
        'recurring_token' => 'tok_owner_a_only',
        'card' => ['brand' => 'Visa', 'last_4' => '4242'],
        'purchase' => ['total' => 10000, 'currency' => 'MYR'],
    ];

    $purchase = PurchaseData::from($purchaseData);

    // Dispatch within ownerA context — only ownerA's billable should be updated
    OwnerContext::withOwner($ownerA, function () use ($purchase, $purchaseData): void {
        PurchasePaid::dispatch($purchase, $purchaseData);
    });

    expect($ownerA->fresh()?->default_pm_id)->toBe('tok_owner_a_only');
    expect($ownerB->fresh()?->default_pm_id)->toBeNull();
});
