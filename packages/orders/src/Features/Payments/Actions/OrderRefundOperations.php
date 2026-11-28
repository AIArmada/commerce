<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Payments\Actions;

use AIArmada\Orders\Contracts\OrderRefunds;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;

final readonly class OrderRefundOperations implements OrderRefunds
{
    public function __construct(private RegisterOrderRefund $refunds) {}

    public function processRefund(string $orderId, int $amount, string $transactionId, string $reason, array $metadata = [], ?string $paymentId = null): void
    {
        $order = Order::query()->forOwner()->findOrFail($orderId);

        $this->refunds->execute($order, $amount, $transactionId, $reason, $metadata, $this->payment($order, $paymentId));
    }

    public function createPendingRefund(string $orderId, int $amount, string $transactionId, string $reason, array $metadata = [], ?string $paymentId = null): array
    {
        $order = Order::query()->forOwner()->findOrFail($orderId);

        return array_map(
            static fn (OrderRefund $refund): string => (string) $refund->getKey(),
            $this->refunds->createPending($order, $amount, $transactionId, $reason, $metadata, $this->payment($order, $paymentId)),
        );
    }

    public function claimPendingRefundSubmission(string $refundId): bool
    {
        return $this->refunds->claimPendingSubmission(OrderRefund::query()->forOwner()->findOrFail($refundId));
    }

    public function correlateProviderReference(string $refundId, ?string $providerRefundId, ?string $relatedPaymentId = null): bool
    {
        return $this->refunds->correlateProviderReference(OrderRefund::query()->forOwner()->findOrFail($refundId), $providerRefundId, $relatedPaymentId);
    }

    public function completePendingRefund(string $refundId, ?string $transactionId = null): void
    {
        $this->refunds->completePending(OrderRefund::query()->forOwner()->findOrFail($refundId), $transactionId);
    }

    public function failPendingRefund(string $refundId, string $reason): void
    {
        $this->refunds->failPending(OrderRefund::query()->forOwner()->findOrFail($refundId), $reason);
    }

    private function payment(Order $order, ?string $paymentId): ?OrderPayment
    {
        return $paymentId === null ? null : $order->payments()->findOrFail($paymentId);
    }
}
