<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

/**
 * Payment behavior for subscription operations.
 *
 * CANONICAL for subscription payment behavior workflow settings.
 * Unlike InteractsWithChip (which provides gateway access), this
 * trait controls how subscription changes handle payment failures.
 *
 * @see InteractsWithChip for CHIP gateway client access.
 */
trait InteractsWithPaymentBehavior
{
    public const PAYMENT_BEHAVIOR_DEFAULT_INCOMPLETE = 'default_incomplete';

    public const PAYMENT_BEHAVIOR_ALLOW_INCOMPLETE = 'allow_incomplete';

    public const PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE = 'pending_if_incomplete';

    public const PAYMENT_BEHAVIOR_ERROR_IF_INCOMPLETE = 'error_if_incomplete';

    protected string $paymentBehavior = 'default_incomplete';

    public function defaultIncomplete(): static
    {
        $this->paymentBehavior = self::PAYMENT_BEHAVIOR_DEFAULT_INCOMPLETE;

        return $this;
    }

    public function allowPaymentFailures(): static
    {
        $this->paymentBehavior = self::PAYMENT_BEHAVIOR_ALLOW_INCOMPLETE;

        return $this;
    }

    public function pendingIfPaymentFails(): static
    {
        $this->paymentBehavior = self::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE;

        return $this;
    }

    public function errorIfPaymentFails(): static
    {
        $this->paymentBehavior = self::PAYMENT_BEHAVIOR_ERROR_IF_INCOMPLETE;

        return $this;
    }

    public function paymentBehavior(): string
    {
        return $this->paymentBehavior;
    }

    public function setPaymentBehavior(string $paymentBehavior): static
    {
        $this->paymentBehavior = $paymentBehavior;

        return $this;
    }
}
