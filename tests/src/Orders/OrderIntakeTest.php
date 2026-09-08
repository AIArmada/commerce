<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Tests\Fixtures\TestOwner;
use AIArmada\Orders\Actions\CreateOrder;
use AIArmada\Orders\Events\OrderCreated;
use AIArmada\Orders\Exceptions\OrderIntakeConflictException;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates order with intake identity', function (): void {
    $order = OwnerContext::withOwner(null, fn () => Order::create([
        'order_number' => 'ORD-INTK-' . uniqid(),
        'status' => Created::class,
        'intake_source' => 'checkout',
        'intake_id' => 'sess_abc123',
        'currency' => 'MYR',
        'subtotal' => 5000,
        'grand_total' => 5000,
    ]));

    expect($order->intake_source)->toBe('checkout');
    expect($order->intake_id)->toBe('sess_abc123');
});

it('retries a generated order number after a unique collision', function (): void {
    config()->set('orders.order_number.use_date', false);

    $createOrder = new CreateOrder;
    Str::createRandomStringsUsingSequence([
        'DUPLICAT',
        'DUPLICAT',
        'UNIQUE01',
    ]);

    try {
        $first = OwnerContext::withOwner(null, fn (): Order => $createOrder->execute(
            orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
            items: [['name' => 'Item A', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        ));

        $second = OwnerContext::withOwner(null, fn (): Order => $createOrder->execute(
            orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
            items: [['name' => 'Item B', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        ));
    } finally {
        Str::createRandomStringsNormally();
    }

    expect($first->order_number)->toBe('ORD-DUPLICAT')
        ->and($second->order_number)->toBe('ORD-UNIQUE01')
        ->and($second->id)->not->toBe($first->id);
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
        ->withoutGlobalScope(OwnerScope::class)
        ->where('intake_source', 'checkout')
        ->where('intake_id', 'sess_dup_test')
        ->count())->toBe(1);
});

it('isolates the same intake identity between owners', function (): void {
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', false);
    config()->set('orders.owner.auto_assign_on_create', true);

    Schema::dropIfExists('test_owners');
    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);
    $createOrder = new CreateOrder;

    $orderData = [
        'currency' => 'MYR',
        'subtotal' => 5000,
        'grand_total' => 5000,
    ];
    $items = [['name' => 'Test Item', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']];

    $orderA = OwnerContext::withOwner($ownerA, fn () => $createOrder->execute(
        orderData: $orderData,
        items: $items,
        intakeSource: 'checkout',
        intakeId: 'shared-intake',
    ));
    $orderB = OwnerContext::withOwner($ownerB, fn () => $createOrder->execute(
        orderData: $orderData,
        items: $items,
        intakeSource: 'checkout',
        intakeId: 'shared-intake',
    ));

    expect($orderB->id)->not->toBe($orderA->id)
        ->and(OwnerContext::withOwner($ownerA, fn () => Order::query()
            ->where('intake_source', 'checkout')
            ->where('intake_id', 'shared-intake')
            ->value('id')))->toBe($orderA->id)
        ->and(OwnerContext::withOwner($ownerB, fn () => Order::query()
            ->where('intake_source', 'checkout')
            ->where('intake_id', 'shared-intake')
            ->value('id')))->toBe($orderB->id);
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
        ]))->toThrow(QueryException::class);
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
    )))->toThrow(OrderIntakeConflictException::class);
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

    Event::assertDispatched(OrderCreated::class, 1);

    OwnerContext::withOwner(null, fn () => $createOrder->execute(
        orderData: ['currency' => 'MYR', 'subtotal' => 5000, 'grand_total' => 5000],
        items: [['name' => 'Test Item', 'quantity' => 1, 'unit_price' => 5000, 'currency' => 'MYR']],
        intakeSource: 'checkout',
        intakeId: 'sess_event_test',
    ));

    Event::assertDispatched(OrderCreated::class, 1);
});
