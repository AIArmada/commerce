<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

class RenewSubscriptionsCommandTest extends CashierChipTestCase
{
    public function test_command_runs_with_no_subscriptions(): void
    {
        $this->artisan('cashier-chip:renew-subscriptions')
            ->assertSuccessful();
    }

    public function test_command_runs_with_dry_run_option(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'customer')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'next_billing_at' => Carbon::now()->subDay(),
            'chip_price' => 'price_123',
        ]);

        $this->artisan('cashier-chip:renew-subscriptions', ['--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_command_handles_subscription_without_owner(): void
    {
        // Subscription with null owner should be skipped
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'customer')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        // Delete the user to simulate orphan subscription
        $user->delete();

        $this->artisan('cashier-chip:renew-subscriptions')
            ->assertSuccessful();
    }

    public function test_command_renews_due_subscription_successfully(): void
    {
        Event::fake([
            SubscriptionRenewed::class,
            SubscriptionRenewalFailed::class,
        ]);

        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $token = $this->fakeChip->getFakeClient()->addRecurringToken($user->chip_id);
        $user->updateDefaultPaymentMethod($token['id']);

        $subscription = Subscription::factory()->for($user, 'customer')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 2,
        ]);

        $this->artisan('cashier-chip:renew-subscriptions')
            ->assertSuccessful();

        Event::assertDispatched(SubscriptionRenewed::class);
        Event::assertNotDispatched(SubscriptionRenewalFailed::class);
    }

    public function test_command_marks_subscription_past_due_when_no_payment_method_available(): void
    {
        Event::fake([
            SubscriptionRenewed::class,
            SubscriptionRenewalFailed::class,
        ]);

        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $subscription = Subscription::factory()->for($user, 'customer')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 1,
        ]);

        $this->artisan('cashier-chip:renew-subscriptions')
            ->assertFailed();

        $subscription->refresh();

        $this->assertSame(Subscription::STATUS_PAST_DUE, $subscription->chip_status);

        Event::assertDispatched(SubscriptionRenewalFailed::class);
        Event::assertNotDispatched(SubscriptionRenewed::class);
    }

    public function test_command_marks_subscription_past_due_when_subscription_amount_is_invalid(): void
    {
        Event::fake([
            SubscriptionRenewed::class,
            SubscriptionRenewalFailed::class,
        ]);

        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $token = $this->fakeChip->getFakeClient()->addRecurringToken($user->chip_id);
        $user->updateDefaultPaymentMethod($token['id']);

        $subscription = Subscription::factory()->for($user, 'customer')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 0,
            'quantity' => 1,
        ]);

        $this->artisan('cashier-chip:renew-subscriptions')
            ->assertFailed();

        $subscription->refresh();

        $this->assertSame(Subscription::STATUS_PAST_DUE, $subscription->chip_status);

        Event::assertDispatched(SubscriptionRenewalFailed::class);
        Event::assertNotDispatched(SubscriptionRenewed::class);
    }
}
