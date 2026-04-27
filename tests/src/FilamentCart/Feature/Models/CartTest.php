<?php

declare(strict_types=1);

use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User as TestUser;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('Cart Model', function (): void {
    it('can be created with factory', function (): void {
        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
            'subtotal' => 1000,
            'total' => 1200,
            'currency' => 'USD',
        ]);

        expect($cart)->toBeInstanceOf(Cart::class);
        expect($cart->instance)->toBe('default');
    });

    it('returns correct table name', function (): void {
        $cart = new Cart;
        expect($cart->getTable())->toContain('snapshots');
    });

    it('formats money attributes correctly', function (): void {
        $cart = Cart::create([
            'identifier' => 'test-1',
            'instance' => 'default',
            'currency' => 'USD',
            'subtotal' => 1050, // $10.50
            'total' => 1200, // $12.00
            'savings' => 100, // $1.00
        ]);

        expect($cart->formattedSubtotal)->toBe('$10.50');
        expect($cart->formattedTotal)->toBe('$12.00');
        expect($cart->formattedSavings)->toBe('$1.00');
    });

    it('calculates dollar attributes', function (): void {
        $cart = Cart::create([
            'identifier' => 'test-2',
            'instance' => 'default',
            'subtotal' => 1050,
            'total' => 1200,
            'savings' => 100,
        ]);

        expect($cart->subtotalInDollars)->toBe(10.50);
        expect($cart->totalInDollars)->toBe(12.00);
        expect($cart->savingsInDollars)->toBe(1.00);
    });

    it('resolves cart instance', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $mockInstance = new AIArmada\Cart\Cart(
            storage: $storage,
            identifier: 'session-123',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
        );
        $manager = Mockery::mock(CartInstanceManager::class);
        $manager->shouldReceive('resolveForSnapshot')
            ->once()
            ->andReturn($mockInstance);

        $this->app->instance(CartInstanceManager::class, $manager);

        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
        ]);

        $instance = $cart->getCartInstance();
        expect($instance)->toBe($mockInstance);
    });

    it('returns null and logs when cart instance cannot be resolved', function (): void {
        $manager = Mockery::mock(CartInstanceManager::class);
        $manager->shouldReceive('resolveForSnapshot')
            ->once()
            ->andThrow(new RuntimeException('nope'));

        $this->app->instance(CartInstanceManager::class, $manager);

        Log::shouldReceive('warning')->once();

        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-err',
        ]);

        expect($cart->getCartInstance())->toBeNull();
    });

    it('has relations', function (): void {
        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => 'item-1',
            'name' => 'Item 1',
            'price' => 100,
            'quantity' => 1,
        ]);

        $condition = CartCondition::create([
            'cart_id' => $cart->id,
            'name' => 'Promo',
            'type' => 'discount',
            'target' => 'cart.subtotal',
            'target_definition' => [],
            'value' => '10%',
        ]);

        expect($cart->items()->count())->toBe(1);
        expect($cart->cartConditions()->count())->toBe(1);
    });

    it('filters cart and item level conditions', function (): void {
        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-levels',
        ]);

        $cartLevel = CartCondition::create([
            'cart_id' => $cart->id,
            'name' => 'Cart Promo',
            'type' => 'discount',
            'target' => 'cart.subtotal',
            'target_definition' => [],
            'value' => '10%',
        ]);

        $itemLevel = CartCondition::create([
            'cart_id' => $cart->id,
            'item_id' => 'item-1',
            'name' => 'Item Promo',
            'type' => 'discount',
            'target' => 'item.price',
            'target_definition' => [],
            'value' => '5%',
        ]);

        expect($cart->cartLevelConditions()->pluck('id')->all())->toContain($cartLevel->id);
        expect($cart->cartLevelConditions()->pluck('id')->all())->not->toContain($itemLevel->id);

        expect($cart->itemLevelConditions()->pluck('id')->all())->toContain($itemLevel->id);
        expect($cart->itemLevelConditions()->pluck('id')->all())->not->toContain($cartLevel->id);
    });

    it('resolves current owner context', function (): void {
        config([
            'filament-cart.owner.enabled' => true,
            'filament-cart.owner.include_global' => false,
        ]);

        $user = TestUser::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $this->app->instance(OwnerResolverInterface::class, new class($user) implements OwnerResolverInterface
        {
            public function __construct(private TestUser $user) {}

            public function resolve(): ?Model
            {
                return $this->user;
            }
        });

        expect(Cart::resolveCurrentOwner()?->id)->toBe($user->id);
    });

    it('resolves associated user relation', function (): void {
        $user = TestUser::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => 'secret',
        ]);

        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => (string) $user->id,
        ]);

        expect($cart->user()->first()?->id)->toBe($user->id);
    });

    it('auto-assigns the resolved owner when direct snapshot writes occur in owner mode', function (): void {
        config()->set('filament-cart.owner.enabled', true);
        config()->set('filament-cart.owner.include_global', false);

        $owner = TestUser::create([
            'name' => 'Snapshot Owner',
            'email' => 'snapshot-owner@example.com',
            'password' => 'secret',
        ]);

        $this->app->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
        {
            public function __construct(private TestUser $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });

        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'owner-assigned-snapshot',
        ]);

        expect($cart->owner_type)->toBe($owner->getMorphClass());
        expect($cart->owner_id)->toBe((string) $owner->getKey());
        expect($cart->getRawOriginal('owner_scope'))->not->toBe('global');
    });

    it('allows explicit global snapshot writes in owner mode', function (): void {
        config()->set('filament-cart.owner.enabled', true);
        config()->set('filament-cart.owner.include_global', false);

        $cart = OwnerContext::withOwner(null, fn () => Cart::create([
            'instance' => 'default',
            'identifier' => 'explicit-global-snapshot',
        ]));

        expect($cart->owner_type)->toBeNull();
        expect($cart->owner_id)->toBeNull();
        expect($cart->getRawOriginal('owner_scope'))->toBe('global');
    });

    it('rejects snapshot writes with an explicit owner that mismatches the current owner context', function (): void {
        config()->set('filament-cart.owner.enabled', true);
        config()->set('filament-cart.owner.include_global', false);

        $ownerA = TestUser::create([
            'name' => 'Snapshot Owner A',
            'email' => 'snapshot-owner-a@example.com',
            'password' => 'secret',
        ]);

        $ownerB = TestUser::create([
            'name' => 'Snapshot Owner B',
            'email' => 'snapshot-owner-b@example.com',
            'password' => 'secret',
        ]);

        $this->app->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
        {
            public function __construct(private TestUser $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });

        expect(fn () => Cart::create([
            'instance' => 'default',
            'identifier' => 'mismatched-snapshot-owner',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]))->toThrow(AuthorizationException::class);
    });

    it('scopes query properly', function (): void {
        Cart::create([
            'instance' => 'default',
            'identifier' => 'abc',
        ]);
        Cart::create([
            'instance' => 'wishlist',
            'identifier' => 'def',
        ]);

        expect(Cart::instance('default')->count())->toBe(1);
        expect(Cart::instance('wishlist')->count())->toBe(1);
        expect(Cart::byIdentifier('abc')->count())->toBe(1);
    });

    it('detects statuses', function (): void {
        $active = Cart::create(['instance' => 'default', 'identifier' => 'active']);
        $abandoned = Cart::create([
            'instance' => 'default',
            'identifier' => 'abandoned',
            'checkout_started_at' => now()->subDay(),
            'checkout_abandoned_at' => now()->subHours(5),
        ]);
        $checkout = Cart::create([
            'instance' => 'default',
            'identifier' => 'checkout',
            'checkout_started_at' => now(),
            'checkout_abandoned_at' => null,
        ]);

        expect($active->isAbandoned())->toBeFalse();
        expect($abandoned->isAbandoned())->toBeTrue();
        expect($checkout->isInCheckout())->toBeTrue();
    });

    it('checks if empty', function (): void {
        $empty = Cart::create(['identifier' => 'e', 'instance' => 'default', 'items_count' => 0]);
        $filled = Cart::create(['identifier' => 'f', 'instance' => 'default', 'items_count' => 5, 'quantity' => 5]);

        expect($empty->isEmpty())->toBeTrue();
        expect($filled->isEmpty())->toBeFalse();
    });
});
