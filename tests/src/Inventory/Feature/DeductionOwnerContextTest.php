<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Inventory\Listeners\DeductInventoryFromOrder;
use AIArmada\Inventory\Models\InventoryOperation;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;

function createOwnedDeductionOrder(User $owner, string $number): Order
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

function simulateQueueWorkerWithoutAmbientOwner(): void
{
    OwnerContext::flushState();
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);
}

function findDeductionOperation(Order $order): ?InventoryOperation
{
    return InventoryOperation::query()
        ->withoutGlobalScope(OwnerScope::class)
        ->where('order_id', $order->getKey())
        ->where('kind', InventoryOperation::KIND_DEDUCTION)
        ->first();
}

test('deduction restores the order owner context when ambient context is missing', function (): void {
    config()->set('inventory.owner.enabled', true);

    $owner = User::factory()->create();
    $order = createOwnedDeductionOrder($owner, 'ORD-CTX-1');

    simulateQueueWorkerWithoutAmbientOwner();

    app(DeductInventoryFromOrder::class)->handle(new InventoryDeductionRequired($order));

    $operation = findDeductionOperation($order);

    expect($operation)->not->toBeNull()
        ->and($operation->status)->toBe(InventoryOperation::STATUS_COMPLETED)
        ->and((string) $operation->owner_id)->toBe((string) $owner->getKey());
});

test('repeat deduction finds the owned operation without ambient context', function (): void {
    config()->set('inventory.owner.enabled', true);

    $owner = User::factory()->create();
    $order = createOwnedDeductionOrder($owner, 'ORD-CTX-2');

    simulateQueueWorkerWithoutAmbientOwner();

    $listener = app(DeductInventoryFromOrder::class);
    $listener->handle(new InventoryDeductionRequired($order));
    $listener->handle(new InventoryDeductionRequired($order->fresh()));

    expect(InventoryOperation::query()->withoutGlobalScope(OwnerScope::class)->where('order_id', $order->getKey())->count())->toBe(1)
        ->and(findDeductionOperation($order)->status)->toBe(InventoryOperation::STATUS_COMPLETED);
});
