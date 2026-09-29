<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Inventory\Listeners\ReleaseInventoryFromOrder;
use AIArmada\Inventory\Models\InventoryOperation;
use AIArmada\Orders\Events\InventoryReleaseRequired;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use Illuminate\Support\Str;

function createOwnedReleaseOrder(User $owner, string $number): Order
{
    return OwnerContext::withOwner($owner, static fn (): Order => Order::create([
        'order_number' => $number,
        'status' => PendingPayment::class,
        'currency' => 'USD',
        'subtotal' => 10000,
        'grand_total' => 10000,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]));
}

function simulateReleaseQueueWorkerWithoutAmbientOwner(): void
{
    OwnerContext::flushState();
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);
}

function findReleaseOperation(Order $order): ?InventoryOperation
{
    return InventoryOperation::query()
        ->withoutGlobalScope(OwnerScope::class)
        ->where('order_id', $order->getKey())
        ->where('kind', InventoryOperation::KIND_RELEASE)
        ->first();
}

test('release restores the order owner context when ambient context is missing', function (): void {
    config()->set('inventory.owner.enabled', true);

    $owner = User::factory()->create();
    $order = createOwnedReleaseOrder($owner, 'ORD-CTX-R1');

    simulateReleaseQueueWorkerWithoutAmbientOwner();

    app(ReleaseInventoryFromOrder::class)->handle(new InventoryReleaseRequired($order));

    $operation = findReleaseOperation($order);

    expect($operation)->not->toBeNull()
        ->and($operation->status)->toBe(InventoryOperation::STATUS_COMPLETED)
        ->and((string) $operation->owner_id)->toBe((string) $owner->getKey());
});

test('repeat release finds the owned operation without ambient context', function (): void {
    config()->set('inventory.owner.enabled', true);

    $owner = User::factory()->create();
    $order = createOwnedReleaseOrder($owner, 'ORD-CTX-R2');

    simulateReleaseQueueWorkerWithoutAmbientOwner();

    $listener = app(ReleaseInventoryFromOrder::class);
    $listener->handle(new InventoryReleaseRequired($order));
    $listener->handle(new InventoryReleaseRequired($order->fresh()));

    expect(InventoryOperation::query()->withoutGlobalScope(OwnerScope::class)->where('order_id', $order->getKey())->count())->toBe(1)
        ->and(findReleaseOperation($order)->status)->toBe(InventoryOperation::STATUS_COMPLETED);
});

test('release skips malformed and half-null owner tuples without an operation', function (): void {
    // Owner columns are not fillable: plant the corrupt shapes directly.
    $emptyType = Order::create([
        'order_number' => 'ORD-CTX-RM1',
        'status' => PendingPayment::class,
        'currency' => 'USD',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
    $emptyType->forceFill(['owner_type' => '', 'owner_id' => (string) Str::uuid()])->saveQuietly();

    $halfNull = Order::create([
        'order_number' => 'ORD-CTX-RM2',
        'status' => PendingPayment::class,
        'currency' => 'USD',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
    $halfNull->forceFill(['owner_type' => null, 'owner_id' => (string) Str::uuid()])->saveQuietly();

    $listener = app(ReleaseInventoryFromOrder::class);
    $listener->handle(new InventoryReleaseRequired($emptyType));
    $listener->handle(new InventoryReleaseRequired($halfNull));

    expect(findReleaseOperation($emptyType))->toBeNull()
        ->and(findReleaseOperation($halfNull))->toBeNull();
});
