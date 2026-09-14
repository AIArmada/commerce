<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\CreateOrderInvoiceDoc;
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

    $order->recalculateTotals()->save();

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

    $doc = app(CreateOrderInvoiceDoc::class)->execute($order, 'txn_docs_disc', 'stripe');

    expect($doc)->not->toBeNull()
        ->and($doc->total_minor)->toBe(12100);
});
