<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Events;

use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Support\SnapshotPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base event for payment-related events with gateway support.
 *
 * Live payments wrap gateway SDK objects, so only a scalar snapshot crosses
 * the queue boundary. Queued listeners must re-resolve the live payment
 * when they need gateway operations.
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
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'payment' => SnapshotPayment::capture($this->payment),
            'gateway' => $this->gateway,
            'billable' => $this->getSerializedPropertyValue($this->billable),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function __unserialize(array $values): void
    {
        $this->payment = SnapshotPayment::fromSnapshot((array) ($values['payment'] ?? []));
        $this->gateway = (string) ($values['gateway'] ?? '');
        $this->billable = $this->getRestoredPropertyValue($values['billable'] ?? null);
    }

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

    /**
     * @return array<string, mixed>
     */
    final public function metadata(): array
    {
        return $this->payment->metadata();
    }
}
