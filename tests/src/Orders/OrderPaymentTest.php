<?php

declare(strict_types=1);

use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;

describe('OrderPayment Model', function (): void {
    describe('OrderPayment Creation', function (): void {
        it('can create a payment', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => PaymentStatus::Pending,
            ]);

            expect($payment)->toBeInstanceOf(OrderPayment::class)
                ->and($payment->gateway)->toBe('stripe')
                ->and($payment->amount)->toBe(10000)
                ->and($payment->status)->toBe(PaymentStatus::Pending);
        });

        it('belongs to an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'manual',
                'amount' => 5000,
                'currency' => 'MYR',
                'status' => PaymentStatus::Completed,
            ]);

            expect($payment->order->id)->toBe($order->id);
        });
    });

    describe('OrderPayment Status Helpers', function (): void {
        it('can check payment status', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $pending = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 5000,
                'currency' => 'MYR',
                'status' => PaymentStatus::Pending,
            ]);

            $completed = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 3000,
                'currency' => 'MYR',
                'status' => PaymentStatus::Completed,
            ]);

            $failed = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 2000,
                'currency' => 'MYR',
                'status' => PaymentStatus::Failed,
            ]);

            expect($pending->isPending())->toBeTrue()
                ->and($pending->isCompleted())->toBeFalse()
                ->and($pending->isFailed())->toBeFalse()
                ->and($pending->isRefunded())->toBeFalse();

            expect($completed->isPending())->toBeFalse()
                ->and($completed->isCompleted())->toBeTrue()
                ->and($completed->isFailed())->toBeFalse()
                ->and($completed->isRefunded())->toBeFalse();

            expect($failed->isPending())->toBeFalse()
                ->and($failed->isCompleted())->toBeFalse()
                ->and($failed->isFailed())->toBeTrue()
                ->and($failed->isRefunded())->toBeFalse();
        });
    });

    describe('OrderPayment Actions', function (): void {
        it('can mark payment as completed', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY4-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => PaymentStatus::Pending,
            ]);

            $result = $payment->markAsCompleted('txn_123');

            expect($result)->toBe($payment)
                ->and($payment->status)->toBe(PaymentStatus::Completed)
                ->and($payment->transaction_id)->toBe('txn_123')
                ->and($payment->paid_at)->not->toBeNull();
        });

        it('can mark payment as failed', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY5-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => PaymentStatus::Pending,
            ]);

            $result = $payment->markAsFailed('Card declined');

            expect($result)->toBe($payment)
                ->and($payment->status)->toBe(PaymentStatus::Failed)
                ->and($payment->failure_reason)->toBe('Card declined');
        });
    });

    describe('OrderPayment Formatting', function (): void {
        it('can format amount in different currencies', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY6-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $myrPayment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => PaymentStatus::Completed,
            ]);

            $usdPayment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 5000,
                'currency' => 'USD',
                'status' => PaymentStatus::Completed,
            ]);

            $eurPayment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 7500,
                'currency' => 'EUR',
                'status' => PaymentStatus::Completed,
            ]);

            $gbpPayment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 2500,
                'currency' => 'GBP',
                'status' => PaymentStatus::Completed,
            ]);

            expect($myrPayment->getFormattedAmount())->toBe('RM100.00');
            expect($usdPayment->getFormattedAmount())->toBe('$50.00');
            // EUR uses European formatting (comma as decimal separator)
            expect($eurPayment->getFormattedAmount())->toBe('€75,00');
            expect($gbpPayment->getFormattedAmount())->toBe('£25.00');
        });
    });
});
