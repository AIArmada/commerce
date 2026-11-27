<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Billing;

use AIArmada\CashierChip\Payment\Payment;
use AIArmada\CashierChip\Payment\PaymentMethod;
use AIArmada\CashierChip\Contracts\BillableIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface BillableModel extends BillableIdentity
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function charge(int $amount, ?string $recurringToken = null, array $options = []): Payment;

    /**
     * @param  array<string, mixed>  $options
     */
    public function chargeWithRecurringToken(int $amount, ?string $recurringToken = null, array $options = []): Payment;

    public function defaultPaymentMethod(): ?PaymentMethod;

    public function hasDefaultPaymentMethod(): bool;

    public function updateDefaultPaymentMethod(string $paymentMethodId): static;

    public function deletePaymentMethod(string $paymentMethodId): void;

    public function subscriptions(): MorphMany;

    public function subscription(string $type = 'default'): ?Model;
}
