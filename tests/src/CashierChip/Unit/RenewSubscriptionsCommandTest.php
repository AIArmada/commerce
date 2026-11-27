<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Features\Subscriptions\Actions\ClaimRenewalAttempt;
use AIArmada\CashierChip\Features\Subscriptions\Actions\RenewSubscriptions;
use AIArmada\CashierChip\Features\Subscriptions\Models\RenewalAttempt;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
use AIArmada\CashierChip\Features\Subscriptions\Models\SubscriptionItem;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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

        $action = $this->app->make(RenewSubscriptions::class);
        /** @var array{renewed: int, failed: int, unknown: int, skipped: int} $result */
        $result = $action->processRenewals(false, 0);

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
            'recurring_token' => null,
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 1,
        ]);

        $action = $this->app->make(RenewSubscriptions::class);
        /** @var array{renewed: int, failed: int, unknown: int, skipped: int} $result */
        $result = $action->processRenewals(false, 0);

        $this->assertSame(['renewed' => 0, 'failed' => 1, 'unknown' => 0, 'skipped' => 0], $result);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->chip_status);

        Event::assertDispatched(SubscriptionRenewalFailed::class);
        Event::assertNotDispatched(SubscriptionRenewed::class);
    });

    it('command completes a fully discounted renewal without charging', function (): void {
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
            'unit_amount' => 0,
            'quantity' => 1,
        ]);

        $action = $this->app->make(RenewSubscriptions::class);
        /** @var array{renewed: int, failed: int, unknown: int, skipped: int} $result */
        $result = $action->processRenewals(false, 0);

        $this->assertSame(['renewed' => 1, 'failed' => 0, 'unknown' => 0, 'skipped' => 0], $result);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->chip_status);
        $this->assertTrue($subscription->next_billing_at->isFuture());

        $attempt = RenewalAttempt::query()
            ->where('subscription_id', $subscription->id)
            ->first();

        $this->assertSame('completed', $attempt->status);
        $this->assertSame(0, $attempt->amount_minor);
        $this->assertNull($attempt->purchase_id);

        Event::assertDispatched(
            SubscriptionRenewed::class,
            fn (SubscriptionRenewed $event): bool => $event->purchaseId === null
        );
        Event::assertNotDispatched(SubscriptionRenewalFailed::class);

        $this->assertSame([], $this->fakeChip->getFakeClient()->getPurchases());
    });

    it('command charges a positive renewal amount through the gateway', function (): void {
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
            'quantity' => 1,
        ]);

        $action = $this->app->make(RenewSubscriptions::class);
        /** @var array{renewed: int, failed: int, unknown: int, skipped: int} $result */
        $result = $action->processRenewals(false, 0);

        $this->assertSame(['renewed' => 1, 'failed' => 0, 'unknown' => 0, 'skipped' => 0], $result);

        $purchases = array_values($this->fakeChip->getFakeClient()->getPurchases());

        $this->assertCount(1, $purchases);
        $this->assertSame(1000, data_get($purchases[0], 'purchase.total'));

        Event::assertDispatched(
            SubscriptionRenewed::class,
            fn (SubscriptionRenewed $event): bool => $event->purchaseId !== null
        );
        Event::assertNotDispatched(SubscriptionRenewalFailed::class);
    });

    it('uses distinct period keys for sub-monthly renewal periods', function (): void {
        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $firstBillingAt = CarbonImmutable::parse('2024-06-10T00:00:00+00:00');
        $secondBillingAt = $firstBillingAt->addDay();

        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'billing_interval' => 'day',
            'billing_interval_count' => 1,
            'next_billing_at' => $firstBillingAt,
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 1,
        ]);

        $claimRenewalAttempt = app(ClaimRenewalAttempt::class);
        $firstAttempt = $claimRenewalAttempt->handle($subscription->id);

        $subscription->update(['next_billing_at' => $secondBillingAt]);
        $secondAttempt = $claimRenewalAttempt->handle($subscription->id);

        expect($firstAttempt)->toBeInstanceOf(RenewalAttempt::class)
            ->and($secondAttempt)->toBeInstanceOf(RenewalAttempt::class)
            ->and($firstAttempt?->period_key)->toBe($firstBillingAt->toIso8601String())
            ->and($secondAttempt?->period_key)->toBe($secondBillingAt->toIso8601String())
            ->and($secondAttempt?->period_key)->not->toBe($firstAttempt?->period_key);
    });

    it('does not reclaim an expired renewal attempt for the same period', function (): void {
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

        $claimRenewalAttempt = app(ClaimRenewalAttempt::class);
        $attempt = $claimRenewalAttempt->handle($subscription->id);

        expect($attempt)->toBeInstanceOf(RenewalAttempt::class);

        $attempt?->update(['lease_expires_at' => CarbonImmutable::now()->subMinute()]);

        expect($claimRenewalAttempt->handle($subscription->id))->toBeNull()
            ->and(RenewalAttempt::query()
                ->where('subscription_id', $subscription->id)
                ->where('period_key', $attempt?->period_key)
                ->count())->toBe(1);
    });

    it('charges the frozen renewal amount after subscription item mutation', function (): void {
        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $token = $this->fakeChip->getFakeClient()->addRecurringToken($user->chip_id);
        $user->updateDefaultPaymentMethod($token['id']);

        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);
        $item = SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 1,
        ]);

        /** @var ClaimRenewalAttempt $claimRenewalAttempt */
        $claimRenewalAttempt = app(ClaimRenewalAttempt::class);
        $attempt = $claimRenewalAttempt->handle($subscription->id);

        expect($attempt)->toBeInstanceOf(RenewalAttempt::class)
            ->and($attempt?->amount_minor)->toBe(1000);

        expect(fn () => $attempt?->forceFill(['amount_minor' => 2000])->save())
            ->toThrow(LogicException::class);

        $attempt?->refresh();
        $item->forceFill(['unit_amount' => 2000])->save();

        $action = $this->app->make(RenewSubscriptions::class);
        $result = $action->executeAttempt($attempt);
        $purchases = array_values($this->fakeChip->getFakeClient()->getPurchases());

        expect($result)->toBe('renewed')
            ->and($purchases)->toHaveCount(1)
            ->and(data_get($purchases[0], 'purchase.total'))->toBe(1000)
            ->and($attempt?->fresh()?->amount_minor)->toBe(1000);
    });

    it('rejects a tampered renewal amount outside the configured bounds', function (): void {
        config()->set('cashier-chip.billing.max_amount_minor', 5000);

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

        /** @var ClaimRenewalAttempt $claimRenewalAttempt */
        $claimRenewalAttempt = app(ClaimRenewalAttempt::class);
        $attempt = $claimRenewalAttempt->handle($subscription->id);

        expect($attempt)->toBeInstanceOf(RenewalAttempt::class);

        DB::table($attempt->getTable())
            ->where('id', $attempt->id)
            ->update(['amount_minor' => 5001]);
        $attempt->refresh();

        $action = $this->app->make(RenewSubscriptions::class);
        $result = $action->executeAttempt($attempt);

        expect($result)->toBe('failed')
            ->and($attempt->fresh()?->status)->toBe('failed')
            ->and($attempt->fresh()?->last_error_code)->toBe('INVALID_RENEWAL_AMOUNT')
            ->and($this->fakeChip->getFakeClient()->getPurchases())->toBe([]);
    });

    it('fails a renewal with unknown item prices instead of renewing for free', function (): void {
        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => null,
            'quantity' => 1,
        ]);

        $action = $this->app->make(RenewSubscriptions::class);
        /** @var array{renewed: int, failed: int, unknown: int, skipped: int} $result */
        $result = $action->processRenewals(false, 0);

        expect($result)->toBe(['renewed' => 0, 'failed' => 1, 'unknown' => 0, 'skipped' => 0])
            ->and($subscription->fresh()?->chip_status)->toBe(SubscriptionStatus::PastDue)
            ->and($this->fakeChip->getFakeClient()->getPurchases())->toBe([]);

        $attempt = RenewalAttempt::query()
            ->where('subscription_id', $subscription->id)
            ->first();

        expect($attempt?->status)->toBe('failed')
            ->and($attempt?->last_error_code)->toBe('INVALID_RENEWAL_AMOUNT');
    });

    it('refuses to charge a subscription with unknown item prices', function (): void {
        /** @var User $user */
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => Carbon::now()->subDay(),
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => null,
            'quantity' => 1,
        ]);

        expect(fn () => $subscription->charge())->toThrow(InvalidArgumentException::class, 'unknown item prices');
    });

    it('fails an expired renewal claim closed to past-due instead of stalling', function (): void {
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

        /** @var ClaimRenewalAttempt $claimRenewalAttempt */
        $claimRenewalAttempt = app(ClaimRenewalAttempt::class);
        $attempt = $claimRenewalAttempt->handle($subscription->id);

        expect($attempt)->toBeInstanceOf(RenewalAttempt::class);

        $attempt?->update(['lease_expires_at' => CarbonImmutable::now()->subMinute()]);

        $action = $this->app->make(RenewSubscriptions::class);
        /** @var array{renewed: int, failed: int, unknown: int, skipped: int} $result */
        $result = $action->processRenewals(false, 0);

        expect($result)->toBe(['renewed' => 0, 'failed' => 1, 'unknown' => 0, 'skipped' => 0])
            ->and($subscription->fresh()?->chip_status)->toBe(SubscriptionStatus::PastDue)
            ->and($attempt?->fresh()?->status)->toBe('failed')
            ->and($attempt?->fresh()?->last_error_code)->toBe('CLAIM_EXPIRED')
            ->and($this->fakeChip->getFakeClient()->getPurchases())->toBe([]);
    });
});

