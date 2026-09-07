<?php

declare(strict_types=1);

use AIArmada\CashierChip\Console\RenewSubscriptionsCommand;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Carbon\Carbon;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Event;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(CashierChipTestCase::class);

describe('RenewSubscriptionsCommand', function (): void {
    it('command runs with no subscriptions', function (): void {
        $this->artisan('cashier-chip:renew-subscriptions')
            ->assertSuccessful();
    });

    it('command runs with dry run option', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => Carbon::now()->subDay(),
            'chip_price' => 'price_123',
        ]);

        $this->artisan('cashier-chip:renew-subscriptions', ['--dry-run' => true])
            ->assertSuccessful();
    });

    it('command handles subscription without owner', function (): void {
        // Subscription with null owner should be skipped
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        // Delete the user to simulate orphan subscription
        $user->delete();

        $this->artisan('cashier-chip:renew-subscriptions')
            ->assertSuccessful();
    });

    it('command renews due subscription successfully', function (): void {
        Event::fake([
            SubscriptionRenewed::class,
            SubscriptionRenewalFailed::class,
        ]);

        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $token = $this->fakeChip->getFakeClient()->addRecurringToken($user->chip_id);
        $user->updateDefaultPaymentMethod($token['id']);

        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 2,
        ]);

        $command = $this->app->make(RenewSubscriptionsCommand::class);
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(
            new ArrayInput([]),
            new BufferedOutput,
        ));
        $method = new ReflectionMethod($command, 'processRenewals');
        /** @var array{renewed: int, failed: int, unknown: int, skipped: int} $result */
        $result = $method->invoke($command, false, 0);

        $this->assertSame(['renewed' => 1, 'failed' => 0, 'unknown' => 0, 'skipped' => 0], $result);

        Event::assertDispatched(SubscriptionRenewed::class);
        Event::assertNotDispatched(SubscriptionRenewalFailed::class);
    });

    it('command marks subscription past due when no payment method available', function (): void {
        Event::fake([
            SubscriptionRenewed::class,
            SubscriptionRenewalFailed::class,
        ]);

        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 1,
        ]);

        $command = $this->app->make(RenewSubscriptionsCommand::class);
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(
            new ArrayInput([]),
            new BufferedOutput,
        ));
        $method = new ReflectionMethod($command, 'processRenewals');
        /** @var array{renewed: int, failed: int, unknown: int, skipped: int} $result */
        $result = $method->invoke($command, false, 0);

        $this->assertSame(['renewed' => 0, 'failed' => 1, 'unknown' => 0, 'skipped' => 0], $result);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->chip_status);

        Event::assertDispatched(SubscriptionRenewalFailed::class);
        Event::assertNotDispatched(SubscriptionRenewed::class);
    });

    it('command marks subscription past due when subscription amount is invalid', function (): void {
        Event::fake([
            SubscriptionRenewed::class,
            SubscriptionRenewalFailed::class,
        ]);

        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $token = $this->fakeChip->getFakeClient()->addRecurringToken($user->chip_id);
        $user->updateDefaultPaymentMethod($token['id']);

        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 0,
            'quantity' => 1,
        ]);

        $command = $this->app->make(RenewSubscriptionsCommand::class);
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(
            new ArrayInput([]),
            new BufferedOutput,
        ));
        $method = new ReflectionMethod($command, 'processRenewals');
        /** @var array{renewed: int, failed: int, unknown: int, skipped: int} $result */
        $result = $method->invoke($command, false, 0);

        $this->assertSame(['renewed' => 0, 'failed' => 1, 'unknown' => 0, 'skipped' => 0], $result);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->chip_status);

        Event::assertDispatched(SubscriptionRenewalFailed::class);
        Event::assertNotDispatched(SubscriptionRenewed::class);
    });
});
