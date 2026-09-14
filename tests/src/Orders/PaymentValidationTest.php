<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\RegisterOrderPayment;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\PendingPayment;

function makePayableOrder(int $grandTotal = 10000): Order
{
    $order = Order::create([
        'order_number' => 'ORD-PAY-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ]);
    $order->status->transitionTo(PendingPayment::class);

    return $order->refresh();
}

it('rejects zero and negative payment amounts', function (): void {
    $order = makePayableOrder();

    expect(fn (): Order => app(RegisterOrderPayment::class)->execute($order, 'txn_zero', 'stripe', 0))
        ->toThrow(InvalidArgumentException::class, 'greater than zero');

    expect(fn (): Order => app(RegisterOrderPayment::class)->execute($order, 'txn_neg', 'stripe', -100))
        ->toThrow(InvalidArgumentException::class, 'greater than zero');

    expect($order->payments()->count())->toBe(0);
});

it('rejects a payment above the outstanding balance', function (): void {
    $order = makePayableOrder(10000);

    expect(fn (): Order => app(RegisterOrderPayment::class)->execute($order, 'txn_over', 'stripe', 10001))
        ->toThrow(InvalidArgumentException::class, 'outstanding balance');

    expect($order->payments()->count())->toBe(0);
});

it('measures the balance against earlier payments', function (): void {
    $order = makePayableOrder(10000);
    $action = app(RegisterOrderPayment::class);

    $action->execute($order, 'txn_part_1', 'stripe', 6000);

    expect(fn (): Order => $action->execute($order->refresh(), 'txn_part_2', 'stripe', 4001))
        ->toThrow(InvalidArgumentException::class, 'outstanding balance');

    expect($order->payments()->count())->toBe(1);
});

it('still replays a settled transaction idempotently', function (): void {
    $order = makePayableOrder(10000);
    $action = app(RegisterOrderPayment::class);

    $action->execute($order, 'txn_replay', 'stripe', 10000);
    $replayed = $action->execute($order->refresh(), 'txn_replay', 'stripe', 10000);

    expect($order->payments()->count())->toBe(1)
        ->and($replayed->id)->toBe($order->id);
});
