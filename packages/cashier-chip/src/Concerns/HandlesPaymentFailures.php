<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Payment;
use AIArmada\CashierChip\Subscription;

/**
 * Handles payment failures during subscription operations.
 */
trait HandlesPaymentFailures
{
    /**
     * Indicates if incomplete payments should be validated automatically.
     */
    protected bool $validateIncompletePayment = true;

    /**
     * Handle a failed payment for the given subscription.
     *
     * @throws IncompletePayment
     *
     * @internal
     */
    public function handlePaymentFailure(Subscription $subscription): void
    {
        if ($this->validateIncompletePayment && $subscription->hasIncompletePayment()) {
            $latestPayment = $subscription->latestPayment();

            if ($latestPayment) {
                $latestPayment->validate();
            }
        }

        $this->validateIncompletePayment = true;
    }

    /**
     * Prevent automatic validation of incomplete payments.
     *
     * @return $this
     */
    public function ignoreIncompletePayments(): static
    {
        $this->validateIncompletePayment = false;

        return $this;
    }
}
