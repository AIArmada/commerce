<?php

declare(strict_types=1);

use AIArmada\Cart\Models\Condition;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\FilamentCartServiceProvider;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use AIArmada\FilamentCart\Resources\CartConditionResource;
use AIArmada\FilamentCart\Resources\CartItemResource;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Resources\ConditionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('synchronizes owner configuration into the core cart package on boot', function (): void {
    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.owner.include_global', true);
    config()->set('cart.owner.enabled', false);
    config()->set('cart.owner.include_global', false);

    $provider = new FilamentCartServiceProvider(app());
    $provider->packageBooted();

    expect(config('filament-cart.owner.enabled'))->toBeTrue();
    expect(config('filament-cart.owner.include_global'))->toBeTrue();
    expect(config('cart.owner.enabled'))->toBeTrue();
    expect(config('cart.owner.include_global'))->toBeTrue();
});

it('scopes filament-cart snapshots and child resources by resolved owner', function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('filament-cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
    config()->set('filament-cart.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $cartA = CartSnapshot::query()->create([
        'identifier' => 'same-id',
        'instance' => 'default',
        'currency' => 'USD',
        'items_count' => 1,
        'quantity' => 1,
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $cartB = OwnerContext::withOwner($ownerB, fn () => CartSnapshot::query()->create([
        'identifier' => 'same-id',
        'instance' => 'default',
        'currency' => 'USD',
        'items_count' => 2,
        'quantity' => 2,
        'subtotal' => 2000,
        'total' => 2000,
    ]));

    $cartAItem = CartItem::query()->create([
        'cart_id' => $cartA->id,
        'item_id' => 'sku-a',
        'name' => 'Item A',
        'price' => 1000,
        'quantity' => 1,
    ]);

    CartItem::query()->create([
        'cart_id' => $cartB->id,
        'item_id' => 'sku-b',
        'name' => 'Item B',
        'price' => 1000,
        'quantity' => 2,
    ]);

    CartCondition::query()->create([
        'cart_id' => $cartA->id,
        'name' => 'discount-a',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
        'value' => '-10%',
        'is_discount' => true,
        'is_percentage' => true,
        'order' => 1,
        'cart_item_id' => $cartAItem->id,
        'item_id' => $cartAItem->item_id,
    ]);

    CartCondition::query()->create([
        'cart_id' => $cartB->id,
        'name' => 'discount-b',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
        'value' => '-5%',
        'is_discount' => true,
        'is_percentage' => true,
        'order' => 1,
    ]);

    expect(CartResource::getEloquentQuery()->count())->toBe(1);
    expect(CartResource::getEloquentQuery()->first()?->id)->toBe($cartA->id);

    expect(CartItemResource::getEloquentQuery()->count())->toBe(1);
    expect(CartItemResource::getEloquentQuery()->first()?->cart_id)->toBe($cartA->id);
    expect(CartItemResource::getEloquentQuery()->withoutConditions()->count())->toBe(1);
    expect(CartItemResource::getEloquentQuery()->withoutConditions()->first()?->cart_id)->toBe($cartA->id);

    expect(CartConditionResource::getEloquentQuery()->count())->toBe(1);
    expect(CartConditionResource::getEloquentQuery()->first()?->cart_id)->toBe($cartA->id);
});

it('treats shared global conditions as read-only in tenant contexts', function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('filament-cart.owner.enabled', true);
    config()->set('cart.owner.include_global', true);
    config()->set('filament-cart.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Tenant Owner',
        'email' => 'tenant-owner@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($owner));

    $globalCondition = OwnerContext::withOwner(null, fn () => Condition::factory()->create([
        'name' => 'shared-global-condition',
        'is_active' => true,
        'is_global' => true,
        'owner_type' => null,
        'owner_id' => null,
    ]));

    expect(ConditionResource::canEdit($globalCondition))->toBeFalse();
    expect(ConditionResource::canDelete($globalCondition))->toBeFalse();
});

it('includes global snapshot rows across resources when include_global is enabled', function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('filament-cart.owner.enabled', true);
    config()->set('cart.owner.include_global', true);
    config()->set('filament-cart.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Owner Scoped',
        'email' => 'owner-scoped-include-global@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($owner));

    $ownerCart = OwnerContext::withOwner($owner, fn () => CartSnapshot::query()->create([
        'identifier' => 'owner-cart',
        'instance' => 'default',
        'currency' => 'USD',
        'items_count' => 1,
        'quantity' => 1,
        'subtotal' => 1000,
        'total' => 1000,
    ]));

    $globalCart = OwnerContext::withOwner(null, fn () => CartSnapshot::query()->create([
        'identifier' => 'global-cart',
        'instance' => 'default',
        'currency' => 'USD',
        'items_count' => 1,
        'quantity' => 1,
        'subtotal' => 1500,
        'total' => 1500,
    ]));

    CartItem::query()->create([
        'cart_id' => $ownerCart->id,
        'item_id' => 'sku-owner',
        'name' => 'Owner Item',
        'price' => 1000,
        'quantity' => 1,
    ]);

    CartItem::query()->create([
        'cart_id' => $globalCart->id,
        'item_id' => 'sku-global',
        'name' => 'Global Item',
        'price' => 1500,
        'quantity' => 1,
    ]);

    CartCondition::query()->create([
        'cart_id' => $ownerCart->id,
        'name' => 'owner-discount',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
        'value' => '-5%',
        'is_discount' => true,
        'is_percentage' => true,
        'order' => 1,
    ]);

    CartCondition::query()->create([
        'cart_id' => $globalCart->id,
        'name' => 'global-discount',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
        'value' => '-3%',
        'is_discount' => true,
        'is_percentage' => true,
        'order' => 1,
    ]);

    expect(CartResource::getEloquentQuery()->count())->toBe(2);
    expect(CartItemResource::getEloquentQuery()->count())->toBe(2);
    expect(CartConditionResource::getEloquentQuery()->count())->toBe(2);
});
