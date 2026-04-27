<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\Condition;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart as CartModel;
use AIArmada\FilamentCart\Models\CartCondition as CartConditionModel;
use AIArmada\FilamentCart\Services\CartConditionBatchRemoval;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentCart\Services\CartSyncManager;

describe('CartConditionBatchRemoval service', function (): void {
    it('removes condition from affected carts using stored condition identity', function (): void {
        $storedCondition = Condition::factory()->create([
            'name' => 'bad-condition-rule',
            'display_name' => 'Bad Condition',
            'type' => 'coupon',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10',
            'is_active' => true,
            'is_global' => true,
        ]);

        $snapshot = CartModel::create([
            'instance' => 'default',
            'identifier' => 'session-123',
            'subtotal' => 1000,
        ]);

        CartConditionModel::create([
            'cart_id' => $snapshot->id,
            'name' => 'Bad Condition',
            'type' => 'coupon',
            'target' => 'cart.subtotal',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10',
            'order' => 0,
            'is_global' => true,
            'attributes' => ['condition_id' => $storedCondition->id],
        ]);

        // Create Real Cart with Mocked Storage
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getConditions')->andReturn([
            'Bad Condition' => [
                'name' => 'Bad Condition',
                'type' => 'coupon',
                'target' => 'cart.subtotal',
                'target_definition' => [
                    'scope' => 'cart',
                    'phase' => 'cart_subtotal',
                    'application' => 'aggregate',
                ],
                'value' => '-10',
            ],
        ]);
        $storage->shouldReceive('getItems')->andReturn([]);
        $storage->shouldReceive('getId')->andReturn('cart-id');
        $storage->shouldReceive('getVersion')->andReturn(1);
        $storage->shouldReceive('getCreatedAt')->andReturn(now()->toIso8601String());
        $storage->shouldReceive('getUpdatedAt')->andReturn(now()->toIso8601String());

        $storage->shouldReceive('putConditions')->with('session-123', 'default', Mockery::on(function ($args) {
            return ! isset($args['Bad Condition']);
        }))->once();

        $realCart = new Cart($storage, 'session-123');

        $cartManager = Mockery::mock(CartInstanceManager::class);
        $cartManager->shouldReceive('resolveForSnapshot')
            ->andReturn($realCart);

        $syncManager = Mockery::mock(CartSyncManager::class);
        $syncManager->shouldReceive('sync')->with($realCart)->once();

        $service = new CartConditionBatchRemoval($cartManager, $syncManager);

        $result = $service->removeConditionFromAllCarts($storedCondition);

        expect($result['success'])->toBeTrue();
        expect($result['carts_processed'])->toBe(1);
        expect($result['carts_updated'])->toBe(1);
    });

    it('handles cart loading failures', function (): void {
        $storedCondition = Condition::factory()->create([
            'name' => 'bad-condition-rule',
            'display_name' => 'Bad Condition',
            'type' => 'coupon',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10',
            'is_active' => true,
            'is_global' => true,
        ]);

        $snapshot = CartModel::create([
            'instance' => 'default',
            'identifier' => 'session-bad',
            'subtotal' => 1000,
        ]);

        CartConditionModel::create([
            'cart_id' => $snapshot->id,
            'name' => 'Bad Condition',
            'type' => 'coupon',
            'target' => 'cart.subtotal',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10',
            'order' => 0,
            'is_global' => true,
            'attributes' => ['condition_id' => $storedCondition->id],
        ]);

        $cartManager = Mockery::mock(CartInstanceManager::class);
        $cartManager->shouldReceive('resolveForSnapshot')->andThrow(new Exception('Fail'));

        $syncManager = Mockery::mock(CartSyncManager::class);

        $service = new CartConditionBatchRemoval($cartManager, $syncManager);

        $result = $service->removeConditionFromAllCarts($storedCondition);

        expect($result['success'])->toBeTrue();
        expect($result['carts_processed'])->toBe(1);
        expect($result['carts_updated'])->toBe(0);
        expect($result['errors'])->not->toBeEmpty();
    });

    it('records per-cart processing errors without triggering an undefined variable failure', function (): void {
        $storedCondition = Condition::factory()->create([
            'name' => 'bad-condition-rule',
            'display_name' => 'Bad Condition',
            'type' => 'coupon',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10',
            'is_active' => true,
            'is_global' => true,
        ]);

        $snapshot = CartModel::create([
            'instance' => 'default',
            'identifier' => 'session-sync-fail',
            'subtotal' => 1000,
        ]);

        CartConditionModel::create([
            'cart_id' => $snapshot->id,
            'name' => 'Bad Condition',
            'type' => 'coupon',
            'target' => 'cart.subtotal',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10',
            'order' => 0,
            'is_global' => true,
            'attributes' => ['condition_id' => $storedCondition->id],
        ]);

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getConditions')->andReturn([
            'Bad Condition' => [
                'name' => 'Bad Condition',
                'type' => 'coupon',
                'target' => 'cart.subtotal',
                'target_definition' => [
                    'scope' => 'cart',
                    'phase' => 'cart_subtotal',
                    'application' => 'aggregate',
                ],
                'value' => '-10',
            ],
        ]);
        $storage->shouldReceive('getItems')->andReturn([]);
        $storage->shouldReceive('getId')->andReturn('cart-id');
        $storage->shouldReceive('getVersion')->andReturn(1);
        $storage->shouldReceive('getCreatedAt')->andReturn(now()->toIso8601String());
        $storage->shouldReceive('getUpdatedAt')->andReturn(now()->toIso8601String());
        $storage->shouldReceive('putConditions')->once();

        $realCart = new Cart($storage, 'session-sync-fail');

        $cartManager = Mockery::mock(CartInstanceManager::class);
        $cartManager->shouldReceive('resolveForSnapshot')
            ->andReturn($realCart);

        $syncManager = Mockery::mock(CartSyncManager::class);
        $syncManager->shouldReceive('sync')
            ->with($realCart)
            ->once()
            ->andThrow(new Exception('sync failed'));

        $service = new CartConditionBatchRemoval($cartManager, $syncManager);

        $result = $service->removeConditionFromAllCarts($storedCondition);

        expect($result['success'])->toBeTrue();
        expect($result['carts_processed'])->toBe(1);
        expect($result['carts_updated'])->toBe(0);
        expect($result['errors'])->toHaveCount(1);
        expect($result['errors'][0])->toContain('sync failed');
    });

    it('returns 0 processed if no carts match', function (): void {
        $storedCondition = Condition::factory()->create([
            'name' => 'bad-condition-rule',
            'display_name' => 'Bad Condition',
            'type' => 'coupon',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10',
            'is_active' => true,
            'is_global' => true,
        ]);

        $snapshot = CartModel::create([
            'instance' => 'default',
            'identifier' => 'session-ok',
            'subtotal' => 1000,
        ]);

        CartConditionModel::create([
            'cart_id' => $snapshot->id,
            'name' => 'Good Condition',
            'type' => 'coupon',
            'target' => 'cart.subtotal',
            'target_definition' => [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            'value' => '-10',
            'order' => 0,
            'is_global' => true,
        ]);

        $cartManager = Mockery::mock(CartInstanceManager::class);
        $syncManager = Mockery::mock(CartSyncManager::class);

        $service = new CartConditionBatchRemoval($cartManager, $syncManager);

        $result = $service->removeConditionFromAllCarts($storedCondition);

        expect($result['carts_processed'])->toBe(0);
    });

    it('removes shared global conditions across owner snapshots when owner mode is enabled', function (): void {
        config()->set('cart.owner.enabled', true);
        config()->set('filament-cart.owner.enabled', true);

        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'batch-owner-a@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'batch-owner-b@example.com',
            'password' => 'secret',
        ]);

        $storedCondition = OwnerContext::withOwner(null, fn () => Condition::factory()->create([
            'name' => 'global-bad-condition',
            'display_name' => 'Global Bad Condition',
            'type' => 'coupon',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10',
            'is_active' => true,
            'is_global' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]));

        $snapshotA = OwnerContext::withOwner($ownerA, fn () => CartModel::create([
            'instance' => 'default',
            'identifier' => 'session-a',
            'subtotal' => 1000,
        ]));

        $snapshotB = OwnerContext::withOwner($ownerB, fn () => CartModel::create([
            'instance' => 'default',
            'identifier' => 'session-b',
            'subtotal' => 1000,
        ]));

        foreach ([$snapshotA, $snapshotB] as $snapshot) {
            CartConditionModel::create([
                'cart_id' => $snapshot->id,
                'name' => 'Global Bad Condition',
                'type' => 'coupon',
                'target' => 'cart.subtotal',
                'target_definition' => [
                    'scope' => 'cart',
                    'phase' => 'cart_subtotal',
                    'application' => 'aggregate',
                ],
                'value' => '-10',
                'order' => 0,
                'is_global' => true,
                'attributes' => ['condition_id' => $storedCondition->id],
            ]);
        }

        $storageA = Mockery::mock(StorageInterface::class);
        $storageA->shouldReceive('getConditions')->andReturn([
            'Global Bad Condition' => [
                'name' => 'Global Bad Condition',
                'type' => 'coupon',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => [
                    'scope' => 'cart',
                    'phase' => 'cart_subtotal',
                    'application' => 'aggregate',
                ],
                'value' => '-10',
                'order' => 0,
            ],
        ]);
        $storageA->shouldReceive('getItems')->andReturn([]);
        $storageA->shouldReceive('getId')->andReturn('cart-a');
        $storageA->shouldReceive('getVersion')->andReturn(1);
        $storageA->shouldReceive('getCreatedAt')->andReturn(now()->toIso8601String());
        $storageA->shouldReceive('getUpdatedAt')->andReturn(now()->toIso8601String());
        $storageA->shouldReceive('putConditions')->once();

        $storageB = Mockery::mock(StorageInterface::class);
        $storageB->shouldReceive('getConditions')->andReturn([
            'Global Bad Condition' => [
                'name' => 'Global Bad Condition',
                'type' => 'coupon',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => [
                    'scope' => 'cart',
                    'phase' => 'cart_subtotal',
                    'application' => 'aggregate',
                ],
                'value' => '-10',
                'order' => 0,
            ],
        ]);
        $storageB->shouldReceive('getItems')->andReturn([]);
        $storageB->shouldReceive('getId')->andReturn('cart-b');
        $storageB->shouldReceive('getVersion')->andReturn(1);
        $storageB->shouldReceive('getCreatedAt')->andReturn(now()->toIso8601String());
        $storageB->shouldReceive('getUpdatedAt')->andReturn(now()->toIso8601String());
        $storageB->shouldReceive('putConditions')->once();

        $cartA = new Cart($storageA, 'session-a');
        $cartB = new Cart($storageB, 'session-b');

        $cartManager = Mockery::mock(CartInstanceManager::class);
        $cartManager->shouldReceive('resolveForSnapshot')
            ->with(Mockery::on(fn ($snapshot) => $snapshot instanceof CartModel && $snapshot->id === $snapshotA->id))
            ->andReturn($cartA);
        $cartManager->shouldReceive('resolveForSnapshot')
            ->with(Mockery::on(fn ($snapshot) => $snapshot instanceof CartModel && $snapshot->id === $snapshotB->id))
            ->andReturn($cartB);

        $syncManager = Mockery::mock(CartSyncManager::class);
        $syncManager->shouldReceive('sync')->with($cartA)->once();
        $syncManager->shouldReceive('sync')->with($cartB)->once();

        $service = new CartConditionBatchRemoval($cartManager, $syncManager);

        $result = OwnerContext::withOwner($ownerA, fn () => $service->removeConditionFromAllCarts($storedCondition));

        expect($result['success'])->toBeTrue();
        expect($result['carts_processed'])->toBe(2);
        expect($result['carts_updated'])->toBe(2);
    });
});
