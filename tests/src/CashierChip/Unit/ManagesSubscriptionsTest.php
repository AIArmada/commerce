<?php

declare(strict_types=1);

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;

uses(CashierChipTestCase::class);

describe('ManagesSubscriptions', function (): void {
    it('new subscription returns builder', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $builder = $user->newSubscription('default', 'price_123');

        $this->assertInstanceOf(SubscriptionBuilder::class, $builder);
    });

    it('on trial returns false without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->onTrial('default'));
    });

    it('on generic trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123', 'trial_ends_at' => Carbon::now()->addDays(7)]);

        $this->assertTrue($user->onGenericTrial());
    });

    it('on generic trial false when expired', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123', 'trial_ends_at' => Carbon::now()->subDay()]);

        $this->assertFalse($user->onGenericTrial());
    });

    it('has expired generic trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123', 'trial_ends_at' => Carbon::now()->subDay()]);

        $this->assertTrue($user->hasExpiredGenericTrial());
    });

    it('has expired trial returns false without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->hasExpiredTrial('default'));
    });

    it('trial ends at returns model trial', function (): void {
        $trialDate = Carbon::now()->addDays(7);
        $user = $this->createUser(['chip_id' => 'cli_123', 'trial_ends_at' => $trialDate]);

        $this->assertEquals($trialDate->toDateTimeString(), $user->trialEndsAt()->toDateTimeString());
    });

    it('subscribed returns false without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->subscribed('default'));
    });

    it('subscription returns null without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertNull($user->subscription('default'));
    });

    it('subscriptions relation', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertInstanceOf(MorphMany::class, $user->subscriptions());
    });

    it('has incomplete payment returns false without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->hasIncompletePayment('default'));
    });

    it('subscribed to product returns false without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->subscribedToProduct('prod_123'));
    });

    it('subscribed to price returns false without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->subscribedToPrice('price_123'));
    });

    it('on product returns false without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->onProduct('prod_123'));
    });

    it('on price returns false without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->onPrice('price_123'));
    });

    it('tax rates returns empty array', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals([], $user->taxRates());
    });

    it('price tax rates returns empty array', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals([], $user->priceTaxRates());
    });

    it('subscribed with active subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'billable')->create([
            'type' => 'default',
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $this->assertTrue($user->subscribed('default'));
    });

    it('has incomplete payment with past due subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'billable')->create([
            'type' => 'default',
            'chip_status' => SubscriptionStatus::PastDue,
        ]);

        $this->assertTrue($user->hasIncompletePayment('default'));
    });
});
