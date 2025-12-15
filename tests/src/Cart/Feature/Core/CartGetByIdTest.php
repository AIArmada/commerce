<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Commerce\Tests\Fixtures\Models\User;

describe('Cart getById', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('retrieves a cart by id', function (): void {
        Cart::add('item', 'Item', 10.00, 1);

        $cartId = Cart::getId();
        expect($cartId)->not->toBeNull();

        $cart = Cart::getById($cartId);

        expect($cart)->not->toBeNull();
        expect($cart?->getId())->toBe($cartId);
        expect($cart?->get('item'))->not->toBeNull();
    });

    it('scopes getById to the current owner', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'owner-b@example.com',
            'password' => 'secret',
        ]);

        $cartA = Cart::forOwner($ownerA);
        $cartA->setIdentifier('shared-user');
        $cartA->add('item-a', 'Item A', 10.00, 1);
        $cartIdA = $cartA->getId();

        expect($cartIdA)->not->toBeNull();

        $cartB = Cart::forOwner($ownerB);
        $cartB->setIdentifier('shared-user');
        $cartB->add('item-b', 'Item B', 5.00, 1);

        expect($cartB->getById($cartIdA))->toBeNull();

        $foundByA = $cartA->getById($cartIdA);
        expect($foundByA)->not->toBeNull();
        expect($foundByA?->getId())->toBe($cartIdA);
        expect($foundByA?->get('item-a'))->not->toBeNull();
    });
});
