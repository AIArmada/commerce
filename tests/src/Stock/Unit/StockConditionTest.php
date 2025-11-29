<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartItem;
use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Cart\StockCondition;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;

it('creates from cart items with sufficient stock', function (): void {
    $cartId = 'test-cart';
    $product = Product::create(['name' => 'Test Product']);
    app(StockService::class)->addStock($product, 100);

    $item = new CartItem(
        id: 'item1',
        name: 'Test',
        price: 1000,
        quantity: 5,
        associatedModel: $product,
    );

    $reservationService = app(StockReservationService::class);

    $condition = StockCondition::fromCartItems($cartId, [$item], $reservationService);

    expect($condition->isValid())->toBeTrue();
    expect($condition->hasIssues())->toBeFalse();
    expect($condition->getIssues())->toBeEmpty();
});

it('detects insufficient stock issues', function (): void {
    $cartId = 'test-cart';
    $product = Product::create(['name' => 'Test Product']);
    app(StockService::class)->addStock($product, 3); // low stock

    $item = new CartItem(
        id: 'item1',
        name: 'Test',
        price: 1000,
        quantity: 5,
        associatedModel: $product,
    );

    $reservationService = app(StockReservationService::class);

    $condition = StockCondition::fromCartItems($cartId, [$item], $reservationService);

    expect($condition->isValid())->toBeFalse();
    expect($condition->getIssues())->toHaveKey('item1');
    expect($condition->getIssues()['item1']['available'])->toBe(3);
    expect($condition->getIssues()['item1']['requested'])->toBe(5);
});

it('skips items without model', function (): void {
    $cartId = 'test-cart';
    $item = new CartItem(
        id: 'no-model',
        name: 'Test',
        price: 1000,
        quantity: 10,
    );

    $reservationService = app(StockReservationService::class);

    $condition = StockCondition::fromCartItems($cartId, [$item], $reservationService);

    expect($condition->isValid())->toBeTrue();
});

it('provides correct attributes', function (): void {
    $condition = new StockCondition('test-cart', 1);
    $condition->setIssues(['item1' => ['name' => 'Test', 'requested' => 10, 'available' => 5]]);

    expect($condition->getName())->toBe('stock_validation');
    expect($condition->getType())->toBe('validation');
    expect($condition->getValue())->toBe(0);
    expect($condition->getOrder())->toBe(1);
    expect($condition->getAttributes())->toHaveKeys(['cart_id', 'has_issues', 'issues']);
});

it('converts to CartCondition', function (): void {
    $condition = new StockCondition('test-cart', 1);
    $cartCondition = $condition->toCartCondition();

    expect($cartCondition->getName())->toBe('stock_validation');
    expect($cartCondition->getType())->toBe('validation');
});