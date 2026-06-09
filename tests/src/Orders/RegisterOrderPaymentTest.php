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

        expect($result)->toBe($order);
        expect($order->status)->toBeInstanceOf(Processing::class);
        expect($order->paid_at)->not->toBeNull();
        expect($order->payments)->toHaveCount(1);
        expect($order->payments->first()->transaction_id)->toBe('txn_action_1');
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
});
