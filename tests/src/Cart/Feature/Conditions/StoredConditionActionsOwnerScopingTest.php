<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\ApplyStoredCondition;
use AIArmada\Cart\Models\Condition;
use AIArmada\Cart\Services\BuiltInRulesFactory;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
});

it('rejects a stored-condition action for a cart owned by another tenant', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'stored-action-cart-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'stored-action-cart-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $cartB = OwnerContext::withOwner($ownerB, fn (): CartSnapshot => CartSnapshot::query()->create([
        'identifier' => 'owner-b-action-cart',
        'instance' => 'default',
    ]));

    $action = new ApplyStoredCondition(
        new CartInstanceManager(new BuiltInRulesFactory),
        new BuiltInRulesFactory,
    );

    expect(fn () => $action->apply($cartB, 'condition-id'))
        ->toThrow(AuthorizationException::class, 'Cart is not accessible in the current owner scope.');
});

it('rejects a stored condition owned by another tenant before resolving the cart', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'stored-action-condition-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'stored-action-condition-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $cartA = OwnerContext::withOwner($ownerA, fn (): CartSnapshot => CartSnapshot::query()->create([
        'identifier' => 'owner-a-action-cart',
        'instance' => 'default',
    ]));

    $conditionB = OwnerContext::withOwner($ownerB, fn (): Condition => Condition::factory()->active()->create([
        'name' => 'owner-b-action-condition',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => '-10%',
    ]));

    $action = new ApplyStoredCondition(
        new CartInstanceManager(new BuiltInRulesFactory),
        new BuiltInRulesFactory,
    );

    expect(fn () => $action->apply($cartA, $conditionB->id))
        ->toThrow(AuthorizationException::class, 'Condition is not accessible in the current owner scope.');
});
