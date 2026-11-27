<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Exceptions\InvalidCartConditionException;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Features\Conditions\Actions\ApplyStoredCondition;
use AIArmada\Cart\Features\Conditions\Actions\RemoveStoredConditions;
use AIArmada\Cart\Features\Conditions\Actions\SaveStoredCondition;
use AIArmada\Cart\Features\Conditions\Models\Condition;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Cart\Runtime\CartSnapshotSyncInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'cart.events' => false,
        'cart.owner.enabled' => false,
        'cart.snapshots.synchronization.queue_sync' => false,
    ]);
});

it('registers dynamic cart-scope stored conditions through the durable dynamic path', function (): void {
    $snapshot = CartSnapshot::query()->create([
        'identifier' => 'dynamic-cart',
        'instance' => 'default',
    ]);

    $stored = Condition::factory()
        ->active()
        ->withRules(['factory_keys' => ['always-true'], 'context' => []])
        ->create([
            'name' => 'dynamic-stored-discount',
            'display_name' => 'Dynamic Stored Discount',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
        ]);

    app(ApplyStoredCondition::class)->apply((string) $snapshot->getKey(), (string) $stored->getKey());

    $cart = Cart::getCartInstance('default', 'dynamic-cart')
        ->withRulesFactory(app(RulesFactoryInterface::class));

    expect($cart->getDynamicConditions()->has('Dynamic Stored Discount'))->toBeTrue()
        ->and($cart->getDynamicConditionMetadata())->toHaveKey('Dynamic Stored Discount')
        ->and($cart->getDynamicConditionMetadata()['Dynamic Stored Discount']['rule_factory_key'])->toBe('always-true');
});

it('rejects applying a dynamic stored condition to a single item', function (): void {
    $snapshot = CartSnapshot::query()->create([
        'identifier' => 'dynamic-item-cart',
        'instance' => 'default',
    ]);

    $stored = Condition::factory()
        ->active()
        ->forItems()
        ->withRules(['factory_keys' => ['always-true'], 'context' => []])
        ->create();

    expect(fn () => app(ApplyStoredCondition::class)->applyToItem(
        (string) $snapshot->getKey(),
        'sku-1',
        (string) $stored->getKey(),
    ))->toThrow(Exception::class, 'Dynamic item-level conditions cannot be applied to a single item.');
});

it('rejects serializing dynamic closures into an item condition projection', function (): void {
    $cart = Cart::getCartInstance('default', 'item-condition-guard');
    $cart->add('sku-1', 'Product', 1000, 1);

    $condition = new CartCondition(
        name: 'dynamic-item-condition',
        type: 'discount',
        target: 'items@item_discount/per-item',
        value: '-10%',
        rules: [static fn (): bool => true],
    );

    expect(fn () => $cart->addItemCondition('sku-1', $condition))
        ->toThrow(InvalidCartConditionException::class, 'registerDynamicCondition()');
});

it('unregisters the exact dynamic definition when removing its item projection', function (): void {
    $snapshot = CartSnapshot::query()->create([
        'identifier' => 'dynamic-item-removal',
        'instance' => 'default',
    ]);

    $stored = Condition::factory()
        ->active()
        ->forItems()
        ->withRules(['factory_keys' => ['always-true'], 'context' => []])
        ->create([
            'name' => 'item-dynamic-removal',
            'display_name' => 'Item Dynamic Removal',
        ]);

    $cart = Cart::getCartInstance('default', 'dynamic-item-removal');
    $cart->add('sku-1', 'Product', 1000, 1);

    app(ApplyStoredCondition::class)->apply((string) $snapshot->getKey(), (string) $stored->getKey());

    $cart = Cart::getCartInstance('default', 'dynamic-item-removal')
        ->withRulesFactory(app(RulesFactoryInterface::class));
    $cart->evaluateDynamicConditions();

    expect($cart->get('sku-1')?->getConditions()->has('Item Dynamic Removal'))->toBeTrue();

    app(CartSnapshotSyncInterface::class)->sync($cart, force: true);
    app(RemoveStoredConditions::class)->handle($stored);

    $fresh = Cart::getCartInstance('default', 'dynamic-item-removal')
        ->withRulesFactory(app(RulesFactoryInterface::class));
    $fresh->evaluateDynamicConditions();

    expect($fresh->getDynamicConditions()->has('Item Dynamic Removal'))->toBeFalse()
        ->and($fresh->get('sku-1')?->getConditions()->has('Item Dynamic Removal'))->toBeFalse();
});

it('rejects reserved attributes when authoring stored conditions', function (string $attribute): void {
    expect(fn () => app(SaveStoredCondition::class)->create([
        'name' => 'reserved-stored-condition',
        'display_name' => 'Reserved Stored Condition',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => '-10%',
        'attributes' => [$attribute => 'forged'],
    ]))->toThrow(InvalidArgumentException::class, 'is reserved');
})->with(['condition_id', 'condition_name', 'is_global', 'definition_hash', '__provider']);
