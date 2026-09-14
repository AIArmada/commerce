<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\Fixtures\TestOwner;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Policies\OrderPolicy;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Str;

afterEach(function (): void {
    Mockery::close();
});

function makePermittedUser(array $permissions): User
{
    $user = Mockery::mock(User::class);

    foreach ($permissions as $permission) {
        $user->shouldReceive('can')->with($permission)->andReturn(true);
    }

    return $user;
}

function makeOwnedOrder(TestOwner $owner, string $status = Created::class): Order
{
    return OwnerContext::withOwner($owner, fn (): Order => Order::create([
        'order_number' => 'ORD-POL-' . uniqid(),
        'status' => $status,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]));
}

beforeEach(function (): void {
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', false);
});

it('denies cross-owner record access even with permissions', function (): void {
    $ownerA = new TestOwner(['name' => 'A']);
    $ownerA->id = (string) Str::orderedUuid();
    $ownerB = new TestOwner(['name' => 'B']);
    $ownerB->id = (string) Str::orderedUuid();

    $order = makeOwnedOrder($ownerA);

    $user = makePermittedUser(['view_order', 'update_order', 'delete_order', 'cancel_order']);
    $policy = new OrderPolicy;

    OwnerContext::withOwner($ownerB, function () use ($policy, $user, $order): void {
        expect($policy->view($user, $order))->toBeFalse()
            ->and($policy->update($user, $order))->toBeFalse()
            ->and($policy->delete($user, $order))->toBeFalse()
            ->and($policy->cancel($user, $order))->toBeFalse()
            ->and($policy->addNote($user, $order))->toBeFalse();
    });
});

it('allows same-owner record access with permissions', function (): void {
    $ownerA = new TestOwner(['name' => 'A']);
    $ownerA->id = (string) Str::orderedUuid();

    $order = makeOwnedOrder($ownerA);

    $user = makePermittedUser(['view_order', 'update_order', 'delete_order', 'cancel_order', 'add_order_note', 'create_order']);
    $policy = new OrderPolicy;

    OwnerContext::withOwner($ownerA, function () use ($policy, $user, $order): void {
        expect($policy->view($user, $order))->toBeTrue()
            ->and($policy->update($user, $order))->toBeTrue()
            ->and($policy->delete($user, $order))->toBeTrue()
            ->and($policy->cancel($user, $order))->toBeTrue()
            ->and($policy->addNote($user, $order))->toBeTrue()
            ->and($policy->create($user))->toBeTrue();
    });
});

it('denies cross-owner refunds with permissions', function (): void {
    $ownerA = new TestOwner(['name' => 'A']);
    $ownerA->id = (string) Str::orderedUuid();
    $ownerB = new TestOwner(['name' => 'B']);
    $ownerB->id = (string) Str::orderedUuid();

    $order = makeOwnedOrder($ownerA, Completed::class);

    $user = makePermittedUser(['refund_order']);
    $policy = new OrderPolicy;

    OwnerContext::withOwner($ownerB, function () use ($policy, $user, $order): void {
        expect($policy->refund($user, $order))->toBeFalse();
    });

    OwnerContext::withOwner($ownerA, function () use ($policy, $user, $order): void {
        expect($policy->refund($user, $order))->toBeTrue();
    });
});
