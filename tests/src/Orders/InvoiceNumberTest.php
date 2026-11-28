<?php

declare(strict_types=1);

use AIArmada\Orders\Contracts\OrderInvoiceNumber;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Runtime\OrderInvoiceDownload;
use AIArmada\Orders\States\Completed;

it('mints an invoice number once and reuses it on later downloads', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-INV-' . uniqid(),
        'status' => Completed::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    expect($order->invoice_number)->toBeNull();

    app(OrderInvoiceDownload::class)->download((string) $order->getKey());
    $first = $order->refresh()->invoice_number;

    expect($first)->not->toBeNull();

    app(OrderInvoiceDownload::class)->download((string) $order->getKey());

    expect($order->refresh()->invoice_number)->toBe($first);
});

it('gives every order its own invoice number', function (): void {
    $first = Order::create([
        'order_number' => 'ORD-INVA-' . uniqid(),
        'status' => Completed::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
    $second = Order::create([
        'order_number' => 'ORD-INVB-' . uniqid(),
        'status' => Completed::class,
        'currency' => 'MYR',
        'subtotal' => 20000,
        'grand_total' => 20000,
    ]);

    app(OrderInvoiceDownload::class)->download((string) $first->getKey());
    app(OrderInvoiceDownload::class)->download((string) $second->getKey());

    expect($first->refresh()->invoice_number)->not->toBe($second->refresh()->invoice_number);
});

it('reuses the persisted invoice number when the caller holds a stale order', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-INV-STALE-' . uniqid(),
        'status' => Completed::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $stale = Order::query()->findOrFail($order->getKey());
    $action = app(OrderInvoiceNumber::class);

    $first = $action->assign((string) $order->getKey());

    expect($stale->invoice_number)->toBeNull()
        ->and($action->assign((string) $stale->getKey()))->toBe($first)
        ->and($order->refresh()->invoice_number)->toBe($first);
});
