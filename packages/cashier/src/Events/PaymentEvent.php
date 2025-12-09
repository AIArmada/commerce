<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Events;

use AIArmada\Cashier\Contracts\PaymentContract;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base event for payment-related events with gateway support.
 */
abstract class PaymentEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly PaymentContract $payment,
        public readonly string $gateway,
        public readonly mixed $billable = null,
    ) {}

    /**
     * Get the payment instance.
     */
    final public function payment(): PaymentContract
    {
        return $this->payment;
    }

    /**
     * Get the gateway name.
     */
    final public function gateway(): string
    {
        return $this->gateway;
    }

    /**
     * Get the billable model.
     */
    final public function billable(): mixed
    {
        return $this->billable;
    }
}
