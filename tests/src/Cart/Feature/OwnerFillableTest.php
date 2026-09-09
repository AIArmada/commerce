<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartModel;
use AIArmada\Cart\Models\Condition;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps owner columns out of mass assignment and uses the current owner', function (): void {
    config()->set('cart.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-fillable-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-fillable-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $cart = CartModel::create([
        'identifier' => 'mass-assignment-cart',
        'instance' => 'default',
        'items' => [],
        'conditions' => [],
        'metadata' => [],
        'version' => 1,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    $condition = Condition::create([
        'name' => 'mass-assignment-condition',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
        'value' => '-10%',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    expect($cart->getFillable())
        ->not->toContain('owner_type')
        ->not->toContain('owner_id');
    expect($condition->getFillable())
        ->not->toContain('owner_type')
        ->not->toContain('owner_id');
    expect($cart->owner_type)->toBe($ownerA->getMorphClass())
        ->and((string) $cart->owner_id)->toBe((string) $ownerA->getKey())
        ->and($condition->owner_type)->toBe($ownerA->getMorphClass())
        ->and((string) $condition->owner_id)->toBe((string) $ownerA->getKey());
});
