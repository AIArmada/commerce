<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Carbon\Carbon;

uses(CashierChipTestCase::class);

beforeEach(function () {
    $this->user = $this->createUser();
});

it('can create a subscription', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
    ]);

    expect($subscription)->toBeInstanceOf(Subscription::class);
    expect($subscription->type)->toBe('default');
    expect($subscription->chip_status)->toBe(Subscription::STATUS_ACTIVE);
});

it('can determine if subscription is active', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    expect($subscription->active())->toBeTrue();
    expect($subscription->valid())->toBeTrue();
});

it('can determine if subscription is on trial', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_TRIALING,
        'chip_price' => 'price_monthly',
        'trial_ends_at' => Carbon::now()->addDays(14),
    ]);

    expect($subscription->onTrial())->toBeTrue();
    expect($subscription->valid())->toBeTrue();
});

it('can determine if subscription trial has expired', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'trial_ends_at' => Carbon::now()->subDays(1),
    ]);

    expect($subscription->onTrial())->toBeFalse();
    expect($subscription->hasExpiredTrial())->toBeTrue();
});

it('can determine if subscription is canceled', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'ends_at' => Carbon::now()->addDays(7),
    ]);

    expect($subscription->canceled())->toBeTrue();
    expect($subscription->onGracePeriod())->toBeTrue();
});

it('can determine if subscription has ended', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_CANCELED,
        'chip_price' => 'price_monthly',
        'ends_at' => Carbon::now()->subDays(1),
    ]);

    expect($subscription->ended())->toBeTrue();
    expect($subscription->valid())->toBeFalse();
});

it('can determine if subscription is incomplete', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_INCOMPLETE,
        'chip_price' => 'price_monthly',
    ]);

    expect($subscription->incomplete())->toBeTrue();
});

it('can determine if subscription is past due', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_PAST_DUE,
        'chip_price' => 'price_monthly',
    ]);

    expect($subscription->pastDue())->toBeTrue();
    expect($subscription->hasIncompletePayment())->toBeTrue();
});

it('can determine if subscription is recurring', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    expect($subscription->recurring())->toBeTrue();
});

it('can cancel subscription at period end', function () {
    $nextBilling = Carbon::now()->addMonth();
    
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'next_billing_at' => $nextBilling,
    ]);

    $subscription->cancel();

    expect($subscription->canceled())->toBeTrue();
    expect($subscription->ends_at->toDateString())->toBe($nextBilling->toDateString());
});

it('can cancel subscription immediately', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $subscription->cancelNow();

    expect($subscription->chip_status)->toBe(Subscription::STATUS_CANCELED);
    expect($subscription->ended())->toBeTrue();
});

it('can resume a canceled subscription on grace period', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'ends_at' => Carbon::now()->addDays(7),
    ]);

    $subscription->resume();

    expect($subscription->ends_at)->toBeNull();
    expect($subscription->chip_status)->toBe(Subscription::STATUS_ACTIVE);
});

it('cannot resume a subscription that has ended', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_CANCELED,
        'chip_price' => 'price_monthly',
        'ends_at' => Carbon::now()->subDays(1),
    ]);

    $subscription->resume();
})->throws(LogicException::class);

it('can skip trial', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_TRIALING,
        'chip_price' => 'price_monthly',
        'trial_ends_at' => Carbon::now()->addDays(14),
    ]);

    $subscription->skipTrial();

    expect($subscription->trial_ends_at)->toBeNull();
});

it('can extend trial', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_TRIALING,
        'chip_price' => 'price_monthly',
        'trial_ends_at' => Carbon::now()->addDays(14),
    ]);

    $newTrialEnd = Carbon::now()->addDays(30);
    $subscription->extendTrial($newTrialEnd);

    expect($subscription->trial_ends_at->toDateString())->toBe($newTrialEnd->toDateString());
});

it('cannot extend trial to past date', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_TRIALING,
        'chip_price' => 'price_monthly',
        'trial_ends_at' => Carbon::now()->addDays(14),
    ]);

    $subscription->extendTrial(Carbon::now()->subDays(1));
})->throws(InvalidArgumentException::class);

it('can update quantity', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'quantity' => 1,
    ]);

    $subscription->items()->create([
        'chip_id' => 'item-1',
        'chip_price' => 'price_monthly',
        'quantity' => 1,
    ]);

    $subscription->updateQuantity(5);

    expect($subscription->quantity)->toBe(5);
});

it('can increment quantity', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'quantity' => 2,
    ]);

    $subscription->items()->create([
        'chip_id' => 'item-1',
        'chip_price' => 'price_monthly',
        'quantity' => 2,
    ]);

    $subscription->incrementQuantity();

    expect($subscription->quantity)->toBe(3);
});

it('can decrement quantity', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'quantity' => 5,
    ]);

    $subscription->items()->create([
        'chip_id' => 'item-1',
        'chip_price' => 'price_monthly',
        'quantity' => 5,
    ]);

    $subscription->decrementQuantity(2);

    expect($subscription->quantity)->toBe(3);
});

it('can check for specific price', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    expect($subscription->hasPrice('price_monthly'))->toBeTrue();
    expect($subscription->hasPrice('price_yearly'))->toBeFalse();
});

it('can get owner', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    expect($subscription->owner)->toBeInstanceOf(User::class);
    expect($subscription->owner->id)->toBe($this->user->id);
});

it('has subscription items relationship', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $subscription->items()->create([
        'chip_id' => 'item-1',
        'chip_product' => 'prod_123',
        'chip_price' => 'price_monthly',
        'quantity' => 1,
        'unit_amount' => 9900,
    ]);

    expect($subscription->items)->toHaveCount(1);
    expect($subscription->items->first())->toBeInstanceOf(SubscriptionItem::class);
});

it('can swap prices', function () {
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $subscription->items()->create([
        'chip_id' => 'item-1',
        'chip_price' => 'price_monthly',
        'quantity' => 1,
    ]);

    $subscription->swap('price_yearly');

    expect($subscription->chip_price)->toBe('price_yearly');
});
