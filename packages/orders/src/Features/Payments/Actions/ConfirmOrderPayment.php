<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Payments\Actions;

use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderPaymentConfirmation;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmOrderPayment implements OrderPaymentConfirmation
{
    use AssertsOrderOwnerBoundary;

    public function __construct(private RegisterOrderPayment $payments) {}

    public function confirm(string $orderId, string $transactionId, string $gateway, int $amount, array $metadata = []): void
    {
        $this->payments->execute(Order::query()->findOrFail($orderId), $transactionId, $gateway, $amount, $metadata);
    }

    public function settleOutstanding(string $orderId, string $transactionId, string $gateway): void
    {
        DB::transaction(function () use ($orderId, $transactionId, $gateway): void {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $this->assertOwnerBoundaryForMutation($order, __METHOD__);

            $existing = $order->payments()
                ->where('gateway', $gateway)
                ->where('transaction_id', $transactionId)
                ->first();

            $this->payments->execute($order, $transactionId, $gateway, $existing?->amount ?? $order->getBalanceDue());
        });
    }
}
