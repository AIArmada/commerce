<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\CompleteOrder;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Completed;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

describe('CompleteOrder', function (): void {
    it('can complete an order and record metadata', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-CMP-ACT-' . uniqid(),
            'status' => \AIArmada\Orders\States\Processing::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        $action = new CompleteOrder;

        $result = OwnerContext::withOwner(null, fn (): Order => $action->execute($order, ['source' => 'manual']));

        expect($result)->toBe($order);
        expect($order->status)->toBeInstanceOf(Completed::class);
        expect($order->metadata)->toHaveKey('completion');
        expect($order->metadata['completion'])->toHaveKey('completed_at');
    });

    it('merges completion metadata with existing metadata', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-CMP-META-' . uniqid(),
            'status' => \AIArmada\Orders\States\Processing::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
            'metadata' => ['existing' => 'value'],
        ]);

        $action = new CompleteOrder;

        $result = OwnerContext::withOwner(null, fn (): Order => $action->execute($order, ['note' => 'All good']));

        expect($result->metadata)->toHaveKey('existing');
        expect($result->metadata['completion']['metadata']['note'])->toBe('All good');
    });
});
