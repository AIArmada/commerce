<?php

declare(strict_types=1);

use AIArmada\Cart\Models\Condition;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Actions\ApplyConditionAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scopes ApplyConditionAction condition options and lookups by resolved owner', function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', true);
    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-apply-condition@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-apply-condition@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $conditionA = Condition::factory()->create([
        'name' => 'owner-a-discount',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => '-10%',
        'is_active' => true,
        'is_global' => false,
    ]);
    $conditionA->assignOwner($ownerA)->save();

    $conditionB = OwnerContext::withOwner($ownerB, fn () => Condition::factory()->create([
        'name' => 'owner-b-discount',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => '-5%',
        'is_active' => true,
        'is_global' => false,
    ]));

    $globalCondition = OwnerContext::withOwner(null, fn () => Condition::factory()->create([
        'name' => 'global-discount',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => '-3%',
        'is_active' => true,
        'is_global' => true,
        'owner_type' => null,
        'owner_id' => null,
    ]));

    $optionsMethod = new ReflectionMethod(ApplyConditionAction::class, 'getConditionOptions');

    /** @var array<string, array<string, string>> $options */
    $options = $optionsMethod->invoke(null, false);

    $hasConditionA = false;
    $hasConditionB = false;
    $hasGlobal = false;

    foreach ($options as $group) {
        $hasConditionA = $hasConditionA || array_key_exists($conditionA->id, $group);
        $hasConditionB = $hasConditionB || array_key_exists($conditionB->id, $group);
        $hasGlobal = $hasGlobal || array_key_exists($globalCondition->id, $group);
    }

    expect($hasConditionA)->toBeTrue();
    expect($hasGlobal)->toBeTrue();
    expect($hasConditionB)->toBeFalse();

    $queryMethod = new ReflectionMethod(ApplyConditionAction::class, 'getScopedConditionQuery');

    /** @var Builder<Condition> $query */
    $query = $queryMethod->invoke(null, false);

    expect($query->findOrFail($conditionA->id)->id)->toBe($conditionA->id);
    expect(fn () => $query->findOrFail($conditionB->id))->toThrow(ModelNotFoundException::class);
});

it('returns only item-level conditions for item actions', function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', true);
    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-item-condition@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($owner));

    $itemCondition = Condition::factory()->forItems()->create([
        'name' => 'owner-item-discount',
        'display_name' => 'Owner Item Discount',
        'is_active' => true,
    ]);
    $itemCondition->assignOwner($owner)->save();

    $cartCondition = Condition::factory()->create([
        'name' => 'owner-cart-discount',
        'display_name' => 'Owner Cart Discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'is_active' => true,
    ]);
    $cartCondition->assignOwner($owner)->save();

    $optionsMethod = new ReflectionMethod(ApplyConditionAction::class, 'getConditionOptions');

    /** @var array<string, array<string, string>> $options */
    $options = $optionsMethod->invoke(null, true);

    $flattened = collect($options)->collapse()->all();

    expect(array_key_exists($itemCondition->id, $flattened))->toBeTrue();
    expect(array_key_exists($cartCondition->id, $flattened))->toBeFalse();

    $queryMethod = new ReflectionMethod(ApplyConditionAction::class, 'getScopedConditionQuery');

    /** @var Builder<Condition> $query */
    $query = $queryMethod->invoke(null, true);

    expect($query->findOrFail($itemCondition->id)->id)->toBe($itemCondition->id);
    expect(fn () => $query->findOrFail($cartCondition->id))->toThrow(ModelNotFoundException::class);
});
