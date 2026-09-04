<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\RegisterOrderRefund;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\PendingPayment;
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
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $action = new RegisterOrderRefund;

        $result = OwnerContext::withOwner(null, fn (): Order => $action->execute($order, 5000, 'ref_txn_1', 'Partial refund'));

        expect($result)->toBe($order);
        expect($order->status)->not->toBeInstanceOf(Refunded::class);
        expect($order->refunds)->toHaveCount(1);
        expect($order->refunds->first()->amount)->toBe(5000);

        $action->execute($order, 5000, 'ref_txn_2', 'Remaining refund');

        expect($order->status)->toBeInstanceOf(Refunded::class);
    });

    it('throws when order cannot be refunded', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-NOREF-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        $action = new RegisterOrderRefund;

        expect(fn () => $action->execute($order, 5000, 'txn_fail', 'Not possible'))
            ->toThrow(RuntimeException::class, 'cannot be refunded');
    });

    it('returns the existing pending refund for a repeated transaction id', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-IDEMPOTENT-' . uniqid(),
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
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $action = new RegisterOrderRefund;
        $first = $action->createPending($order, 5000, 'same-key', 'Duplicate-safe refund');
        $second = $action->createPending($order, 5000, 'same-key', 'Duplicate-safe refund');

        expect($second->getKey())->toBe($first->getKey())
            ->and($order->refresh()->refunds()->count())->toBe(1);
    });

    it('claims a pending refund submission only once', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-REF-CLAIM-' . uniqid(),
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
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $action = new RegisterOrderRefund;
        $refund = $action->createPending($order, 5000, 'claim-key', 'Claimed refund');

        expect($action->claimPendingSubmission($refund))->toBeTrue()
            ->and($action->claimPendingSubmission($refund))->toBeFalse()
            ->and($refund->fresh()->hasProviderSubmissionStarted())->toBeTrue();
    });

    it('does not create another refund after a transaction has failed', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-REF-FAILED-' . uniqid(),
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
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $action = new RegisterOrderRefund;
        $refund = $action->createPending($order, 5000, 'failed-key', 'Failed refund');
        $refund->markAsFailed('Provider rejected the refund.');

        expect(fn () => $action->createPending($order, 5000, 'failed-key', 'Retry refund'))
            ->toThrow(RuntimeException::class, 'has already failed')
            ->and($order->refresh()->refunds()->count())->toBe(1);
    });
});