it('does not claim new renewals for paused, unpaid or canceled subscriptions', function (): void {
    $user = $this->createUser(['chip_id' => 'cli_ineligible']);

    foreach ([SubscriptionStatus::Paused, SubscriptionStatus::Unpaid, SubscriptionStatus::Canceled, SubscriptionStatus::IncompleteExpired] as $status) {
        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => $status,
            'next_billing_at' => CarbonImmutable::now()->subDay(),
        ]);

        expect(app(ClaimRenewalAttempt::class)->handle((string) $subscription->id))->toBeNull();
    }

    expect(RenewalAttempt::query()->count())->toBe(0);
});

it('charges renewals with the subscription token before the customer default token', function (): void {
    /** @var User $user */
    $user = $this->createUser(['chip_id' => 'cli_renewal_token']);

    $defaultToken = $this->fakeChip->getFakeClient()->addRecurringToken($user->chip_id);
    $user->updateDefaultPaymentMethod($defaultToken['id']);

    $subscription = Subscription::factory()->for($user, 'billable')->create([
        'chip_status' => SubscriptionStatus::Active,
        'next_billing_at' => Carbon::now()->subDay(),
        'recurring_token' => 'tok_subscription_specific',
    ]);

    SubscriptionItem::factory()->forSubscription($subscription)->create([
        'unit_amount' => 1000,
        'quantity' => 1,
    ]);

    $result = $this->app->make(RenewSubscriptions::class)->processRenewals(false, 0);

    $purchases = array_values($this->fakeChip->getFakeClient()->getPurchases());

    expect($result)->toBe(['renewed' => 1, 'failed' => 0, 'unknown' => 0, 'skipped' => 0])
        ->and($purchases)->toHaveCount(1)
        ->and($purchases[0]['recurring_token'])->toBe('tok_subscription_specific');
});

