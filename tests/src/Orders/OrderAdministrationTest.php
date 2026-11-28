<?php

declare(strict_types=1);

use AIArmada\Orders\Contracts\OrderAdministration;
use AIArmada\Orders\Features\Payments\Actions\RegisterOrderPayment;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\PendingPayment;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

it('creates draft orders and restricts administrative edits to notes', function (): void {
    $admin = app(OrderAdministration::class);
    $id = $admin->createDraft('Customer note', 'Internal note');
    $order = Order::query()->findOrFail($id);

    expect($order->status)->toBeInstanceOf(Created::class)
        ->and($order->notes)->toBe('Customer note')
        ->and($order->internal_notes)->toBe('Internal note');

    $admin->updateNotes($id, ['notes' => 'Updated', 'grand_total' => 9999]);

    expect($order->refresh()->notes)->toBe('Updated')
        ->and($order->grand_total)->toBe(0);

    $admin->deleteDrafts([$id]);
    expect(Order::query()->find($id))->toBeNull();
});

it('protects partially paid orders from administrative deletion', function (): void {
    $order = Order::query()->create([
        'status' => PendingPayment::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
    app(RegisterOrderPayment::class)->execute($order, 'partial-' . $order->getKey(), 'manual', 5000);

    expect(fn () => app(OrderAdministration::class)->deleteDrafts([(string) $order->getKey()]))
        ->toThrow(LogicException::class);

    expect($order->refresh()->getTotalPaid())->toBe(5000)
        ->and($order->payments()->count())->toBe(1);
});
