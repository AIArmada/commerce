<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Orders\Contracts\OrderAdministration;
use AIArmada\Orders\Features\Documents\Actions\CreateOrderInvoiceDoc;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Created;

it('rejects negative line inputs and floors over-discounted lines', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-LINE-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
    ]);

    foreach (['quantity', 'unit_price', 'discount_amount', 'tax_amount'] as $field) {
        $item = new OrderItem([
            'order_id' => $order->id,
            'name' => 'Widget',
            'quantity' => 1,
            'unit_price' => 100,
            $field => -1,
        ]);

        expect(fn (): int => $item->calculateTotal())
            ->toThrow(InvalidArgumentException::class, 'cannot be negative');
    }

    $floored = new OrderItem([
        'order_id' => $order->id,
        'name' => 'Widget',
        'quantity' => 1,
        'unit_price' => 100,
        'discount_amount' => 500,
        'tax_amount' => 10,
    ]);

    expect($floored->calculateTotal())->toBe(10);
});

it('treats the order discount as already including line discounts', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-DISC-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 12000,
        'discount_total' => 1000,
        'shipping_total' => 500,
        'tax_total' => 600,
        'grand_total' => 12100,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'name' => 'Discounted Widget',
        'quantity' => 1,
        'unit_price' => 12000,
        'discount_amount' => 1000,
        'tax_amount' => 600,
        'currency' => 'MYR',
    ]);

    app(OrderAdministration::class)->recalculateTotals((string) $order->getKey());

    expect($order->refresh()->grand_total)->toBe(12100);
});

it('builds invoice documents for orders carrying line discounts', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-DOC-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 12000,
        'discount_total' => 1000,
        'shipping_total' => 500,
        'tax_total' => 600,
        'grand_total' => 12100,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'name' => 'Discounted Widget',
        'quantity' => 1,
        'unit_price' => 12000,
        'discount_amount' => 1000,
        'tax_amount' => 600,
        'currency' => 'MYR',
    ]);

    $docId = app(CreateOrderInvoiceDoc::class)->execute($order, 'txn_docs_disc', 'stripe');
    $doc = Doc::query()->findOrFail($docId);

    expect($doc)->not->toBeNull()
        ->and($doc->total_minor)->toBe(12100);
});

it('rejects order line arithmetic overflow', function (): void {
    $item = new OrderItem(['quantity' => 2, 'unit_price' => PHP_INT_MAX, 'discount_amount' => 0, 'tax_amount' => 0]);
    expect(fn (): int => $item->calculateTotal())->toThrow(InvalidArgumentException::class, 'subtotal exceeds');

    $item = new OrderItem(['quantity' => 1, 'unit_price' => PHP_INT_MAX, 'discount_amount' => 0, 'tax_amount' => 1]);
    expect(fn (): int => $item->calculateTotal())->toThrow(InvalidArgumentException::class, 'total exceeds');
});

it('floors over-discounted order totals at zero and rejects unfolded line discounts', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-TOTAL-GUARD-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'discount_total' => 200,
    ]);

    OrderItem::create([
        'order_id' => $order->getKey(),
        'name' => 'Discounted item',
        'quantity' => 1,
        'unit_price' => 100,
        'discount_amount' => 100,
        'currency' => 'MYR',
    ]);

    $administration = app(OrderAdministration::class);
    $administration->recalculateTotals((string) $order->getKey());

    expect($order->refresh()->grand_total)->toBe(0);

    $order->update(['discount_total' => 50]);

    expect(fn () => $administration->recalculateTotals((string) $order->getKey()))
        ->toThrow(InvalidArgumentException::class, 'Line discounts must be included');
});
