<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;

class ManagesSubscriptionsTest extends CashierChipTestCase
{
    public function test_new_subscription_returns_builder(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $builder = $user->newSubscription('default', 'price_123');

        $this->assertInstanceOf(SubscriptionBuilder::class, $builder);
    }

    public function test_on_trial_returns_false_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->onTrial('default'));
    }

    public function test_on_generic_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123', 'trial_ends_at' => Carbon::now()->addDays(7)]);

        $this->assertTrue($user->onGenericTrial());
    }

    public function test_on_generic_trial_false_when_expired(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123', 'trial_ends_at' => Carbon::now()->subDay()]);

        $this->assertFalse($user->onGenericTrial());
    }

    public function test_has_expired_generic_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123', 'trial_ends_at' => Carbon::now()->subDay()]);

        $this->assertTrue($user->hasExpiredGenericTrial());
    }

    public function test_has_expired_trial_returns_false_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->hasExpiredTrial('default'));
    }

    public function test_trial_ends_at_returns_model_trial(): void
    {
        $trialDate = Carbon::now()->addDays(7);
        $user = $this->createUser(['chip_id' => 'cli_123', 'trial_ends_at' => $trialDate]);

        $this->assertEquals($trialDate->toDateTimeString(), $user->trialEndsAt()->toDateTimeString());
    }

    public function test_subscribed_returns_false_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->subscribed('default'));
    }

    public function test_subscription_returns_null_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertNull($user->subscription('default'));
    }

    public function test_subscriptions_relation(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->subscriptions());
    }

    public function test_has_incomplete_payment_returns_false_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->hasIncompletePayment('default'));
    }

    public function test_subscribed_to_product_returns_false_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->subscribedToProduct('prod_123'));
    }

    public function test_subscribed_to_price_returns_false_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->subscribedToPrice('price_123'));
    }

    public function test_on_product_returns_false_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->onProduct('prod_123'));
    }

    public function test_on_price_returns_false_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->onPrice('price_123'));
    }

    public function test_tax_rates_returns_empty_array(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals([], $user->taxRates());
    }

    public function test_price_tax_rates_returns_empty_array(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals([], $user->priceTaxRates());
    }

    public function test_subscribed_with_active_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'type' => 'default',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertTrue($user->subscribed('default'));
    }

    public function test_has_incomplete_payment_with_past_due_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'type' => 'default',
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ]);

        $this->assertTrue($user->hasIncompletePayment('default'));
    }
}
