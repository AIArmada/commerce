<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Facades\Cart;

it('returns current cart instance when no name is given', function (): void {
    $manager = app('cart');
    expect($manager)->toBeInstanceOf(CartManagerInterface::class);

    $cart = Cart::getCurrentCart();

    expect($cart)->toBeInstanceOf(AIArmada\Cart\Cart::class);

    Cart::clear();
    Cart::add('helper-item', 'Helper Item', 10.00, 1);

    expect($cart->get('helper-item'))->not->toBeNull();
});

it('returns named instance when name is given', function (): void {
    $wishlist = Cart::getCartInstance('wishlist');

    expect($wishlist)->toBeInstanceOf(AIArmada\Cart\Cart::class);
    expect($wishlist->instance())->toBe('wishlist');

    $wishlist->add('wishlist-item', 'Wishlist Item', 5.00, 2);

    // Ensure facade can access items in the same named instance
    Cart::setInstance('wishlist');
    expect(Cart::get('wishlist-item'))->not->toBeNull();
    expect(Cart::get('wishlist-item')->quantity)->toBe(2);
});
