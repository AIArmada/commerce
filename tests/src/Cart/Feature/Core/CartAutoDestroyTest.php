<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Facades\Cart;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Config::set('cart.empty_cart_behavior', 'destroy'); // Default behavior
    Event::fake();
    app()->forgetInstance('cart');
});

describe('Auto-Destroy on Empty Cart (default behavior)', function (): void {
    it('destroys cart when last item is removed via remove()', function (): void {
        Cart::add('item1', 'Product 1', 100, 1);
        expect(Cart::has('item1'))->toBeTrue();

        Cart::remove('item1');

        expect(Cart::getItems()->isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
    });

    it('destroys cart when last item quantity is zeroed via update()', function (): void {
        Cart::add('item1', 'Product 1', 100, 5);

        Cart::update('item1', ['quantity' => -5]);

        expect(Cart::getItems()->isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
    });

    it('destroys cart when last item quantity set to zero via absolute update', function (): void {
        Cart::add('item1', 'Product 1', 100, 5);

        Cart::update('item1', ['quantity' => ['value' => 0]]);

        expect(Cart::getItems()->isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
    });

    it('dispatches CartDestroyed event when auto-destroying', function (): void {
        Cart::add('item1', 'Product 1', 100, 1);
        Cart::remove('item1');

        Event::assertDispatched(CartDestroyed::class);
    });

    it('does not destroy cart when multiple items exist', function (): void {
        Cart::add('item1', 'Product 1', 100, 1);
        Cart::add('item2', 'Product 2', 200, 1);

        Cart::remove('item1');

        expect(Cart::has('item2'))->toBeTrue();
        expect(Cart::count())->toBe(1);
    });
});

describe('Empty Cart Behavior: destroy', function (): void {
    beforeEach(function (): void {
        Config::set('cart.empty_cart_behavior', 'destroy');
        Event::fake();
    });

    it('removes cart entirely from storage', function (): void {
        Cart::add('item1', 'Product 1', 100, 1);
        Cart::remove('item1');

        expect(Cart::exists())->toBeFalse();
        Event::assertDispatched(CartDestroyed::class);
    });
});

describe('Empty Cart Behavior: clear', function (): void {
    beforeEach(function (): void {
        Config::set('cart.empty_cart_behavior', 'clear');
        // Clear Cart singleton so it gets new config
        app()->forgetInstance('cart');
        Event::fake();
    });

    it('keeps cart row but removes items, conditions, and metadata', function (): void {
        Cart::add('item1', 'Product 1', 100, 1);
        Cart::setMetadata('affiliate_id', 'AFF123');
        Cart::addDiscount('VOUCHER10', '-10%');

        Cart::remove('item1');

        // Cart should exist but be empty
        expect(Cart::getItems()->isEmpty())->toBeTrue();
        expect(Cart::getConditions()->isEmpty())->toBeTrue();
        expect(Cart::getMetadata('affiliate_id'))->toBeNull();
        // Cart structure should still exist
        expect(Cart::exists())->toBeTrue();
    });
});

describe('Empty Cart Behavior: preserve', function (): void {
    beforeEach(function (): void {
        Config::set('cart.empty_cart_behavior', 'preserve');
        // Clear Cart singleton so it gets new config
        app()->forgetInstance('cart');
        Event::fake();
    });

    it('keeps cart with conditions and metadata intact', function (): void {
        Cart::add('item1', 'Product 1', 100, 1);
        Cart::setMetadata('affiliate_id', 'AFF123');
        Cart::addDiscount('VOUCHER10', '-10%');

        Cart::remove('item1');

        // Items should be empty
        expect(Cart::getItems()->isEmpty())->toBeTrue();

        // Conditions and metadata should be preserved
        expect(Cart::getCondition('VOUCHER10'))->not->toBeNull();
        expect(Cart::getMetadata('affiliate_id'))->toBe('AFF123');

        Event::assertNotDispatched(CartDestroyed::class);
        Event::assertNotDispatched(CartCleared::class);
    });

    it('preserves voucher code when customer removes all items', function (): void {
        // Customer applies voucher first
        Cart::addDiscount('SUMMER20', '-20%');

        // Then adds items
        Cart::add('item1', 'Product 1', 100, 1);
        Cart::add('item2', 'Product 2', 200, 1);

        // Customer removes all items
        Cart::remove('item1');
        Cart::remove('item2');

        // Voucher should still be there when they add new items
        expect(Cart::getCondition('SUMMER20'))->not->toBeNull();
    });

    it('preserves affiliate tracking when cart is emptied', function (): void {
        // Affiliate cookie sets metadata
        Cart::setMetadata('affiliate_id', 'partner-xyz');
        Cart::setMetadata('utm_source', 'instagram');

        Cart::add('item1', 'Product 1', 100, 1);
        Cart::remove('item1');

        // Affiliate tracking should be preserved
        expect(Cart::getMetadata('affiliate_id'))->toBe('partner-xyz');
        expect(Cart::getMetadata('utm_source'))->toBe('instagram');
    });
});

describe('Auto-Destroy with Multiple Instances', function (): void {
    it('destroys specific instance when emptied', function (): void {
        Cart::setInstance('shopping')->add('item1', 'Product 1', 100, 1);
        Cart::setInstance('wishlist')->add('item2', 'Product 2', 200, 1);

        Cart::setInstance('shopping')->remove('item1');

        expect(Cart::setInstance('shopping')->isEmpty())->toBeTrue();
        expect(Cart::setInstance('wishlist')->has('item2'))->toBeTrue();
    });

    it('preserves other instances when one is auto-destroyed', function (): void {
        Cart::setInstance('cart1')->add('item1', 'Product 1', 100, 1);
        Cart::setInstance('cart2')->add('item2', 'Product 2', 200, 1);
        Cart::setInstance('cart3')->add('item3', 'Product 3', 300, 1);

        Cart::setInstance('cart2')->remove('item2');

        expect(Cart::setInstance('cart2')->isEmpty())->toBeTrue();
        expect(Cart::setInstance('cart1')->has('item1'))->toBeTrue();
        expect(Cart::setInstance('cart3')->has('item3'))->toBeTrue();
    });
});

describe('Auto-Destroy with Database Storage', function (): void {
    beforeEach(function (): void {
        Config::set('cart.storage', 'database');
        Config::set('cart.empty_cart_behavior', 'destroy');
        Event::fake();
        Cart::destroy();
    });

    it('removes database record when cart is auto-destroyed', function (): void {
        $identifier = 'user-123';
        Cart::setIdentifier($identifier)->add('item1', 'Product 1', 100, 1);

        expect(
            DB::table('carts')
                ->where('identifier', $identifier)
                ->where('instance', 'default')
                ->exists()
        )->toBeTrue();

        Cart::setIdentifier($identifier)->remove('item1');

        expect(
            DB::table('carts')
                ->where('identifier', $identifier)
                ->where('instance', 'default')
                ->exists()
        )->toBeFalse();
    });

    it('keeps database record with preserve behavior', function (): void {
        Event::fake();
        Config::set('cart.empty_cart_behavior', 'preserve');

        $identifier = 'user-456';
        Cart::setIdentifier($identifier)->add('item1', 'Product 1', 100, 1);
        Cart::setIdentifier($identifier)->setMetadata('voucher', 'SAVE10');

        expect(
            DB::table('carts')
                ->where('identifier', $identifier)
                ->where('instance', 'default')
                ->exists()
        )->toBeTrue();

        Cart::setIdentifier($identifier)->remove('item1');

        $cart = DB::table('carts')
            ->where('identifier', $identifier)
            ->where('instance', 'default')
            ->first();

        expect($cart)->not->toBeNull();

        $items = json_decode($cart->items ?? '[]', true);
        expect($items)->toBe([]);

        // Metadata should be preserved
        $metadata = json_decode($cart->metadata ?? '{}', true);
        expect($metadata['voucher'] ?? null)->toBe('SAVE10');
    });

    it('clears database record with clear behavior', function (): void {
        Event::fake();
        Config::set('cart.empty_cart_behavior', 'clear');

        $identifier = 'user-789';
        Cart::setIdentifier($identifier)->add('item1', 'Product 1', 100, 1);
        Cart::setIdentifier($identifier)->setMetadata('voucher', 'SAVE10');

        Cart::setIdentifier($identifier)->remove('item1');

        $cart = DB::table('carts')
            ->where('identifier', $identifier)
            ->where('instance', 'default')
            ->first();

        // Cart row should exist but be empty
        expect($cart)->not->toBeNull();

        $items = json_decode($cart->items ?? '[]', true);
        expect($items)->toBe([]);

        // Metadata should be cleared
        $metadata = json_decode($cart->metadata ?? '{}', true);
        expect($metadata)->toBe([]);
    });
});
