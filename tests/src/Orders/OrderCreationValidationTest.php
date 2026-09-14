<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\CreateOrder;
use AIArmada\Orders\Exceptions\OrderIntakeConflictException;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

function makeValidOrderData(array $overrides = []): array
{
    return array_merge([
        'order_number' => 'ORD-VAL-' . uniqid(),
        'currency' => 'MYR',
        'subtotal' => 5000,
        'grand_total' => 5000,
    ], $overrides);
}

function makeValidItem(array $overrides = []): array
{
    return array_merge([
        'name' => 'Widget',
        'quantity' => 1,
        'unit_price' => 5000,
    ], $overrides);
}

it('rejects items without a usable name', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-NAME-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
    ]);

    expect(fn () => app(CreateOrder::class)->addItem($order, makeValidItem(['name' => null])))
        ->toThrow(InvalidArgumentException::class, 'name is required');

    $nameless = makeValidItem();
    unset($nameless['name']);

    expect(fn () => app(CreateOrder::class)->addItem($order, $nameless))
        ->toThrow(InvalidArgumentException::class, 'name is required');
});

it('rejects non-positive quantities and negative amounts', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-QTY-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
    ]);
    $action = app(CreateOrder::class);

    foreach ([0, -2] as $quantity) {
        expect(fn () => $action->addItem($order, makeValidItem(['quantity' => $quantity])))
            ->toThrow(InvalidArgumentException::class, 'quantity');
    }

    foreach (['unit_price', 'discount_amount', 'tax_amount'] as $field) {
        expect(fn () => $action->addItem($order, makeValidItem([$field => -1])))
            ->toThrow(InvalidArgumentException::class, 'cannot be negative');
    }

    expect($order->items()->count())->toBe(0);
});

it('rejects negative totals and an uncovered zero grand total', function (): void {
    expect(fn (): Order => app(CreateOrder::class)->execute(
        makeValidOrderData(['subtotal' => -5, 'grand_total' => -5]),
        [makeValidItem()],
    ))->toThrow(InvalidArgumentException::class, 'cannot be negative');

    expect(fn (): Order => app(CreateOrder::class)->execute(
        makeValidOrderData(['subtotal' => 5000, 'grand_total' => 0]),
        [makeValidItem()],
    ))->toThrow(InvalidArgumentException::class, 'zero grand_total');
});

it('accepts a covered zero grand total and engine-shaped totals', function (): void {
    $free = app(CreateOrder::class)->execute(
        makeValidOrderData(['subtotal' => 5000, 'discount_total' => 5000, 'grand_total' => 0]),
        [makeValidItem()],
    );

    expect($free->grand_total)->toBe(0);

    $engineShaped = app(CreateOrder::class)->execute(
        makeValidOrderData([
            'subtotal' => 15200,
            'discount_total' => 2000,
            'shipping_total' => 1000,
            'tax_total' => 1200,
            'grand_total' => 16200,
        ]),
        [makeValidItem(['quantity' => 2, 'unit_price' => 8000])],
    );

    expect($engineShaped->grand_total)->toBe(16200);
});

it('validates the whole payload before writing anything', function (): void {
    $countBefore = Order::query()->count();

    expect(fn (): Order => app(CreateOrder::class)->execute(
        makeValidOrderData(),
        [makeValidItem(), makeValidItem(['quantity' => 0])],
    ))->toThrow(InvalidArgumentException::class, 'quantity');

    expect(Order::query()->count())->toBe($countBefore);
});

it('serializes concurrent creates for one intake identity', function (): void {
    $source = 'checkout';
    $intakeId = 'session-' . uniqid();

    $first = OwnerContext::withOwner(null, fn (): Order => app(CreateOrder::class)->execute(
        makeValidOrderData(),
        [makeValidItem()],
        intakeSource: $source,
        intakeId: $intakeId,
    ));

    $second = OwnerContext::withOwner(null, fn (): Order => app(CreateOrder::class)->execute(
        makeValidOrderData(),
        [makeValidItem()],
        intakeSource: $source,
        intakeId: $intakeId,
    ));

    expect($second->id)->toBe($first->id)
        ->and(Order::query()->withoutOwnerScope()->where('intake_source', $source)->where('intake_id', $intakeId)->count())->toBe(1);
});

it('fails loudly when the intake identity is already being created', function (): void {
    $source = 'checkout';
    $intakeId = 'session-' . uniqid();

    $lock = Cache::lock('orders-intake-' . sha1('global|' . $source . '|' . $intakeId), 10);
    $lock->acquire();

    try {
        expect(fn (): Order => OwnerContext::withOwner(null, fn (): Order => app(CreateOrder::class)->execute(
            makeValidOrderData(),
            [makeValidItem()],
            intakeSource: $source,
            intakeId: $intakeId,
        )))->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }
});

it('accepts a whitespace-variant retry of the same intake', function (): void {
    $source = 'checkout';
    $intakeId = 'session-' . uniqid();

    $first = OwnerContext::withOwner(null, fn (): Order => app(CreateOrder::class)->execute(
        makeValidOrderData(['customer_id' => 'cust_1', 'customer_type' => 'customer']),
        [makeValidItem()],
        intakeSource: $source,
        intakeId: $intakeId,
    ));

    $retry = OwnerContext::withOwner(null, fn (): Order => app(CreateOrder::class)->execute(
        makeValidOrderData(['customer_id' => '  cust_1 ', 'customer_type' => 'customer ']),
        [makeValidItem()],
        intakeSource: $source,
        intakeId: $intakeId,
    ));

    expect($retry->id)->toBe($first->id);
});

it('keeps intake deduplication inside one scope', function (): void {
    config()->set('orders.owner.include_global', true);

    $source = 'checkout';
    $intakeId = 'session-' . uniqid();

    $global = OwnerContext::withOwner(null, fn (): Order => app(CreateOrder::class)->execute(
        makeValidOrderData(),
        [makeValidItem()],
        intakeSource: $source,
        intakeId: $intakeId,
    ));

    $owned = app(CreateOrder::class)->execute(
        makeValidOrderData(),
        [makeValidItem()],
        intakeSource: $source,
        intakeId: $intakeId,
    );

    expect($owned->id)->not->toBe($global->id)
        ->and($owned->owner_type)->not->toBeNull();
});

it('omits the existing order key from intake conflict errors', function (): void {
    $source = 'checkout';
    $intakeId = 'session-' . uniqid();

    $existing = OwnerContext::withOwner(null, fn (): Order => app(CreateOrder::class)->execute(
        makeValidOrderData(),
        [makeValidItem()],
        intakeSource: $source,
        intakeId: $intakeId,
    ));

    try {
        OwnerContext::withOwner(null, fn (): Order => app(CreateOrder::class)->execute(
            makeValidOrderData(['subtotal' => 9999, 'grand_total' => 9999]),
            [makeValidItem()],
            intakeSource: $source,
            intakeId: $intakeId,
        ));

        $this->fail('Expected an intake conflict.');
    } catch (OrderIntakeConflictException $e) {
        expect($e->getMessage())->not->toContain((string) $existing->getKey());
    }
});
