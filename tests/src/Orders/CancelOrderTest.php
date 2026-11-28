<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Features\Lifecycle\Actions\OrderCanceled;
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

        $result = OwnerContext::withOwner(null, fn (): Order => (new OrderCanceled($order, 'Customer request', 'admin@test.com'))->handle());

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

        expect(fn () => (new OrderCanceled($order, 'Double cancel'))->handle())
            ->toThrow(RuntimeException::class, 'cannot be canceled');
    });
});

it('rejects cancellation when a stale order was already canceled', function (): void {
    $order = Order::factory()->create(['status' => PendingPayment::class]);
    $stale = Order::query()->findOrFail($order->getKey());

    (new OrderCanceled($order, 'First cancellation'))->handle();

    expect(fn () => (new OrderCanceled($stale, 'Stale cancellation'))->handle())
        ->toThrow(RuntimeException::class, 'cannot be canceled');
    expect($order->fresh()->cancellation_reason)->toBe('First cancellation')
        ->and($order->orderNotes()->count())->toBe(1);
});
