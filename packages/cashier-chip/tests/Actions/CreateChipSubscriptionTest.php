<?php

declare(strict_types=1);

use AIArmada\CashierChip\Actions\CreateChipSubscription;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\CashierChip\Payment\PaymentMethod;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionBuilder;
use AIArmada\CashierChip\Tests\TestCase;
use AIArmada\Chip\Data\ClientData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

uses(TestCase::class);

describe('CreateChipSubscription', function (): void {
    it('rejects empty prices', function (): void {
        $customer = new class extends Model implements BillableContract
        {
            protected $guarded = [];

            public function chipId(): ?string
            {
                return 'chip_cus_sub';
            }

            public function hasChipId(): bool
            {
                return true;
            }

            public function chipEmail(): ?string
            {
                return 'sub@example.com';
            }

            public function chipName(): ?string
            {
                return 'Sub User';
            }

            public function preferredCurrency(): string
            {
                return 'MYR';
            }

            public function defaultPaymentMethod(): ?PaymentMethod
            {
                return null;
            }

            public function getMorphClass(): string
            {
                return 'user';
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

        $builder = new SubscriptionBuilder($customer, 'default');

        app(CreateChipSubscription::class)->create($builder);
    })->throws(Exception::class, 'At least one price');
});
