<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;

class SubscriptionExtendedTest extends CashierChipTestCase
{
    public function test_has_multiple_prices(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => null,
        ]);

        $this->assertTrue($subscription->hasMultiplePrices());
    }

    public function test_has_single_price(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_123',
        ]);

        $this->assertTrue($subscription->hasSinglePrice());
        $this->assertFalse($subscription->hasMultiplePrices());
    }

    public function test_has_price_with_single_price(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_123',
        ]);

        $this->assertTrue($subscription->hasPrice('price_123'));
        $this->assertFalse($subscription->hasPrice('price_456'));
    }

    public function test_incomplete(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_INCOMPLETE,
        ]);

        $this->assertTrue($subscription->incomplete());
    }

    public function test_past_due(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ]);

        $this->assertTrue($subscription->pastDue());
    }

    public function test_recurring(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        $this->assertTrue($subscription->recurring());
    }

    public function test_not_recurring_when_on_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $this->assertFalse($subscription->recurring());
    }

    public function test_not_recurring_when_canceled(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'ends_at' => Carbon::now()->addDays(7),
        ]);

        $this->assertFalse($subscription->recurring());
    }

    public function test_has_expired_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->subDay(),
        ]);

        $this->assertTrue($subscription->hasExpiredTrial());
    }

    public function test_on_grace_period(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertTrue($subscription->onGracePeriod());
    }

    public function test_not_on_grace_period(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);

        $this->assertFalse($subscription->onGracePeriod());
    }

    public function test_skip_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $subscription->skipTrial();

        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_end_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $subscription->endTrial();

        $this->assertNull($subscription->fresh()->trial_ends_at);
    }

    public function test_end_trial_does_nothing_without_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => null,
        ]);

        $result = $subscription->endTrial();

        $this->assertSame($subscription, $result);
    }

    public function test_extend_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $newDate = Carbon::now()->addDays(30);
        $subscription->extendTrial($newDate);

        $this->assertEquals($newDate->toDateTimeString(), $subscription->fresh()->trial_ends_at->toDateTimeString());
    }

    public function test_extend_trial_throws_for_past_date(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date in the future');

        $subscription->extendTrial(Carbon::now()->subDay());
    }

    public function test_scope_incomplete(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_INCOMPLETE,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertEquals(1, Subscription::query()->incomplete()->count());
    }

    public function test_scope_past_due(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertEquals(1, Subscription::query()->pastDue()->count());
    }

    public function test_scope_canceled(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->subDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);

        $this->assertEquals(1, Subscription::query()->canceled()->count());
    }

    public function test_scope_not_canceled(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->subDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);

        $this->assertEquals(1, Subscription::query()->notCanceled()->count());
    }

    public function test_scope_ended(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->subDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertEquals(1, Subscription::query()->ended()->count());
    }

    public function test_scope_on_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => null,
        ]);

        $this->assertEquals(1, Subscription::query()->onTrial()->count());
    }

    public function test_scope_expired_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->subDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertEquals(1, Subscription::query()->expiredTrial()->count());
    }

    public function test_scope_recurring(): void
    {
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
    }

    public function test_scope_on_grace_period(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->addDay(),
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);

        $this->assertEquals(1, Subscription::query()->onGracePeriod()->count());
    }

    public function test_user_relation(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertInstanceOf(BelongsTo::class, $subscription->user());
    }

    public function test_items_relation(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertInstanceOf(HasMany::class, $subscription->items());
    }

    public function test_get_table(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertStringContainsString('subscriptions', $subscription->getTable());
    }

    public function test_valid_when_active(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'ends_at' => null,
        ]);

        $this->assertTrue($subscription->valid());
    }

    public function test_valid_when_on_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => Carbon::now()->addDays(7),
        ]);

        $this->assertTrue($subscription->valid());
    }

    public function test_valid_when_on_grace_period(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertTrue($subscription->valid());
    }

    public function test_invalid_when_ended(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_CANCELED,
            'ends_at' => Carbon::now()->subDay(),
        ]);

        $this->assertFalse($subscription->valid());
    }

    public function test_calculate_subscription_amount(): void
    {
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
    }

    public function test_cancel(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'next_billing_at' => Carbon::now()->addDays(15),
        ]);

        $subscription->cancel();

        $this->assertNotNull($subscription->ends_at);
    }

    public function test_cancel_on_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $trialEnd = Carbon::now()->addDays(7);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => $trialEnd,
        ]);

        $subscription->cancel();

        $this->assertEquals($trialEnd->toDateTimeString(), $subscription->ends_at->toDateTimeString());
    }

    public function test_cancel_now(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $subscription->cancelNow();

        $this->assertEquals(Subscription::STATUS_CANCELED, $subscription->chip_status);
        $this->assertNotNull($subscription->ends_at);
    }

    public function test_mark_as_canceled(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $subscription->markAsCanceled();

        $this->assertEquals(Subscription::STATUS_CANCELED, $subscription->fresh()->chip_status);
    }

    public function test_resume(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $subscription->resume();

        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->chip_status);
        $this->assertNull($subscription->ends_at);
    }

    public function test_resume_throws_not_on_grace_period(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'ends_at' => null,
        ]);

        $this->expectException(LogicException::class);

        $subscription->resume();
    }

    public function test_has_incomplete_payment(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ]);

        $this->assertTrue($subscription->hasIncompletePayment());
    }

    public function test_has_incomplete_payment_with_incomplete_status(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_INCOMPLETE,
        ]);

        $this->assertTrue($subscription->hasIncompletePayment());
    }

    public function test_scope_not_on_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => null,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'trial_ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertEquals(1, Subscription::query()->notOnTrial()->count());
    }

    public function test_scope_not_on_grace_period(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => null,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $this->assertEquals(1, Subscription::query()->notOnGracePeriod()->count());
    }

    public function test_cancel_at(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $cancelDate = Carbon::now()->addDays(30);
        $subscription->cancelAt($cancelDate);

        $this->assertEquals($cancelDate->toDateTimeString(), $subscription->ends_at->toDateTimeString());
    }

    public function test_cancel_at_with_timestamp(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $timestamp = Carbon::now()->addDays(30)->timestamp;
        $subscription->cancelAt($timestamp);

        $this->assertNotNull($subscription->ends_at);
    }

    public function test_set_recurring_token(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $subscription->setRecurringToken('tok_123');

        $this->assertEquals('tok_123', $subscription->fresh()->recurring_token);
    }
}
