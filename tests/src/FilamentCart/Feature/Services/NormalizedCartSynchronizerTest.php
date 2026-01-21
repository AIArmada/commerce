<?php

declare(strict_types=1);

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use AIArmada\FilamentCart\Services\NormalizedCartSynchronizer;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
    $this->synchronizer = new NormalizedCartSynchronizer;
    $this->storage = Mockery::mock(StorageInterface::class);
    // Common stubs
    $this->storage->shouldReceive('getVersion')->andReturn(1);
    $this->storage->shouldReceive('getId')->andReturn(null);
    $this->storage->shouldReceive('getCreatedAt')->andReturn(null);
    $this->storage->shouldReceive('getUpdatedAt')->andReturn(null);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('NormalizedCartSynchronizer', function (): void {
    it('syncs empty cart', function (): void {
        $this->storage->shouldReceive('getItems')->andReturn([]);
        $this->storage->shouldReceive('getConditions')->andReturn([]);
        $this->storage->shouldReceive('getMetadata')->andReturnUsing(fn () => []);
        $this->storage->shouldReceive('getAllMetadata')->andReturn([]);

        $cart = new BaseCart($this->storage, 'user-123', null, 'default');

        $this->synchronizer->syncFromCart($cart);

        $cartModel = Cart::instance('default')->byIdentifier('user-123')->first();
        expect($cartModel)->not->toBeNull();
        expect($cartModel->items_count)->toBe(0);
        expect($cartModel->items)->toBeNull();
    });

    it('syncs cart with items and conditions', function (): void {
        $this->storage->shouldReceive('getItems')->andReturn([
            'item-1' => [
                'id' => 'item-1',
                'name' => 'Product A',
                'quantity' => 2,
                'price' => 1000,
                'associated_class' => 'App\\Models\\Product', // CartItem might use associated_class or model
                'associatedModel' => 'App\\Models\\Product', // Adjusted for CartItem hydration
                'attributes' => ['color' => 'red'],
                'conditions' => [],
            ],
        ]);
        $this->storage->shouldReceive('getConditions')->andReturn([
            'Promo Code' => [
                'name' => 'Promo Code',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'target_definition' => [
                    'scope' => 'cart',
                    'phase' => 'cart_subtotal',
                    'application' => 'aggregate',
                ],
                'value' => '-10%',
                'order' => 1,
                'attributes' => ['is_global' => true],
            ],
        ]);
        $this->storage->shouldReceive('getMetadata')->andReturnUsing(fn () => []);
        $this->storage->shouldReceive('getAllMetadata')->andReturn([]);

        $cart = new BaseCart($this->storage, 'user-123', null, 'default');

        $this->synchronizer->syncFromCart($cart);

        $cartModel = Cart::instance('default')->byIdentifier('user-123')->first();
        expect($cartModel->items_count)->toBe(1);
        expect($cartModel->quantity)->toBe(2);
        // Recalculating totals relies on Cart logic.
        // 2 * 1000 = 2000 subtotal.
        // 10% discount -> 200 discount.
        // Total 1800.
        // Savings 200.
        expect($cartModel->subtotal)->toBe(2000);
        expect($cartModel->total)->toBe(1800);
        expect($cartModel->savings)->toBe(200);

        // Check relations
        $cartItem = CartItem::where('cart_id', $cartModel->id)->first();
        expect($cartItem->name)->toBe('Product A');
        expect($cartItem->item_id)->toBe('item-1');

        $cartCondition = CartCondition::where('cart_id', $cartModel->id)->cartLevel()->first();
        expect($cartCondition->name)->toBe('Promo Code');
        expect($cartCondition->is_global)->toBeTrue();
    });

    it('removes deleted items and conditions', function (): void {
        // Prepare initial state in DB
        $cart = Cart::create(['instance' => 'default', 'identifier' => 'user-123']);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => 'junk',
            'name' => 'Junk',
            'price' => 100,
            'quantity' => 1,
        ]);
        CartCondition::create([
            'cart_id' => $cart->id,
            'name' => 'Junk Cond',
            'type' => 'tax',
            'value' => '10',
            'target' => 'subtotal',
            'target_definition' => [],
            'order' => 1,
        ]);

        // Sync empty cart
        $this->storage->shouldReceive('getItems')->andReturn([]);
        $this->storage->shouldReceive('getConditions')->andReturn([]);
        $this->storage->shouldReceive('getMetadata')->andReturnUsing(fn () => []);
        $this->storage->shouldReceive('getAllMetadata')->andReturn([]);

        $baseCart = new BaseCart($this->storage, 'user-123', null, 'default');

        $this->synchronizer->syncFromCart($baseCart);

        expect(CartItem::where('cart_id', $cart->id)->count())->toBe(0);
        expect(CartCondition::where('cart_id', $cart->id)->count())->toBe(0);
    });

    it('deletes normalized cart', function (): void {
        $cart = Cart::create(['instance' => 'default', 'identifier' => 'user-123']);
        CartItem::create(['cart_id' => $cart->id, 'item_id' => '1', 'name' => 'A', 'price' => 1, 'quantity' => 1]);

        $this->synchronizer->deleteNormalizedCart('user-123', 'default');

        expect(Cart::find($cart->id))->toBeNull();
        expect(CartItem::where('cart_id', $cart->id)->count())->toBe(0);
    });

    it('does not overwrite another owners cart snapshot when owner mode is enabled', function (): void {
        config()->set('cart.owner.enabled', true);
        config()->set('filament-cart.owner.enabled', true);

        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-sync@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'owner-b-sync@example.com',
            'password' => 'secret',
        ]);

        $ownerBCart = OwnerContext::withOwner($ownerB, fn () => Cart::query()->create([
            'instance' => 'default',
            'identifier' => 'user-123',
            'items_count' => 99,
        ]));

        $this->storage->shouldReceive('getItems')->andReturn([]);
        $this->storage->shouldReceive('getConditions')->andReturn([]);
        $this->storage->shouldReceive('getMetadata')->andReturnUsing(fn () => []);
        $this->storage->shouldReceive('getAllMetadata')->andReturn([]);

        $cart = new BaseCart($this->storage, 'user-123', null, 'default');

        OwnerContext::withOwner($ownerA, fn () => $this->synchronizer->syncFromCart($cart));

        $ownerACart = OwnerContext::withOwner($ownerA, fn () => Cart::query()
            ->forOwner()
            ->where('instance', 'default')
            ->where('identifier', 'user-123')
            ->first());

        expect($ownerACart)->not->toBeNull();
        expect($ownerACart?->items_count)->toBe(0);

        expect($ownerBCart->refresh()->items_count)->toBe(99);
    });
});
