<?php

declare(strict_types=1);

use AIArmada\Cart\Models\Condition;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\OwnerActionGuard;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.owner.include_global', false);
});

it('rejects submitted condition ids from another owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-action-guard-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-action-guard-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $conditionB = OwnerContext::withOwner($ownerB, fn (): Condition => Condition::factory()->create([
        'name' => 'owner-b-only-condition',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => '-10%',
        'is_active' => true,
    ]));

    expect(fn () => OwnerActionGuard::findStoredCondition($conditionB->id, forItems: false))
        ->toThrow(AuthorizationException::class, 'Condition is not accessible in the current owner scope.');
});

it('rejects cart action records from another owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'cart-action-guard-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'cart-action-guard-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $cartB = OwnerContext::withOwner($ownerB, fn (): Cart => Cart::query()->create([
        'identifier' => 'owner-b-cart',
        'instance' => 'default',
        'currency' => 'USD',
        'items_count' => 1,
        'quantity' => 1,
        'subtotal' => 1000,
        'total' => 1000,
    ]));

    expect(fn () => OwnerActionGuard::authorizeCart($cartB))
        ->toThrow(AuthorizationException::class, 'Cart is not accessible in the current owner scope.');
});
