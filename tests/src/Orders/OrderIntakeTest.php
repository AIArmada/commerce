<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\CreateOrder;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('creates order with intake identity', function (): void {
    $order = OwnerContext::withOwner(null, fn () => Order::create([
        'order_number' => 'ORD-INTK-' . uniqid(),
        'status' => \AIArmada\Orders\States\Created::class,
        'intake_source' => 'checkout',
        'intake_id' => 'sess_abc123',
        'currency' => 'MYR',
        'subtotal' => 5000,
        'grand_total' => 5000,
    ]));

    expect($order->intake_source)->toBe('checkout');
    expect($order->intake_id)->toBe('sess_abc123');
});

it('exact retry with same intake identity returns existing order', function (): void {
    $createOrder = new CreateOrder;

    $order1 = OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Test Item', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'checkout',
        intakeId: 'sess_dup_test',
    ));

    $order2 = OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Test Item', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'checkout',
        intakeId: 'sess_dup_test',
    ));

    expect($order2->id)->toBe($order1->id);
    expect(Order::query()
        ->withoutGlobalScope(\AIArmada\CommerceSupport\Support\OwnerScope::class)
        ->where('intake_source', 'checkout')
        ->where('intake_id', 'sess_dup_test')
        ->count())->toBe(1);
});

it('without intake identity creates new order each call', function (): void {
    $createOrder = new CreateOrder;

    $order1 = OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Item A', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
    ));

    $order2 = OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Item A', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
    ));

    expect($order2->id)->not->toBe($order1->id);
});

it('different intake source with same id creates separate orders', function (): void {
    $createOrder = new CreateOrder;

    $order1 = OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Item A', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'checkout',
        intakeId: 'same_id',
    ));

    $order2 = OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Item A', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'api',
        intakeId: 'same_id',
    ));

    expect($order2->id)->not->toBe($order1->id);
});

it('database unique constraint prevents concurrent duplicate intake', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $testOwnerId = (string) Str::uuid();

        DB::table('orders')->insert([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-CONC-1-' . uniqid(),
            'status' => 'created',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => $testOwnerId,
            'intake_source' => 'checkout',
            'intake_id' => 'concurrent_abc',
            'currency' => 'MYR',
            'subtotal' => 5000,
            'grand_total' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('orders')->insert([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-CONC-2-' . uniqid(),
            'status' => 'created',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => $testOwnerId,
            'intake_source' => 'checkout',
            'intake_id' => 'concurrent_abc',
            'currency' => 'MYR',
            'subtotal' => 5000,
            'grand_total' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});

it('retried intake includes loaded relationships', function (): void {
    $createOrder = new CreateOrder;

    $order1 = OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Item A', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        billingAddress: ['first_name' => 'John', 'last_name' => 'Doe', 'line1' => '123 Main St', 'city' => 'KL', 'postcode' => '50000'],
        intakeSource: 'checkout',
        intakeId: 'sess_relationships',
    ));

    $order2 = OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Item A', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'checkout',
        intakeId: 'sess_relationships',
    ));

    expect($order2->id)->toBe($order1->id);
    expect($order2->relationLoaded('items'))->toBeTrue();
    expect($order2->relationLoaded('billingAddress'))->toBeTrue();
    expect($order2->relationLoaded('shippingAddress'))->toBeTrue();
});

it('throws conflict exception when retry has different customer', function (): void {
    $createOrder = new CreateOrder;

    OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000, 'customer_id' => '1'],
        items: [['name' => 'Test Item', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'checkout',
        intakeId: 'test-1',
    ));

    expect(fn () => OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000, 'customer_id' => '2'],
        items: [['name' => 'Test Item', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'checkout',
        intakeId: 'test-1',
    )))->toThrow(AIArmada\Orders\Exceptions\OrderIntakeConflictException::class);
});

it('dispatches OrderCreated only once and not on retry', function (): void {
    Event::fake();

    $createOrder = new CreateOrder;

    OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Test Item', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'checkout',
        intakeId: 'sess_event_test',
    ));

    Event::assertDispatched(AIArmada\Orders\Events\OrderCreated::class, 1);

    OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Test Item', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'checkout',
        intakeId: 'sess_event_test',
    ));

    Event::assertDispatched(AIArmada\Orders\Events\OrderCreated::class, 1);
});
