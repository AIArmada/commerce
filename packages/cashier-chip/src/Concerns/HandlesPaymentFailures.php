<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Payment\Payment;

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
     * @param  callable(): ?Payment  $incompletePayment
     * @throws IncompletePayment
     *
     * @internal
     */
    public function handlePaymentFailure(callable $incompletePayment): void
    {
        try {
            if ($this->validateIncompletePayment) {
                $incompletePayment()?->validate();
            }
        } finally {
            $this->validateIncompletePayment = true;
        }
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
