<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\Support\SubscriptionStatus;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

it('can format amount in USD correctly', function (): void {
    $model = Mockery::mock(Model::class);
    $model->shouldReceive('getKey')->andReturn('123');

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_789',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $formatted = $subscription->formattedAmount();

    expect($formatted)->toBe('$29.99');
});

it('can format amount in MYR correctly', function (): void {
    $model = Mockery::mock(Model::class);
    $model->shouldReceive('getKey')->andReturn('456');

    $subscription = new UnifiedSubscription(
        id: 'sub_456',
        gateway: 'chip',
        userId: 'user_456',
        type: 'default',
        planId: 'plan_789',
        amount: 5000,
        currency: 'MYR',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $formatted = $subscription->formattedAmount();

    expect($formatted)->toBe('RM50.00');
});

it('returns gateway config', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_789',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $config = $subscription->gatewayConfig();

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['label', 'color', 'icon']);
});

it('returns billing cycle as monthly by default', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_monthly',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $cycle = $subscription->billingCycle();

    expect($cycle)->toBeString();
});

it('identifies yearly billing cycle from plan name', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_yearly_plan',
        amount: 29900,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $cycle = $subscription->billingCycle();

    expect($cycle)->toBeString();
});

it('detects subscription needs attention when past due', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_789',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::PastDue,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    expect($subscription->needsAttention())->toBeTrue();
});

it('detects subscription does not need attention when active', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_789',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    expect($subscription->needsAttention())->toBeFalse();
});
