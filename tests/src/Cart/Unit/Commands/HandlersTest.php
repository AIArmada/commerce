<?php

declare(strict_types=1);

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Commands\AddItemCommand;
use AIArmada\Cart\Commands\ApplyConditionCommand;
use AIArmada\Cart\Commands\ClearCartCommand;
use AIArmada\Cart\Commands\Handlers\AddItemHandler;
use AIArmada\Cart\Commands\Handlers\ApplyConditionHandler;
use AIArmada\Cart\Commands\Handlers\ClearCartHandler;
use AIArmada\Cart\Commands\Handlers\RemoveItemHandler;
use AIArmada\Cart\Commands\Handlers\UpdateItemQuantityHandler;
use AIArmada\Cart\Commands\RemoveItemCommand;
use AIArmada\Cart\Commands\UpdateItemQuantityCommand;
use AIArmada\Cart\Testing\InMemoryStorage;

describe('AddItemHandler', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cartManager = new CartManager($this->storage);
        $this->handler = new AddItemHandler($this->cartManager);
    });

    it('can be instantiated', function (): void {
        expect($this->handler)->toBeInstanceOf(AddItemHandler::class);
    });

    it('handles add item command', function (): void {
        $command = new AddItemCommand(
            identifier: 'user-123',
            instance: 'default',
            itemId: 'item-1',
            itemName: 'Test Product',
            priceInCents: 1000,
            quantity: 2,
            attributes: ['color' => 'blue']
        );

        $item = $this->handler->handle($command);

        expect($item->id)->toBe('item-1')
            ->and($item->name)->toBe('Test Product')
            ->and($item->price)->toBe(1000)
            ->and($item->quantity)->toBe(2);
    });

    it('handles command via invoke', function (): void {
        $command = new AddItemCommand(
            identifier: 'user-456',
            instance: 'default',
            itemId: 'item-2',
            itemName: 'Another Product',
            priceInCents: 500,
            quantity: 1
        );

        $item = ($this->handler)($command);

        expect($item->id)->toBe('item-2')
            ->and($item->name)->toBe('Another Product');
    });
});

describe('ClearCartHandler', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cartManager = new CartManager($this->storage);
        $this->handler = new ClearCartHandler($this->cartManager);
    });

    it('can be instantiated', function (): void {
        expect($this->handler)->toBeInstanceOf(ClearCartHandler::class);
    });

    it('handles clear cart command', function (): void {
        // Add items first
        $cart = $this->cartManager
            ->setIdentifier('user-123')
            ->getCurrentCart();
        $cart->add('item-1', 'Product', 100, 1);

        expect($cart->countItems())->toBe(1);

        $command = new ClearCartCommand(identifier: 'user-123', instance: 'default');
        $this->handler->handle($command);

        // Get fresh cart instance
        $cart = $this->cartManager
            ->setIdentifier('user-123')
            ->getCurrentCart();

        expect($cart->countItems())->toBe(0);
    });

    it('handles command via invoke', function (): void {
        $cart = $this->cartManager
            ->setIdentifier('user-789')
            ->getCurrentCart();
        $cart->add('item-1', 'Product', 100, 1);

        $command = new ClearCartCommand(identifier: 'user-789', instance: 'default');
        ($this->handler)($command);

        $cart = $this->cartManager
            ->setIdentifier('user-789')
            ->getCurrentCart();

        expect($cart->countItems())->toBe(0);
    });
});

describe('RemoveItemHandler', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cartManager = new CartManager($this->storage);
        $this->handler = new RemoveItemHandler($this->cartManager);
    });

    it('can be instantiated', function (): void {
        expect($this->handler)->toBeInstanceOf(RemoveItemHandler::class);
    });

    it('handles remove item command', function (): void {
        $cart = $this->cartManager
            ->setIdentifier('user-123')
            ->getCurrentCart();
        $cart->add('item-1', 'Product A', 100, 1);
        $cart->add('item-2', 'Product B', 200, 1);

        expect($cart->countItems())->toBe(2);

        $command = new RemoveItemCommand(identifier: 'user-123', instance: 'default', itemId: 'item-1');
        $this->handler->handle($command);

        $cart = $this->cartManager
            ->setIdentifier('user-123')
            ->getCurrentCart();

        expect($cart->countItems())->toBe(1)
            ->and($cart->has('item-1'))->toBeFalse()
            ->and($cart->has('item-2'))->toBeTrue();
    });
});

describe('UpdateItemQuantityHandler', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cartManager = new CartManager($this->storage);
        $this->handler = new UpdateItemQuantityHandler($this->cartManager);
    });

    it('can be instantiated', function (): void {
        expect($this->handler)->toBeInstanceOf(UpdateItemQuantityHandler::class);
    });

    it('handles update quantity command', function (): void {
        $cart = $this->cartManager
            ->setIdentifier('user-update-qty')
            ->getCurrentCart();
        $cart->add('item-update', 'Product', 100, 1);

        $command = new UpdateItemQuantityCommand(
            identifier: 'user-update-qty',
            instance: 'default',
            itemId: 'item-update',
            newQuantity: 5
        );

        $this->handler->handle($command);

        $cart = $this->cartManager
            ->setIdentifier('user-update-qty')
            ->getCurrentCart();
        $item = $cart->get('item-update');

        expect($item)->not->toBeNull()
            ->and($item->quantity)->toBeGreaterThanOrEqual(5);
    });
});

describe('ApplyConditionHandler', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cartManager = new CartManager($this->storage);
        $this->handler = new ApplyConditionHandler($this->cartManager);
    });

    it('can be instantiated', function (): void {
        expect($this->handler)->toBeInstanceOf(ApplyConditionHandler::class);
    });

    it('handles apply condition command', function (): void {
        $cart = $this->cartManager
            ->setIdentifier('user-123')
            ->getCurrentCart();
        $cart->add('item-1', 'Product', 1000, 1);

        // Use the updated ApplyConditionCommand with string parameters
        $command = new ApplyConditionCommand(
            identifier: 'user-123',
            instance: 'default',
            conditionName: 'Test Discount',
            conditionType: 'discount',
            value: '-10%'
        );

        $this->handler->handle($command);

        $cart = $this->cartManager
            ->setIdentifier('user-123')
            ->getCurrentCart();

        expect($cart->getConditions())->toHaveCount(1);
    });
});
