<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

/**
 * Proration behavior for subscription changes.
 *
 * Note: CHIP doesn't support proration natively like Stripe.
 * These methods are provided for API compatibility, but proration
 * logic must be handled at the application level.
 */
trait Prorates
{
    /**
     * Indicates if the price change should be prorated.
     */
    protected string $prorationBehavior = 'create_prorations';

    /**
     * Indicate that the price change should not be prorated.
     *
     * @return $this
     */
    public function noProrate(): static
    {
        $this->prorationBehavior = 'none';

        return $this;
    }

    /**
     * Indicate that the price change should be prorated.
     *
     * @return $this
     */
    public function prorate(): static
    {
        $this->prorationBehavior = 'create_prorations';

        return $this;
    }

    /**
     * Indicate that the price change should always be invoiced.
     *
     * @return $this
     */
    public function alwaysInvoice(): static
    {
        $this->prorationBehavior = 'always_invoice';

        return $this;
    }

    /**
     * Set the prorating behavior.
     *
     * @return $this
     */
    public function setProrationBehavior(string $prorationBehavior): static
    {
        $this->prorationBehavior = $prorationBehavior;

        return $this;
    }

    /**
     * Determine the prorating behavior when updating the subscription.
     */
    public function prorateBehavior(): string
    {
        return $this->prorationBehavior;
    }
}
