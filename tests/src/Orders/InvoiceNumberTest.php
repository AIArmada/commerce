<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\GenerateInvoice;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Completed;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasColumn('orders', 'invoice_number')) {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('invoice_number')->nullable()->unique();
        });
    }
});

it('mints an invoice number once and reuses it on later downloads', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-INV-' . uniqid(),
        'status' => Completed::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    expect($order->invoice_number)->toBeNull();

    app(GenerateInvoice::class)->download($order->refresh());
    $first = $order->refresh()->invoice_number;

    expect($first)->not->toBeNull();

    app(GenerateInvoice::class)->download($order->refresh());

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

    app(GenerateInvoice::class)->download($first);
    app(GenerateInvoice::class)->download($second);

    expect($first->refresh()->invoice_number)->not->toBe($second->refresh()->invoice_number);
});
