<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Payments\Actions;

use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderPaymentLifecycle;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Events\OrderPaymentFailed;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Spatie\ModelStates\Transition;

/**
 * Transition from PendingPayment → PaymentFailed.
 *
 * This transition is triggered when payment fails for an order.
 */
final class PaymentFailed extends Transition
{
    use AssertsOrderOwnerBoundary;

    public function __construct(
        private Order $order,
        private string $reason,
    ) {}

    public function handle(): Order
    {
        return $this->order->getConnection()->transaction(function (): Order {
            $this->order->newQuery()->lockForUpdate()->findOrFail($this->order->getKey());
            $this->order->refresh();
            $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

            $now = CarbonImmutable::now();

            // Mark the payment record as failed
            $payment = $this->order->payments()->where('status', PaymentStatus::Pending)->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();
            if ($payment !== null) {
                $payment->status = PaymentStatus::Failed;
                $payment->failure_reason = $this->reason;
                $payment->failed_at = $now;
                $payment->save();
            }

            // Update order state and payment_failed timestamp
            app(OrderPaymentLifecycle::class)->markPaymentFailed((string) $this->order->getKey());
            $this->order->refresh();

            // Dispatch event
            event(new OrderPaymentFailed($this->order, $this->reason));

            return $this->order;
        });
    }
}
