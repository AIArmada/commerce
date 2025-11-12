<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Facades\Cart;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Config::set('cart.preserve_empty_cart', false); // Default to not preserving
    Event::fake(); // Fake events BEFORE any cart operations so Cart gets EventFake
    Cart::destroy();
});

describe('Auto-Destroy on Empty Cart', function () {
    it('destroys cart when last item is removed via remove()', function () {
        Cart::add('item1', 'Product 1', 100, 1);
        expect(Cart::has('item1'))->toBeTrue();

        Cart::remove('item1');

        // Cart should be destroyed - getItems should return empty collection
        expect(Cart::getItems()->isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
    });

    it('destroys cart when last item quantity is zeroed via update()', function () {
        Cart::add('item1', 'Product 1', 100, 5);

        // Update with negative quantity to zero it out
        Cart::update('item1', ['quantity' => -5]);

        expect(Cart::getItems()->isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
    });

    it('destroys cart when last item quantity set to zero via absolute update', function () {
        Cart::add('item1', 'Product 1', 100, 5);

        // Absolute quantity update to 0
        Cart::update('item1', ['quantity' => ['value' => 0]]);

        expect(Cart::getItems()->isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
    });

    it('dispatches CartDestroyed event when auto-destroying', function () {
        Cart::add('item1', 'Product 1', 100, 1);
        Cart::remove('item1');

        Event::assertDispatched(CartDestroyed::class);
    });

    it('does not destroy cart when multiple items exist', function () {
        Cart::add('item1', 'Product 1', 100, 1);
        Cart::add('item2', 'Product 2', 200, 1);

        Cart::remove('item1');

        // Cart should still exist with one item
        expect(Cart::has('item2'))->toBeTrue();
        expect(Cart::count())->toBe(1);
    });
});

describe('Preserve Empty Cart Config', function () {
    it('preserves empty cart when config is true', function () {
        Config::set('cart.preserve_empty_cart', true);

        Cart::add('item1', 'Product 1', 100, 1);
        Cart::remove('item1');

        // Cart should be preserved (empty but not destroyed)
        expect(Cart::getItems()->isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
    });

    it('does not dispatch CartDestroyed when preserving empty cart', function () {
        // Clear any events from beforeEach
        Event::fake();
        
        Config::set('cart.preserve_empty_cart', true);

        // Verify config is set
        expect(config('cart.preserve_empty_cart'))->toBeTrue();

        Cart::add('item1', 'Product 1', 100, 1);
        Cart::remove('item1');

        Event::assertNotDispatched(CartDestroyed::class);
    });

    it('preserves empty cart when last item zeroed via update and config is true', function () {
        Config::set('cart.preserve_empty_cart', true);

        Cart::add('item1', 'Product 1', 100, 5);
        Cart::update('item1', ['quantity' => -5]);

        expect(Cart::getItems()->isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
    });
});

describe('Auto-Destroy with Multiple Instances', function () {
    it('destroys specific instance when emptied', function () {
        Cart::setInstance('shopping')->add('item1', 'Product 1', 100, 1);
        Cart::setInstance('wishlist')->add('item2', 'Product 2', 200, 1);

        Cart::setInstance('shopping')->remove('item1');

        // Shopping cart should be destroyed
        expect(Cart::setInstance('shopping')->isEmpty())->toBeTrue();

        // Wishlist should still exist
        expect(Cart::setInstance('wishlist')->has('item2'))->toBeTrue();
    });

    it('preserves other instances when one is auto-destroyed', function () {
        Cart::setInstance('cart1')->add('item1', 'Product 1', 100, 1);
        Cart::setInstance('cart2')->add('item2', 'Product 2', 200, 1);
        Cart::setInstance('cart3')->add('item3', 'Product 3', 300, 1);

        Cart::setInstance('cart2')->remove('item2');

        // cart2 should be destroyed
        expect(Cart::setInstance('cart2')->isEmpty())->toBeTrue();

        // cart1 and cart3 should remain
        expect(Cart::setInstance('cart1')->has('item1'))->toBeTrue();
        expect(Cart::setInstance('cart3')->has('item3'))->toBeTrue();
    });
});

describe('Auto-Destroy with Database Storage', function () {
    beforeEach(function () {
        Config::set('cart.storage', 'database');
        Event::fake(); // Re-fake after config change to get new Cart with DB storage
        Cart::destroy();
    });

    it('removes database record when cart is auto-destroyed', function () {
        $identifier = 'user-123';
        Cart::setIdentifier($identifier)->add('item1', 'Product 1', 100, 1);

        // Verify record exists
        expect(
            \Illuminate\Support\Facades\DB::table('carts')
                ->where('identifier', $identifier)
                ->where('instance', 'default')
                ->exists()
        )->toBeTrue();

        Cart::setIdentifier($identifier)->remove('item1');

        // Verify record is deleted
        expect(
            \Illuminate\Support\Facades\DB::table('carts')
                ->where('identifier', $identifier)
                ->where('instance', 'default')
                ->exists()
        )->toBeFalse();
    });

    it('keeps database record when preserve_empty_cart is true', function () {
        // Reset events to avoid leakage from beforeEach destroy() call
        Event::fake();
        
        Config::set('cart.preserve_empty_cart', true);

        $identifier = 'user-456';
        Cart::setIdentifier($identifier)->add('item1', 'Product 1', 100, 1);
        
        // Verify cart was created
        expect(
            \Illuminate\Support\Facades\DB::table('carts')
                ->where('identifier', $identifier)
                ->where('instance', 'default')
                ->exists()
        )->toBeTrue();
        
        Cart::setIdentifier($identifier)->remove('item1');

        // Record should still exist with empty items when preserve_empty_cart is true
        $cart = \Illuminate\Support\Facades\DB::table('carts')
            ->where('identifier', $identifier)
            ->where('instance', 'default')
            ->first();

        // Cart should still exist
        expect($cart)->not->toBeNull();
        
        // Items should be empty array
        $items = json_decode($cart->items ?? '[]', true);
        expect($items)->toBe([]);
    });
});
