<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\RegisterOrderPayment;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

describe('RegisterOrderPayment', function (): void {
    it('can register a payment and transition order', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-PAY-ACT-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        $action = new RegisterOrderPayment;

        $result = OwnerContext::withOwner(null, fn (): Order => $action->execute($order, 'txn_action_1', 'stripe', 10000));

        expect($result)->toBeInstanceOf(Order::class)
            ->and($result->getKey())->toBe($order->getKey());
        expect($result->status)->toBeInstanceOf(Processing::class);
        expect($result->paid_at)->not->toBeNull();
        expect($result->payments)->toHaveCount(1);
        expect($result->payments->first()->transaction_id)->toBe('txn_action_1');
    });

    it('records payment with metadata', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-PAY-META-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 5000,
            'grand_total' => 5000,
        ]);

        $action = new RegisterOrderPayment;
        $metadata = ['source' => 'api', 'notes' => 'auto-charge'];

        $result = OwnerContext::withOwner(null, fn (): Order => $action->execute($order, 'txn_meta_1', 'chip', 5000, $metadata));

        expect($result->payments->first()->metadata)->toBe($metadata);
    });

    it('does not duplicate a repeated gateway transaction', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-PAY-IDEMPOTENT-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        $action = new RegisterOrderPayment;

        OwnerContext::withOwner(null, function () use ($action, $order): void {
            $action->execute($order, 'txn_idempotent', 'stripe', 10000);
            $action->execute($order, 'txn_idempotent', 'stripe', 10000);
        });

        expect($order->payments()->where('transaction_id', 'txn_idempotent')->count())->toBe(1);
    });

    it('rejects a repeated gateway transaction with a different amount', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-PAY-MISMATCH-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        $action = new RegisterOrderPayment;

        expect(fn () => OwnerContext::withOwner(null, function () use ($action, $order): void {
            $action->execute($order, 'txn_mismatch', 'stripe', 10000);
            $action->execute($order, 'txn_mismatch', 'stripe', 9000);
        }))->toThrow(InvalidArgumentException::class, 'different amount or currency');
    });
});
