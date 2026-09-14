<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Feature;

use AIArmada\CashierChip\Actions\ClaimRenewalAttempt;
use AIArmada\CashierChip\Actions\SyncChipPurchaseStatus;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\SettledPeriodPurchaseConflict;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\CashierChip\Payment\PaymentMethod;
use AIArmada\CashierChip\Subscription\RenewalAttempt;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\Chip\Data\ClientData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

\uses(TestCase::class);
describe('SyncChipPurchaseStatus', function (): void {
    it('dispatches PaymentSucceeded on syncPaid', function (): void {
        Event::fake();

        Cashier::fake(new FakeChipClient);

        $purchaseData = PurchaseData::from([
            'id' => 'purchase_paid_1',
            'status' => 'paid',
            'currency' => 'MYR',
            'amount_in_cents' => 5000,
        ]);

        $billable = new class extends Model implements BillableContract
        {
            public function chipId(): ?string
            {
                return 'chip_cus_paid';
            }

            public function hasDefaultPaymentMethod(): bool
            {
                return false;
            }

            public function getMorphClass(): string
            {
                return 'user';
            }

            public function getKey(): mixed
            {
                return '1';
            }

            public function hasChipId(): bool
            {
                return true;
            }

            public function chipEmail(): ?string
            {
                return null;
            }

            public function chipName(): ?string
            {
                return null;
            }

            public function preferredCurrency(): string
            {
                return 'MYR';
            }

            public function chipPhone(): ?string
            {
                return null;
            }

            public function chipCountry(): ?string
            {
                return null;
            }

            public function chipAddress(): array
            {
                return [];
            }

            public function createOrGetChipCustomer(array $options = []): ClientData
            {
                throw new BadMethodCallException('Not implemented in test stub.');
            }

            public function charge(int $amount, ?string $recurringToken = null, array $options = []): Payment
            {
                throw new BadMethodCallException('Not implemented in test stub.');
            }

            public function chargeWithRecurringToken(int $amount, ?string $recurringToken = null, array $options = []): Payment
            {
                throw new BadMethodCallException('Not implemented in test stub.');
            }

            public function defaultPaymentMethod(): ?PaymentMethod
            {
                return null;
            }

            public function updateDefaultPaymentMethod(string $paymentMethodId): static
            {
                return $this;
            }

            public function deletePaymentMethod(string $paymentMethodId): void
            {
                // no-op
            }

            public function subscriptions(): MorphMany
            {
                throw new BadMethodCallException('Not implemented in test stub.');
            }

            public function subscription(string $type = 'default'): ?Subscription
            {
                return null;
            }
        };

        SyncChipPurchaseStatus::run($billable, $purchaseData, []);

        Event::assertDispatched(PaymentSucceeded::class);
    });

    it('dispatches PaymentFailed on syncFailed', function (): void {
        Event::fake();

        Cashier::fake(new FakeChipClient);

        $purchaseData = PurchaseData::from([
            'id' => 'purchase_fail_1',
            'status' => 'error',
            'currency' => 'MYR',
            'amount_in_cents' => 5000,
        ]);

        $billable = new class extends Model implements BillableContract
        {
            public function chipId(): ?string
            {
                return 'chip_cus_fail';
            }

            public function getMorphClass(): string
            {
                return 'user';
            }

            public function getKey(): mixed
            {
                return '2';
            }

            public function hasChipId(): bool
            {
                return true;
            }

            public function chipEmail(): ?string
            {
                return null;
            }

            public function chipName(): ?string
            {
                return null;
            }

            public function preferredCurrency(): string
            {
                return 'MYR';
            }

            public function chipPhone(): ?string
            {
                return null;
            }

            public function chipCountry(): ?string
            {
                return null;
            }

            public function chipAddress(): array
            {
                return [];
            }

            public function createOrGetChipCustomer(array $options = []): ClientData
            {
                throw new BadMethodCallException('Not implemented in test stub.');
            }

            public function charge(int $amount, ?string $recurringToken = null, array $options = []): Payment
            {
                throw new BadMethodCallException('Not implemented in test stub.');
            }

            public function chargeWithRecurringToken(int $amount, ?string $recurringToken = null, array $options = []): Payment
            {
                throw new BadMethodCallException('Not implemented in test stub.');
            }

            public function defaultPaymentMethod(): ?PaymentMethod
            {
                return null;
            }

            public function hasDefaultPaymentMethod(): bool
            {
                return false;
            }

            public function updateDefaultPaymentMethod(string $paymentMethodId): static
            {
                return $this;
            }

            public function deletePaymentMethod(string $paymentMethodId): void
            {
                // no-op
            }

            public function subscriptions(): MorphMany
            {
                throw new BadMethodCallException('Not implemented in test stub.');
            }

            public function subscription(string $type = 'default'): ?Subscription
            {
                return null;
            }
        };

        SyncChipPurchaseStatus::make()->syncFailed($billable, $purchaseData, []);

        Event::assertDispatched(PaymentFailed::class);
    });

    it('emits a reconciliation hook when a second distinct purchase conflicts with a settled period', function (): void {
        Cashier::fake(new FakeChipClient);

        config()->set('cashier-chip.features.owner.enabled', true);
        config()->set('cashier-chip.features.owner.include_global', false);

        /** @var User $user */
        $user = User::query()->create([
            'name' => 'Settle User',
            'email' => 'settle-user@example.com',
            'password' => bcrypt('secret'),
        ]);

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($user));

        $nextBillingAt = CarbonImmutable::now()->subDay();

        /** @var Subscription $subscription */
        $subscription = Subscription::query()->create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'type' => 'default',
            'chip_id' => 'sub_' . (string) Str::uuid(),
            'chip_status' => SubscriptionStatus::Active,
            'chip_price' => 'price_basic_monthly',
            'quantity' => 1,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'next_billing_at' => $nextBillingAt,
        ]);

        $periodKey = ClaimRenewalAttempt::periodKeyFor($subscription->refresh());

        RenewalAttempt::query()->create([
            'subscription_id' => $subscription->id,
            'status' => 'unknown',
            'amount_minor' => 1000,
            'period_key' => $periodKey,
            'purchase_id' => 'purchase_other',
        ]);

        $purchaseData = PurchaseData::from([
            'id' => 'purchase_new',
            'status' => 'paid',
            'currency' => 'MYR',
            'amount_in_cents' => 1000,
        ]);

        // Fake only after fixtures exist: model creating hooks stamp owner scope.
        Event::fake();
        Log::spy();

        SyncChipPurchaseStatus::run($user, $purchaseData, [
            'purchase' => [
                'metadata' => ['subscription_type' => 'default'],
            ],
        ]);

        Event::assertDispatched(SettledPeriodPurchaseConflict::class, fn (SettledPeriodPurchaseConflict $event): bool => $event->purchaseId === 'purchase_new'
            && $event->periodKey === $periodKey
            && (string) $event->subscription->id === (string) $subscription->id);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'reconciliation')
            && ($context['purchase_id'] ?? null) === 'purchase_new'
            && ($context['period_key'] ?? null) === $periodKey);

        expect($subscription->refresh()->next_billing_at->toIso8601String())->toBe($nextBillingAt->toIso8601String())
            ->and(RenewalAttempt::query()->where('purchase_id', 'purchase_new')->exists())->toBeFalse();
    });
});
