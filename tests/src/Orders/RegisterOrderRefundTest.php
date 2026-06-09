<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\RegisterOrderRefund;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\States\Returned;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

describe('RegisterOrderRefund', function (): void {
    it('can process a refund and transition order', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-REF-ACT-' . uniqid(),
            'status' => Returned::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        OrderPayment::create([
            'order_id' => $order->id,
            'gateway' => 'stripe',
            'amount' => 10000,
            'currency' => 'MYR',
            'status' => \AIArmada\Orders\Enums\PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $action = new RegisterOrderRefund;

        $result = OwnerContext::withOwner(null, fn (): Order => $action->execute($order, 5000, 'ref_txn_1', 'Partial refund'));

        expect($result)->toBe($order);
        expect($order->status)->toBeInstanceOf(Refunded::class);
        expect($order->refunds)->toHaveCount(1);
        expect($order->refunds->first()->amount)->toBe(5000);
    });

    it('throws when order cannot be refunded', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-NOREF-' . uniqid(),
            'status' => \AIArmada\Orders\States\PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        $action = new RegisterOrderRefund;

        expect(fn () => $action->execute($order, 5000, 'txn_fail', 'Not possible'))
            ->toThrow(RuntimeException::class, 'cannot be refunded');
    });
});
