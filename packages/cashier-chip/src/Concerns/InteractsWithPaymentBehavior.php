<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

/**
 * Payment behavior for subscription operations.
 *
 * While CHIP doesn't have native subscription payment behaviors like Stripe,
 * these methods provide API compatibility for controlling how payment
 * failures should be handled during subscription changes.
 */
trait InteractsWithPaymentBehavior
{
    /**
     * Payment behavior constants for consistency with Stripe.
     */
    public const PAYMENT_BEHAVIOR_DEFAULT_INCOMPLETE = 'default_incomplete';

    public const PAYMENT_BEHAVIOR_ALLOW_INCOMPLETE = 'allow_incomplete';

    public const PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE = 'pending_if_incomplete';

    public const PAYMENT_BEHAVIOR_ERROR_IF_INCOMPLETE = 'error_if_incomplete';

    /**
     * The payment behavior for subscription updates.
     */
    protected string $paymentBehavior = 'default_incomplete';

    /**
     * Set any new subscription as incomplete when created.
     *
     * @return $this
     */
    public function defaultIncomplete(): static
    {
        $this->paymentBehavior = self::PAYMENT_BEHAVIOR_DEFAULT_INCOMPLETE;

        return $this;
    }

    /**
     * Allow subscription changes even if payment fails.
     *
     * @return $this
     */
    public function allowPaymentFailures(): static
    {
        $this->paymentBehavior = self::PAYMENT_BEHAVIOR_ALLOW_INCOMPLETE;

        return $this;
    }

    /**
     * Set any subscription change as pending until payment is successful.
     *
     * @return $this
     */
    public function pendingIfPaymentFails(): static
    {
        $this->paymentBehavior = self::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE;

        return $this;
    }

    /**
     * Prevent any subscription change if payment is unsuccessful.
     *
     * @return $this
     */
    public function errorIfPaymentFails(): static
    {
        $this->paymentBehavior = self::PAYMENT_BEHAVIOR_ERROR_IF_INCOMPLETE;

        return $this;
    }

    /**
     * Determine the payment behavior when updating the subscription.
     */
    public function paymentBehavior(): string
    {
        return $this->paymentBehavior;
    }

    /**
     * Set the payment behavior for any subscription updates.
     *
     * @return $this
     */
    public function setPaymentBehavior(string $paymentBehavior): static
    {
        $this->paymentBehavior = $paymentBehavior;

        return $this;
    }
}
