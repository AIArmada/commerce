<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\CancelOrder;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\PendingPayment;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

describe('CancelOrder', function (): void {
    it('can cancel an order and record reason', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-CXL-ACT-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        $action = new CancelOrder;

        $result = OwnerContext::withOwner(null, fn (): Order => $action->execute($order, 'Customer request', 'admin@test.com'));

        expect($result)->toBe($order);
        expect($order->status)->toBeInstanceOf(Canceled::class);
        expect($order->cancellation_reason)->toBe('Customer request');
        expect($order->orderNotes)->toHaveCount(1);
    });

    it('throws when order cannot be canceled', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-NOCXL-' . uniqid(),
            'status' => Canceled::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
            'canceled_at' => now(),
            'cancellation_reason' => 'Already canceled',
        ]);

        $action = new CancelOrder;

        expect(fn () => $action->execute($order, 'Double cancel'))
            ->toThrow(RuntimeException::class, 'cannot be canceled');
    });
});
