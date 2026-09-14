<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cart.events' => false]);

    $this->storage = new DatabaseStorage(app('db')->connection(), 'carts');
});

it('keeps manager swaps inside the current owner scope', function (): void {
    $ownerA = User::query()->create(['name' => 'Swap A', 'email' => 'swap-a@example.com', 'password' => 'secret']);

    $this->storage->putItems('global-guest', 'default', [
        'product-1' => ['id' => 'product-1', 'name' => 'Global', 'price' => 100, 'quantity' => 1, 'attributes' => []],
    ]);

    $manager = new CartManager($this->storage);

    // An owner-scoped manager must not see (or move) the global source cart.
    expect($manager->forOwner($ownerA)->swap('global-guest', 'user-a', 'default'))->toBeFalse();
    expect($this->storage->getItems('global-guest', 'default'))->toHaveCount(1);
    expect($this->storage->withOwner($ownerA)->getItems('user-a', 'default'))->toBeEmpty();

    // A swap fully inside the scope still works and leaves global rows alone.
    $this->storage->withOwner($ownerA)->putItems('scoped-guest', 'default', [
        'product-2' => ['id' => 'product-2', 'name' => 'Scoped', 'price' => 200, 'quantity' => 1, 'attributes' => []],
    ]);

    expect($manager->forOwner($ownerA)->swap('scoped-guest', 'user-a', 'default'))->toBeTrue();
    expect($this->storage->withOwner($ownerA)->getItems('user-a', 'default'))->toHaveCount(1);
    expect($this->storage->getItems('user-a', 'default'))->toBeEmpty();
});

it('flushes only global rows in a global context', function (): void {
    $owner = User::query()->create(['name' => 'Flush Owner', 'email' => 'flush-owner@example.com', 'password' => 'secret']);

    $this->storage->putItems('global-flush', 'default', [
        'product-1' => ['id' => 'product-1', 'name' => 'Global', 'price' => 100, 'quantity' => 1, 'attributes' => []],
    ]);
    $this->storage->withOwner($owner)->putItems('owner-flush', 'default', [
        'product-2' => ['id' => 'product-2', 'name' => 'Owned', 'price' => 200, 'quantity' => 1, 'attributes' => []],
    ]);

    $this->storage->flush();

    expect($this->storage->getItems('global-flush', 'default'))->toBeEmpty();
    expect($this->storage->withOwner($owner)->getItems('owner-flush', 'default'))->toHaveCount(1);
});

it('rejects deeply nested payloads before encoding', function (): void {
    $deep = ['leaf' => 1];

    for ($i = 0; $i < 40; $i++) {
        $deep = ['nest' => $deep];
    }

    expect(fn (): mixed => $this->storage->putItems('deep-cart', 'default', ['item' => $deep]))
        ->toThrow(InvalidArgumentException::class, 'nesting depth');
});

it('restores associated models within the cart owner scope', function (): void {
    config(['cart.owner.enabled' => true]);

    $ownerA = User::query()->create(['name' => 'Assoc A', 'email' => 'assoc-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Assoc B', 'email' => 'assoc-b@example.com', 'password' => 'secret']);

    $snapshotA = OwnerContext::withOwner($ownerA, fn (): CartSnapshot => CartSnapshot::query()->create([
        'identifier' => 'assoc-own',
        'instance' => 'default',
    ]));
    $snapshotB = OwnerContext::withOwner($ownerB, fn (): CartSnapshot => CartSnapshot::query()->create([
        'identifier' => 'assoc-foreign',
        'instance' => 'default',
    ]));

    $scopedStorage = $this->storage->withOwner($ownerA);
    $scopedStorage->putItems('assoc-cart', 'default', [
        'own' => [
            'id' => 'own', 'name' => 'Own', 'price' => 100, 'quantity' => 1,
            'attributes' => [], 'conditions' => [],
            'associated_model' => ['class' => CartSnapshot::class, 'id' => $snapshotA->getKey()],
        ],
        'foreign' => [
            'id' => 'foreign', 'name' => 'Foreign', 'price' => 100, 'quantity' => 1,
            'attributes' => [], 'conditions' => [],
            'associated_model' => ['class' => CartSnapshot::class, 'id' => $snapshotB->getKey()],
        ],
    ]);

    $items = (new Cart($scopedStorage, 'assoc-cart'))->getItems();

    expect($items->get('own')?->getAssociatedModel())->toBeInstanceOf(CartSnapshot::class);
    expect($items->get('foreign')?->getAssociatedModel())->toBeNull();
});
