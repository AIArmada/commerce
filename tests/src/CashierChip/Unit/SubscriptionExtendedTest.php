<?php

declare(strict_types=1);

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

uses(CashierChipTestCase::class);

describe('SubscriptionExtended', function (): void {
    it('has multiple prices', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => null,
        ]);

        $this->assertTrue($subscription->hasMultiplePrices());
    });

    it('has single price', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_123',
        ]);

        $this->assertTrue($subscription->hasSinglePrice());
        $this->assertFalse($subscription->hasMultiplePrices());
    });

    it('has price with single price', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_123',
        ]);

        $this->assertTrue($subscription->hasPrice('price_123'));
        $this->assertFalse($subscription->hasPrice('price_456'));
    });

    it('incomplete', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Incomplete,
        ]);

        $this->assertTrue($subscription->incomplete());
    });

    it('past due', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::PastDue,
        ]);

        $this->assertTrue($subscription->pastDue());
    });

    it('recurring', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        $this->assertTrue($subscription->recurring());
    });

    it('not recurring when on trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $this->assertFalse($subscription->recurring());
    });

    it('not recurring when canceled', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
            'ends_at' => Carbon::now()->addDays(7),
        ]);

        $this->assertFalse($subscription->recurring());
    });

    it('has expired trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->subDay(),
        ]);

        $this->assertTrue($subscription->hasExpiredTrial());
    });

    it('on grace period', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertTrue($subscription->onGracePeriod());
    });

    it('not on grace period', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);

        $this->assertFalse($subscription->onGracePeriod());
    });

    it('skip trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $subscription->skipTrial();

        $this->assertNull($subscription->trial_ends_at);
    });

    it('end trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $subscription->endTrial();

        $this->assertNull($subscription->fresh()->trial_ends_at);
    });

    it('end trial does nothing without trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => null,
        ]);

        $result = $subscription->endTrial();

        $this->assertSame($subscription, $result);
    });

    it('extend trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $newDate = Carbon::now()->addDays(30);
        $subscription->extendTrial($newDate);

        $this->assertEquals($newDate->toDateTimeString(), $subscription->fresh()->trial_ends_at->toDateTimeString());
    });

    it('extend trial throws for past date', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $subscription->extendTrial(Carbon::now()->subDay());
    })->throws(InvalidArgumentException::class, 'date in the future');

    it('scope incomplete', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Incomplete,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $this->assertEquals(1, Subscription::query()->incomplete()->count());
    });

    it('scope past due', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::PastDue,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $this->assertEquals(1, Subscription::query()->pastDue()->count());
    });

    it('scope canceled', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->subDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);

        $this->assertEquals(1, Subscription::query()->canceled()->count());
    });

    it('scope not canceled', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->subDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);

        $this->assertEquals(1, Subscription::query()->notCanceled()->count());
    });

    it('scope ended', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->subDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertEquals(1, Subscription::query()->ended()->count());
    });

    it('scope on trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => null,
        ]);

        $this->assertEquals(1, Subscription::query()->onTrial()->count());
    });

    it('scope expired trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->subDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertEquals(1, Subscription::query()->expiredTrial()->count());
    });

    it('scope recurring', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDay(),
            'ends_at' => null,
        ]);

        $this->assertEquals(1, Subscription::query()->recurring()->count());
    });

    it('scope on grace period', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->addDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);

        $this->assertEquals(1, Subscription::query()->onGracePeriod()->count());
    });

    it('user relation', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertInstanceOf(BelongsTo::class, $subscription->user());
    });

    it('items relation', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertInstanceOf(HasMany::class, $subscription->items());
    });

    it('get table', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertStringContainsString('subscriptions', $subscription->getTable());
    });

    it('valid when active', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
            'ends_at' => null,
        ]);

        $this->assertTrue($subscription->valid());
    });

    it('valid when on trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $this->assertTrue($subscription->valid());
    });

    it('valid when on grace period', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertTrue($subscription->valid());
    });

    it('invalid when ended', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Canceled,
            'ends_at' => Carbon::now()->subDay(),
        ]);

        $this->assertFalse($subscription->valid());
    });

    it('calculate subscription amount', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        // Add an item
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_123',
            'unit_amount' => 1000,
            'quantity' => 2,
        ]);

        $subscription->refresh();
        $amount = $subscription->calculateSubscriptionAmount();

        $this->assertEquals(2000, $amount);
    });

    it('cancel', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => Carbon::now()->addDays(15),
        ]);

        $subscription->cancel();

        $this->assertNotNull($subscription->ends_at);
    });

    it('cancel on trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $trialEnd = Carbon::now()->addDays(7);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => $trialEnd,
        ]);

        $subscription->cancel();

        $this->assertEquals($trialEnd->toDateTimeString(), $subscription->ends_at->toDateTimeString());
    });

    it('cancel now', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $subscription->cancelNow();

        $this->assertEquals(SubscriptionStatus::Canceled, $subscription->chip_status);
        $this->assertNotNull($subscription->ends_at);
    });

    it('mark as canceled', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $subscription->markAsCanceled();

        $this->assertEquals(SubscriptionStatus::Canceled, $subscription->fresh()->chip_status);
    });

    it('resume', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $subscription->resume();

        $this->assertEquals(SubscriptionStatus::Active, $subscription->chip_status);
        $this->assertNull($subscription->ends_at);
    });

    it('resume throws not on grace period', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Active,
            'ends_at' => null,
        ]);

        $subscription->resume();
    })->throws(LogicException::class);

    it('has incomplete payment', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::PastDue,
        ]);

        $this->assertTrue($subscription->hasIncompletePayment());
    });

    it('has incomplete payment with incomplete status', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => SubscriptionStatus::Incomplete,
        ]);

        $this->assertTrue($subscription->hasIncompletePayment());
    });

    it('scope not on trial', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => null,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertEquals(1, Subscription::query()->notOnTrial()->count());
    });

    it('scope not on grace period', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertEquals(1, Subscription::query()->notOnGracePeriod()->count());
    });

    it('cancel at', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $cancelDate = Carbon::now()->addDays(30);
        $subscription->cancelAt($cancelDate);

        $this->assertEquals($cancelDate->toDateTimeString(), $subscription->ends_at->toDateTimeString());
    });

    it('cancel at with timestamp', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $timestamp = Carbon::now()->addDays(30)->timestamp;
        $subscription->cancelAt($timestamp);

        $this->assertNotNull($subscription->ends_at);
    });

    it('set recurring token', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $subscription->setRecurringToken('tok_123');

        $this->assertEquals('tok_123', $subscription->fresh()->recurring_token);
    });
});
