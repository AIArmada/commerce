<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Payments\Actions;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderPaymentConfirmation;
use AIArmada\Orders\Models\Order;
use InvalidArgumentException;

final readonly class ConfirmOrderPayment implements OrderPaymentConfirmation
{
    use AssertsOrderOwnerBoundary;

    public function __construct(private RegisterOrderPayment $payments) {}

    public function confirm(string $orderId, string $transactionId, string $gateway, int $amount, array $metadata = []): void
    {
        $this->payments->execute(Order::query()->forOwner()->findOrFail($orderId), $transactionId, $gateway, $amount, $metadata);
    }

    public function confirmCheckout(string $orderId, string $transactionId, string $gateway, int $amount, string $currency, array $metadata = []): void
    {
        (new Order)->getConnection()->transaction(function () use ($orderId, $transactionId, $gateway, $amount, $currency, $metadata): void {
            $order = Order::query()->forOwner()->lockForUpdate()->findOrFail($orderId);
            $this->assertOwnerBoundaryForMutation($order, __METHOD__);

            if ((int) $order->grand_total !== $amount || MoneyFormatter::normalizeCurrency($order->currency) !== MoneyFormatter::normalizeCurrency($currency)) {
                throw new InvalidArgumentException('Checkout payment does not match the locked order total and currency.');
            }

            $this->payments->execute($order, $transactionId, $gateway, $amount, $metadata);
        });
    }

    public function settleOutstanding(string $orderId, string $transactionId, string $gateway): void
    {
        (new Order)->getConnection()->transaction(function () use ($orderId, $transactionId, $gateway): void {
            $order = Order::query()->forOwner()->lockForUpdate()->findOrFail($orderId);
            $this->assertOwnerBoundaryForMutation($order, __METHOD__);

            $existing = $order->payments()
                ->where('gateway', $gateway)
                ->where('transaction_id', $transactionId)
                ->first();

            $this->payments->execute($order, $transactionId, $gateway, $existing?->amount ?? $order->getBalanceDue());
        });
    }
}
