<?php

declare(strict_types=1);

use AIArmada\CashierChip\Actions\ChargeChipCustomer;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\CashierChip\Payment\PaymentMethod;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\CashierChip\Tests\TestCase;
use AIArmada\Chip\Data\ClientData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

uses(TestCase::class);

describe('ChargeChipCustomer', function (): void {
    it('charges a billable customer', function (): void {
        Cashier::fake(new FakeChipClient);

        $customer = (new class extends Model implements BillableContract
        {
            public function chipId(): ?string
            {
                return 'chip_cus_123';
            }

            public function hasChipId(): bool
            {
                return true;
            }

            public function chipEmail(): ?string
            {
                return 'test@example.com';
            }

            public function chipName(): ?string
            {
                return 'Test User';
            }

            public function preferredCurrency(): string
            {
                return 'MYR';
            }

            public function getMorphClass(): string
            {
                return 'user';
            }

            public function getKey(): mixed
            {
                return '1';
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
        });

        $payment = app(ChargeChipCustomer::class)->charge($customer, 5000);

        expect($payment)->toBeInstanceOf(Payment::class);
        expect($payment->rawAmount())->toBe(5000);
    });
});
