<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Features\Conditions\Actions\ApplyStoredCondition;
use AIArmada\Cart\Features\Conditions\Actions\RemoveStoredConditions;
use AIArmada\Cart\Features\Conditions\Actions\DeleteStoredCondition;
use AIArmada\Cart\Features\Conditions\Actions\SaveStoredCondition;
use AIArmada\Cart\Features\Conditions\Models\Condition;
use AIArmada\Cart\Features\Snapshots\Api\SnapshotConditionLookup;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Cart\Services\BuiltInRulesFactory;
use AIArmada\Cart\Runtime\CartInstanceManager;
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
        app(SnapshotConditionLookup::class),
    );

    expect(fn () => $action->apply((string) $cartB->getKey(), 'condition-id'))
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
        app(SnapshotConditionLookup::class),
    );

    expect(fn () => $action->apply((string) $cartA->getKey(), $conditionB->id))
        ->toThrow(AuthorizationException::class, 'Condition is not accessible in the current owner scope.');
});

it('prevents cross-owner stored condition edits and deletes', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $condition = OwnerContext::withOwner($ownerB, fn (): Condition => Condition::factory()->create());

    OwnerContext::withOwner($ownerA, function () use ($condition): void {
        expect(fn () => app(SaveStoredCondition::class)->update($condition, ['name' => 'tampered']))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(DeleteStoredCondition::class)->execute($condition))
            ->toThrow(AuthorizationException::class);
    });

    OwnerContext::withOwner($ownerB, function () use ($condition): void {
        expect($condition->fresh()->name)->not->toBe('tampered');
    });
});

it('clears the requested condition type from both cart and item levels', function (): void {
    config(['cart.owner.enabled' => false, 'cart.events' => false]);

    $snapshot = CartSnapshot::query()->create([
        'identifier' => 'clear-condition-types',
        'instance' => 'default',
    ]);
    $cart = Cart::getCartInstance('default', 'clear-condition-types');
    $cart->add('sku-1', 'Product', 1000, 1);

    $cart->addCondition(new CartCondition(
        name: 'cart-discount', type: 'discount', target: 'cart@cart_subtotal/aggregate', value: '-10%',
    ));
    $cart->addItemCondition('sku-1', new CartCondition(
        name: 'item-discount', type: 'discount', target: 'items@item_discount/per-item', value: '-5%',
    ));
    $cart->addItemCondition('sku-1', new CartCondition(
        name: 'item-fee', type: 'fee', target: 'items@item_discount/per-item', value: '+1',
    ));

    app(RemoveStoredConditions::class)->clearByType((string) $snapshot->getKey(), 'discount');

    expect($cart->getStoredConditions()->getCondition('cart-discount'))->toBeNull()
        ->and($cart->get('sku-1')->getConditions()->getCondition('item-discount'))->toBeNull()
        ->and($cart->get('sku-1')->getConditions()->getCondition('item-fee'))->not->toBeNull();
});
