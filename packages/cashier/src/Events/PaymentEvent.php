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
    use Dispatchable, SerializesModels;

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
    public function payment(): PaymentContract
    {
        return $this->payment;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return $this->gateway;
    }

    /**
     * Get the billable model.
     */
    public function billable(): mixed
    {
        return $this->billable;
    }
}
