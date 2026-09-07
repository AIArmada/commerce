<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Checkout;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;

uses(CashierChipTestCase::class);

describe('SubscriptionBuilderIntegration', function (): void {
    it('can create subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_integration_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->create();

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals('default', $subscription->type);
        $this->assertEquals('price_monthly_100', $subscription->chip_price);
        $this->assertEquals(SubscriptionStatus::Active, $subscription->chip_status);
    });

    it('can create subscription with trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_trial_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->trialDays(14)
            ->create();

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(SubscriptionStatus::Trialing, $subscription->chip_status);
        $this->assertTrue($subscription->onTrial());
        $this->assertNotNull($subscription->trial_ends_at);
    });

    it('can create subscription skip trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_skip_trial_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->trialDays(14)
            ->skipTrial()
            ->create();

        $this->assertEquals(SubscriptionStatus::Active, $subscription->chip_status);
        $this->assertNull($subscription->trial_ends_at);
    });

    it('can create subscription with trial until', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_trial_until_123']);

        $trialEnd = Carbon::now()->addDays(30);
        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->trialUntil($trialEnd)
            ->create();

        $this->assertTrue($subscription->onTrial());
        $this->assertEquals($trialEnd->toDateTimeString(), $subscription->trial_ends_at->toDateTimeString());
    });

    it('can create subscription with multiple prices', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_multi_price_123']);

        $subscription = $user->newSubscription('default', ['price_monthly_100', 'price_addon_50'])
            ->create();

        $this->assertNull($subscription->chip_price);
        $this->assertTrue($subscription->hasMultiplePrices());
        $this->assertEquals(2, $subscription->items->count());
    });

    it('can create subscription with quantity', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_qty_123']);

        $subscription = $user->newSubscription('default', 'price_per_seat')
            ->quantity(5)
            ->create();

        $this->assertEquals(5, $subscription->quantity);
    });

    it('subscription builder quantity clamps to minimum one', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_qty_clamp_123']);

        $subscription = $user->newSubscription('default', 'price_per_seat')
            ->quantity(0)
            ->create();

        $this->assertEquals(1, $subscription->quantity);
    });

    it('can create subscription with billing interval', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_interval_123']);

        $subscription = $user->newSubscription('default', 'price_custom')
            ->billingInterval('year', 1)
            ->create();

        $this->assertEquals('year', $subscription->billing_interval);
        $this->assertEquals(1, $subscription->billing_interval_count);
    });

    it('can create monthly subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_monthly_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->monthly()
            ->create();

        $this->assertEquals('month', $subscription->billing_interval);
    });

    it('can create yearly subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_yearly_123']);

        $subscription = $user->newSubscription('default', 'price_yearly_1000')
            ->yearly()
            ->create();

        $this->assertEquals('year', $subscription->billing_interval);
    });

    it('can create weekly subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_weekly_123']);

        $subscription = $user->newSubscription('default', 'price_weekly')
            ->weekly()
            ->create();

        $this->assertEquals('week', $subscription->billing_interval);
    });

    it('can create daily subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_daily_123']);

        $subscription = $user->newSubscription('default', 'price_daily')
            ->daily()
            ->create();

        $this->assertEquals('day', $subscription->billing_interval);
    });

    it('can create subscription with metadata', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_meta_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->withMetadata(['campaign' => 'spring_sale'])
            ->create();

        $this->assertInstanceOf(Subscription::class, $subscription);
    });

    it('can create subscription with anchor', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_anchor_123']);

        $anchor = Carbon::now()->endOfMonth();
        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->anchorBillingCycleOn($anchor)
            ->create();

        $this->assertInstanceOf(Subscription::class, $subscription);
    });

    it('can create subscription with recurring token', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_token_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->create('tok_recurring_123');

        $this->assertEquals('tok_recurring_123', $subscription->recurringToken());
    });

    it('add creates subscription without charge', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_add_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->add();

        $this->assertInstanceOf(Subscription::class, $subscription);
    });

    it('checkout returns checkout instance', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_checkout_123']);

        $builder = new SubscriptionBuilder($user, 'default');
        $builder->price(['price' => 'price_monthly_100', 'quantity' => 1, 'unit_amount' => 10000]);

        $checkout = $builder->checkout();

        $this->assertInstanceOf(Checkout::class, $checkout);
    });

    it('checkout with trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_checkout_trial_123']);

        $builder = new SubscriptionBuilder($user, 'default');
        $builder->price(['price' => 'price_monthly_100', 'quantity' => 1, 'unit_amount' => 10000]);
        $builder->trialDays(7);

        $checkout = $builder->checkout();

        $this->assertInstanceOf(Checkout::class, $checkout);
    });

    it('checkout with recurring', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_checkout_recurring_123']);

        $builder = new SubscriptionBuilder($user, 'default');
        $builder->price(['price' => 'price_monthly_100', 'quantity' => 1, 'unit_amount' => 10000]);

        $checkout = $builder->checkout(['success_url' => 'https://example.com/success']);

        $this->assertInstanceOf(Checkout::class, $checkout);
    });

    it('fluent builder', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_fluent_123']);

        $subscription = $user->newSubscription('premium', 'price_premium')
            ->monthly(1)
            ->trialDays(7)
            ->withMetadata(['source' => 'test'])
            ->create('tok_test_123');

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals('premium', $subscription->type);
        $this->assertEquals('month', $subscription->billing_interval);
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals('tok_test_123', $subscription->recurringToken());
    });

    it('creates subscription items', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_items_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->create();

        $this->assertGreaterThan(0, $subscription->items->count());
        $this->assertEquals('price_monthly_100', $subscription->items->first()->chip_price);
    });

    it('subscription has next billing at', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_billing_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->monthly()
            ->create();

        $this->assertNotNull($subscription->next_billing_at);
    });
});
