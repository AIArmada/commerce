<?php

declare(strict_types=1);

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\CartSnapshotSynced;
use AIArmada\Cart\Events\HighValueCartDetected;
use AIArmada\Cart\Features\LiveCart\Persistence\DatabaseStorage;
use AIArmada\Cart\Features\Snapshots\EntryPoints\Listeners\SyncCartOnEvent;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot as Cart;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotCondition as CartCondition;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotItem as CartItem;
use AIArmada\Cart\Features\Snapshots\Persistence\NormalizedCartSynchronizer;
use AIArmada\Cart\Features\Snapshots\Services\CartSyncManager;
use AIArmada\Cart\Runtime\CartInstanceManager;
use AIArmada\Cart\Runtime\CartOwnerIdentity;
use AIArmada\Cart\Storage\CartStorageSnapshot;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function normalizedSynchronizerSnapshot(
    array $items = [],
    array $conditions = [],
    array $metadata = [],
    ?string $createdAt = null,
    ?string $updatedAt = null,
): CartStorageSnapshot {
    return new CartStorageSnapshot('cart-id', 1, $items, $conditions, $metadata, $createdAt, $updatedAt);
}

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
        $this->storage->shouldReceive('getSnapshot')->andReturn(normalizedSynchronizerSnapshot());

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
        $this->storage->shouldReceive('getSnapshot')->andReturn(normalizedSynchronizerSnapshot(
            items: [
                'item-1' => [
                    'id' => 'item-1',
                    'name' => 'Product A',
                    'quantity' => 2,
                    'price' => 1000,
                    'attributes' => ['color' => 'red'],
                    'conditions' => [],
                ],
            ],
            conditions: [
                'Promo Code' => [
                    'name' => 'Promo Code',
                    'type' => 'discount',
                    'target_definition' => [
                        'scope' => 'cart',
                        'phase' => 'cart_subtotal',
                        'application' => 'aggregate',
                    ],
                    'value' => '-10%',
                    'order' => 1,
                    'attributes' => ['is_global' => true],
                ],
            ],
        ));

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
        $this->storage->shouldReceive('getSnapshot')->andReturn(normalizedSynchronizerSnapshot());

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
        config()->set('cart.owner.enabled', true);

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

        OwnerContext::withOwner($ownerA, fn () => $this->synchronizer->deleteNormalizedCart(
            identifier: 'collision-user',
            instance: 'default',
            identity: CartOwnerIdentity::fromTypeAndId($ownerA->getMorphClass(), $ownerA->getKey()),
        ));

        expect(Cart::query()->withoutOwnerScope()->find($ownerACart->id))->toBeNull();
        expect(Cart::query()->withoutOwnerScope()->find($ownerBCart->id))->not->toBeNull();
        expect(CartItem::query()->where('cart_id', $ownerACart->id)->count())->toBe(0);
        expect(CartItem::query()->where('cart_id', $ownerBCart->id)->count())->toBe(1);
    });

    it('rejects a snapshot deletion claim outside the matching owner context', function (): void {
        config()->set('cart.owner.enabled', true);

        $ownerA = User::query()->create([
            'name' => 'Delete Claim A',
            'email' => 'delete-claim-a@example.com',
            'password' => 'secret',
        ]);
        $ownerB = User::query()->create([
            'name' => 'Delete Claim B',
            'email' => 'delete-claim-b@example.com',
            'password' => 'secret',
        ]);

        $snapshot = OwnerContext::withOwner($ownerA, fn () => Cart::query()->create([
            'instance' => 'default',
            'identifier' => 'claim-cart',
        ]));

        expect(fn () => OwnerContext::withOwner($ownerB, fn () => $this->synchronizer->deleteNormalizedCart(
            identifier: 'claim-cart',
            instance: 'default',
            identity: CartOwnerIdentity::fromTypeAndId($ownerA->getMorphClass(), $ownerA->getKey()),
        )))->toThrow(AuthorizationException::class);

        expect(Cart::query()->withoutOwnerScope()->whereKey($snapshot->id)->exists())->toBeTrue();
    });

    it('requires explicit global context to delete a global snapshot claim', function (): void {
        config()->set('cart.owner.enabled', true);

        $owner = User::query()->create([
            'name' => 'Global Delete Owner',
            'email' => 'global-delete-owner@example.com',
            'password' => 'secret',
        ]);

        $snapshot = OwnerContext::withOwner(null, fn () => Cart::query()->create([
            'instance' => 'default',
            'identifier' => 'global-claim-cart',
        ]));

        expect(fn () => OwnerContext::withOwner($owner, fn () => $this->synchronizer->deleteNormalizedCart(
            identifier: 'global-claim-cart',
            instance: 'default',
            identity: CartOwnerIdentity::global(),
        )))->toThrow(AuthorizationException::class);

        expect(Cart::query()->withoutOwnerScope()->whereKey($snapshot->id)->exists())->toBeTrue();

        OwnerContext::withOwner(null, fn () => $this->synchronizer->deleteNormalizedCart(
            identifier: 'global-claim-cart',
            instance: 'default',
            identity: CartOwnerIdentity::global(),
        ));

        expect(Cart::query()->withoutOwnerScope()->whereKey($snapshot->id)->exists())->toBeFalse();
    });

    it('resolves the ambient owner when no deletion identity is supplied', function (): void {
        config()->set('cart.owner.enabled', true);

        $ownerA = User::query()->create([
            'name' => 'Ambient Delete A',
            'email' => 'ambient-delete-a@example.com',
            'password' => 'secret',
        ]);
        $ownerB = User::query()->create([
            'name' => 'Ambient Delete B',
            'email' => 'ambient-delete-b@example.com',
            'password' => 'secret',
        ]);

        $snapshotA = OwnerContext::withOwner($ownerA, fn () => Cart::query()->create([
            'instance' => 'default',
            'identifier' => 'ambient-cart',
        ]));
        $snapshotB = OwnerContext::withOwner($ownerB, fn () => Cart::query()->create([
            'instance' => 'default',
            'identifier' => 'ambient-cart',
        ]));

        OwnerContext::withOwner($ownerA, fn () => $this->synchronizer->deleteNormalizedCart('ambient-cart', 'default'));

        expect(Cart::query()->withoutOwnerScope()->whereKey($snapshotA->id)->exists())->toBeFalse()
            ->and(Cart::query()->withoutOwnerScope()->whereKey($snapshotB->id)->exists())->toBeTrue();
    });

    it('rejects malformed destroyed owner tuples before deleting', function (): void {
        $syncManager = new CartSyncManager(
            $this->synchronizer,
            app(CartInstanceManager::class),
        );

        expect(fn () => (new SyncCartOnEvent($syncManager))
            ->handle(new CartDestroyed('malformed-cart', 'default', null, 'users', null)))
            ->toThrow(InvalidArgumentException::class);
    });

    it('does not overwrite another owners cart snapshot when owner mode is enabled', function (): void {
        config()->set('cart.owner.enabled', true);
        config()->set('cart.owner.enabled', true);

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
        $this->storage->shouldReceive('getSnapshot')->andReturn(normalizedSynchronizerSnapshot());

        // The synchronizer derives the snapshot owner from the source cart
        // storage and validates it against the ambient owner context.
        $this->storage->shouldReceive('getOwnerType')->andReturn($ownerA->getMorphClass());
        $this->storage->shouldReceive('getOwnerId')->andReturn($ownerA->getKey());

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
        $this->storage->shouldReceive('getSnapshot')->andReturn(normalizedSynchronizerSnapshot(
            metadata: [
                'email' => 'buyer@example.com',
                'last_step' => 'payment',
                'last_activity_at' => now()->subMinutes(45)->toIso8601String(),
                'checkout_started_at' => now()->subHours(2)->toIso8601String(),
                'checkout_abandoned_at' => now()->subHour()->toIso8601String(),
            ],
            createdAt: now()->subDay()->toIso8601String(),
            updatedAt: now()->subMinutes(45)->toIso8601String(),
        ));

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
        $this->storage->shouldReceive('getSnapshot')->andReturn(normalizedSynchronizerSnapshot(
            createdAt: now()->subDay()->toIso8601String(),
            updatedAt: now()->subMinutes(15)->toIso8601String(),
        ));

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
        $this->storage->shouldReceive('getSnapshot')->andReturn(normalizedSynchronizerSnapshot(
            createdAt: CarbonImmutable::now()->subDay()->toIso8601String(),
            updatedAt: CarbonImmutable::now()->subMinutes(10)->toIso8601String(),
        ));

        $cart = new BaseCart($this->storage, 'user-immutable', null, 'default');

        $this->synchronizer->syncFromCart($cart);

        $cartModel = Cart::instance('default')->byIdentifier('user-immutable')->firstOrFail();

        expect($cartModel->last_activity_at?->toDateTimeString())->toBe(Carbon::now()->subHour()->toDateTimeString())
            ->and($cartModel->checkout_started_at?->toDateTimeString())->toBe(Carbon::now()->subHours(3)->toDateTimeString())
            ->and($cartModel->checkout_abandoned_at?->toDateTimeString())->toBe(Carbon::now()->subHours(2)->toDateTimeString());
    });

    it('reads one live snapshot per sync and does not re-fetch children after upsert', function (): void {
        config(['cart.events' => false, 'cart.owner.enabled' => false]);

        $storage = new DatabaseStorage(app('db')->connection(), 'carts');
        $cart = new BaseCart($storage, 'query-budget-cart', null, 'default');
        $cart->add('sku-1', 'Product', 1000, 1);

        $synchronizer = new NormalizedCartSynchronizer;

        DB::enableQueryLog();
        DB::flushQueryLog();

        $synchronizer->syncFromCart($cart);

        $firstQueries = DB::getQueryLog();
        DB::flushQueryLog();

        $synchronizer->syncFromCart($cart);

        $secondQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $childFetches = static fn (array $queries): array => array_values(array_filter(
            $queries,
            static fn (array $query): bool => str_starts_with(mb_ltrim($query['query']), 'select *')
                && (str_contains($query['query'], 'cart_snapshot_items') || str_contains($query['query'], 'cart_snapshot_conditions')),
        ));
        $childWrites = static fn (array $queries): array => array_values(array_filter(
            $queries,
            static fn (array $query): bool => (str_starts_with(mb_ltrim($query['query']), 'insert')
                || str_starts_with(mb_ltrim($query['query']), 'update')
                || str_starts_with(mb_ltrim($query['query']), 'delete'))
                && (str_contains($query['query'], 'cart_snapshot_items') || str_contains($query['query'], 'cart_snapshot_conditions')),
        ));

        expect($childFetches($firstQueries))->toHaveCount(2)
            ->and($childFetches($secondQueries))->toBe([])
            ->and($childWrites($secondQueries))->toBe([])
            ->and(count($secondQueries))->toBeLessThanOrEqual(3);
    });

    it('dispatches snapshot events only for committed syncs', function (): void {
        config(['cart.events' => false, 'cart.owner.enabled' => false]);

        $storage = new DatabaseStorage(app('db')->connection(), 'carts');
        $cart = new BaseCart($storage, 'rollback-sync-cart', null, 'default');
        $cart->add('sku-1', 'Product', 1000, 1);

        Event::fake([CartSnapshotSynced::class]);

        $fail = true;
        $listener = 'eloquent.saving: ' . Cart::class;
        Event::listen($listener, static function () use (&$fail): void {
            if ($fail) {
                throw new RuntimeException('forced snapshot failure');
            }
        });

        expect(fn () => (new NormalizedCartSynchronizer)->syncFromCart($cart))
            ->toThrow(RuntimeException::class, 'forced snapshot failure');

        $fail = false;

        expect(Cart::query()->where('identifier', 'rollback-sync-cart')->exists())->toBeFalse();
        Event::assertNotDispatched(CartSnapshotSynced::class);

        (new NormalizedCartSynchronizer)->syncFromCart($cart);

        Event::assertDispatched(CartSnapshotSynced::class);
    });

    it('defers snapshot events until the outer transaction commits', function (): void {
        config(['cart.events' => false, 'cart.owner.enabled' => false]);

        $connection = (new Cart)->getConnection();
        $cart = new BaseCart(new DatabaseStorage($connection, 'carts'), 'outer-transaction-cart', null, 'default');
        $cart->add('sku-1', 'Product', 1000, 1);
        Event::fake([CartSnapshotSynced::class]);

        $connection->beginTransaction();
        $this->synchronizer->syncFromCart($cart);
        Event::assertNotDispatched(CartSnapshotSynced::class);
        $connection->rollBack();
        Event::assertNotDispatched(CartSnapshotSynced::class);

        $connection->beginTransaction();
        $this->synchronizer->syncFromCart($cart);
        Event::assertNotDispatched(CartSnapshotSynced::class);
        $connection->commit();

        Event::assertDispatchedTimes(CartSnapshotSynced::class, 1);
    });

    it('emits snapshot and high-value events only when material fields change', function (): void {
        Event::fake([CartSnapshotSynced::class, HighValueCartDetected::class]);

        config()->set('cart.snapshots.analytics.high_value_threshold_minor', 1000);

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
        $this->storage->shouldReceive('getSnapshot')->andReturn(normalizedSynchronizerSnapshot(
            items: [
                'item-1' => [
                    'id' => 'item-1',
                    'name' => 'Product A',
                    'quantity' => 1,
                    'price' => 1500,
                    'attributes' => [],
                    'conditions' => [],
                ],
            ],
        ));

        $cart = new BaseCart($this->storage, 'high-value-user', null, 'default');

        $this->synchronizer->syncFromCart($cart);

        Event::assertDispatched(CartSnapshotSynced::class);
        Event::assertDispatched(HighValueCartDetected::class);
    });
});