it('persists a known purchase before charging and never reissues it after a transport failure', function (): void {
    $client = new class extends FakeChipClient
    {
        public function chargePurchase(string $purchaseId, string $recurringToken): ?array
        {
            throw new RuntimeException('charge transport failed');
        }
    };

    $this->fakeChip = Cashier::fake($client);

    /** @var User $user */
    $user = $this->createUser(['chip_id' => 'cli_renewal_transport']);

    $subscription = Subscription::factory()->for($user, 'billable')->create([
        'chip_status' => SubscriptionStatus::Active,
        'next_billing_at' => Carbon::now()->subDay(),
        'recurring_token' => 'tok_renewal_transport',
    ]);

    SubscriptionItem::factory()->forSubscription($subscription)->create([
        'unit_amount' => 1000,
        'quantity' => 1,
    ]);

    $action = $this->app->make(RenewSubscriptions::class);

    expect($action->processRenewals(false, 0))->toBe(['renewed' => 0, 'failed' => 0, 'unknown' => 1, 'skipped' => 0]);

    $attempt = RenewalAttempt::query()->where('subscription_id', $subscription->id)->firstOrFail();
    $purchases = array_values($client->getPurchases());

    expect($purchases)->toHaveCount(1)
        ->and($attempt->status)->toBe('unknown')
        ->and($attempt->purchase_id)->toBe($purchases[0]['id']);

    expect($action->processRenewals(false, 0))->toBe(['renewed' => 0, 'failed' => 0, 'unknown' => 1, 'skipped' => 0])
        ->and(array_values($client->getPurchases()))->toHaveCount(1);
});
