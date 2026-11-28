<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Contracts\OrderOutboxWriter;
use AIArmada\Orders\Contracts\OrderPaymentLifecycle;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PaymentFailed;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class ApplyOrderPaymentLifecycle implements OrderPaymentLifecycle
{
    use AssertsOrderOwnerBoundary;

    public function markPaid(string $orderId): void
    {
        (new Order)->getConnection()->transaction(function () use ($orderId): void {
            $order = $this->lockOrder($orderId);

            if ($order->getTotalPaid() <= 0 || ! $order->isFullyPaid()) {
                throw new LogicException('Only fully paid orders can record payment settlement.');
            }

            $order->status->transitionTo(Processing::class);
            $order->paid_at = CarbonImmutable::now();
            $order->save();
        });
    }

    public function markRefunded(string $orderId): void
    {
        (new Order)->getConnection()->transaction(function () use ($orderId): void {
            $order = $this->lockOrder($orderId);

            if ($order->getTotalPaid() <= 0 || $order->getTotalRefunded() < $order->getTotalPaid()) {
                throw new LogicException('Only fully refunded orders can record refund settlement.');
            }

            if (! ($order->status instanceof Refunded)) {
                $order->status->transitionTo(Refunded::class);
            }

            $order->refunded_at ??= CarbonImmutable::now();
            $order->save();

            app(OrderOutboxWriter::class)->suppressForOrder($orderId, 'Order fully refunded; fulfillment suppressed.');
        });
    }

    public function markPaymentFailed(string $orderId): void
    {
        (new Order)->getConnection()->transaction(function () use ($orderId): void {
            $order = $this->lockOrder($orderId);
            $order->payment_failed_at = CarbonImmutable::now();
            $order->status->transitionTo(PaymentFailed::class);
            $order->save();
        });
    }

    private function lockOrder(string $orderId): Order
    {
        $order = Order::query()->forOwner()->lockForUpdate()->findOrFail($orderId);
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return $order;
    }
}
