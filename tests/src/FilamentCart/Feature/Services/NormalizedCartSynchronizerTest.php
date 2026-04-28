<?php

declare(strict_types=1);

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Events\CartSnapshotSynced;
use AIArmada\FilamentCart\Events\HighValueCartDetected;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use AIArmada\FilamentCart\Services\NormalizedCartSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

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

    it('deletes only the targeted owner snapshot when identifiers collide across owners', function (): void {
        config()->set('cart.owner.enabled', true);
        config()->set('filament-cart.owner.enabled', true);

        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-delete-sync@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'owner-b-delete-sync@example.com',
            'password' => 'secret',
        ]);

        $ownerACart = OwnerContext::withOwner($ownerA, fn () => Cart::query()->create([
            'instance' => 'default',
            'identifier' => 'collision-user',
            'items_count' => 1,
        ]));

        $ownerBCart = OwnerContext::withOwner($ownerB, fn () => Cart::query()->create([
            'instance' => 'default',
            'identifier' => 'collision-user',
            'items_count' => 2,
        ]));

        CartItem::query()->create([
            'cart_id' => $ownerACart->id,
            'item_id' => 'owner-a-item',
            'name' => 'Owner A Item',
            'price' => 100,
            'quantity' => 1,
        ]);

        CartItem::query()->create([
            'cart_id' => $ownerBCart->id,
            'item_id' => 'owner-b-item',
            'name' => 'Owner B Item',
            'price' => 200,
            'quantity' => 1,
        ]);

        $this->synchronizer->deleteNormalizedCart(
            identifier: 'collision-user',
            instance: 'default',
            ownerType: $ownerA->getMorphClass(),
            ownerId: $ownerA->getKey(),
        );

        expect(Cart::query()->withoutOwnerScope()->find($ownerACart->id))->toBeNull();
        expect(Cart::query()->withoutOwnerScope()->find($ownerBCart->id))->not->toBeNull();
        expect(CartItem::query()->where('cart_id', $ownerACart->id)->count())->toBe(0);
        expect(CartItem::query()->where('cart_id', $ownerBCart->id)->count())->toBe(1);
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
        expect($ownerBCart->owner_type)->toBe($ownerB->getMorphClass());
        expect($ownerBCart->owner_id)->toBe((string) $ownerB->getKey());
    });

    it('syncs metadata and lifecycle timestamps from the cart storage snapshot', function (): void {
        $this->storage->shouldReceive('getItems')->andReturn([]);
        $this->storage->shouldReceive('getConditions')->andReturn([]);
        $this->storage->shouldReceive('getMetadata')->andReturnUsing(fn () => null);
        $this->storage->shouldReceive('getAllMetadata')->andReturn([
            'email' => 'buyer@example.com',
            'last_step' => 'payment',
            'last_activity_at' => now()->subMinutes(45)->toIso8601String(),
            'checkout_started_at' => now()->subHours(2)->toIso8601String(),
            'checkout_abandoned_at' => now()->subHour()->toIso8601String(),
        ]);
        $this->storage->shouldReceive('getCreatedAt')->andReturn(now()->subDay()->toIso8601String());
        $this->storage->shouldReceive('getUpdatedAt')->andReturn(now()->subMinutes(45)->toIso8601String());

        $cart = new BaseCart($this->storage, 'user-456', null, 'default');

        $this->synchronizer->syncFromCart($cart);

        $cartModel = Cart::instance('default')->byIdentifier('user-456')->first();

        expect($cartModel)->not->toBeNull();
        expect($cartModel?->metadata)->toMatchArray([
            'email' => 'buyer@example.com',
            'last_step' => 'payment',
        ]);
        expect($cartModel?->last_activity_at?->toDateTimeString())->toBe(now()->subMinutes(45)->toDateTimeString());
        expect($cartModel?->checkout_started_at?->toDateTimeString())->toBe(now()->subHours(2)->toDateTimeString());
        expect($cartModel?->checkout_abandoned_at?->toDateTimeString())->toBe(now()->subHour()->toDateTimeString());
    });

    it('preserves existing recovery lifecycle timestamps when the cart metadata does not provide replacements', function (): void {
        Cart::create([
            'instance' => 'default',
            'identifier' => 'user-789',
            'last_activity_at' => now()->subHour(),
            'checkout_started_at' => now()->subHours(4),
            'checkout_abandoned_at' => now()->subHours(2),
        ]);

        $this->storage->shouldReceive('getItems')->andReturn([]);
        $this->storage->shouldReceive('getConditions')->andReturn([]);
        $this->storage->shouldReceive('getMetadata')->andReturnUsing(fn () => null);
        $this->storage->shouldReceive('getAllMetadata')->andReturn([]);
        $this->storage->shouldReceive('getCreatedAt')->andReturn(now()->subDay()->toIso8601String());
        $this->storage->shouldReceive('getUpdatedAt')->andReturn(now()->subMinutes(15)->toIso8601String());

        $cart = new BaseCart($this->storage, 'user-789', null, 'default');

        $this->synchronizer->syncFromCart($cart);

        $cartModel = Cart::instance('default')->byIdentifier('user-789')->firstOrFail();

        expect($cartModel->metadata)->toBeNull();
        expect($cartModel->last_activity_at?->toDateTimeString())->toBe(now()->subHour()->toDateTimeString());
        expect($cartModel->checkout_started_at?->toDateTimeString())->toBe(now()->subHours(4)->toDateTimeString());
        expect($cartModel->checkout_abandoned_at?->toDateTimeString())->toBe(now()->subHours(2)->toDateTimeString());
    });

    it('accepts immutable existing timestamps when synchronizing cart snapshots', function (): void {
        Cart::create([
            'instance' => 'default',
            'identifier' => 'user-immutable',
            'last_activity_at' => CarbonImmutable::now()->subHour(),
            'checkout_started_at' => CarbonImmutable::now()->subHours(3),
            'checkout_abandoned_at' => CarbonImmutable::now()->subHours(2),
        ]);

        $this->storage->shouldReceive('getItems')->andReturn([]);
        $this->storage->shouldReceive('getConditions')->andReturn([]);
        $this->storage->shouldReceive('getMetadata')->andReturnUsing(fn () => null);
        $this->storage->shouldReceive('getAllMetadata')->andReturn([]);
        $this->storage->shouldReceive('getCreatedAt')->andReturn(CarbonImmutable::now()->subDay());
        $this->storage->shouldReceive('getUpdatedAt')->andReturn(CarbonImmutable::now()->subMinutes(10));

        $cart = new BaseCart($this->storage, 'user-immutable', null, 'default');

        $this->synchronizer->syncFromCart($cart);

        $cartModel = Cart::instance('default')->byIdentifier('user-immutable')->firstOrFail();

        expect($cartModel->last_activity_at?->toDateTimeString())->toBe(Carbon::now()->subHour()->toDateTimeString())
            ->and($cartModel->checkout_started_at?->toDateTimeString())->toBe(Carbon::now()->subHours(3)->toDateTimeString())
            ->and($cartModel->checkout_abandoned_at?->toDateTimeString())->toBe(Carbon::now()->subHours(2)->toDateTimeString());
    });

    it('emits snapshot and high-value events only when material fields change', function (): void {
        Event::fake([CartSnapshotSynced::class, HighValueCartDetected::class]);

        config()->set('filament-cart.analytics.high_value_threshold_minor', 1000);

        $this->storage->shouldReceive('getItems')->andReturn([
            'item-1' => [
                'id' => 'item-1',
                'name' => 'Product A',
                'quantity' => 1,
                'price' => 1500,
                'attributes' => [],
                'conditions' => [],
            ],
        ]);
        $this->storage->shouldReceive('getConditions')->andReturn([]);
        $this->storage->shouldReceive('getMetadata')->andReturnUsing(fn () => []);
        $this->storage->shouldReceive('getAllMetadata')->andReturn([]);

        $cart = new BaseCart($this->storage, 'high-value-user', null, 'default');

        $this->synchronizer->syncFromCart($cart);

        Event::assertDispatched(CartSnapshotSynced::class);
        Event::assertDispatched(HighValueCartDetected::class);
    });
});
