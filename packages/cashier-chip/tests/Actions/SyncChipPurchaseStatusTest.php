<?php

declare(strict_types=1);

use AIArmada\CashierChip\Actions\SyncChipPurchaseStatus;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\CashierChip\Payment\PaymentMethod;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\CashierChip\Tests\TestCase;
use AIArmada\Chip\Data\ClientData;
use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Event;

uses(TestCase::class);

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

        app(SyncChipPurchaseStatus::class)->syncPaid($billable, $purchaseData, []);

        Event::assertDispatched(PaymentSucceeded::class);
    });

    it('dispatches PaymentFailed on syncFailed', function (): void {
        Event::fake();

        Cashier::fake(new FakeChipClient);

        $purchaseData = PurchaseData::from([
            'id' => 'purchase_fail_1',
            'status' => 'failed',
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

        app(SyncChipPurchaseStatus::class)->syncFailed($billable, $purchaseData, []);

        Event::assertDispatched(PaymentFailed::class);
    });
});
