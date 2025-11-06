<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Models\StockTransaction;

test('model can add stock', function (): void {
    $product = Product::create(['name' => 'Test Product']);

    $transaction = $product->addStock(100, 'initial', 'Initial stock');

    expect($transaction)->toBeInstanceOf(StockTransaction::class);
    expect($transaction->quantity)->toBe(100);
    expect($transaction->type)->toBe('in');
    expect($transaction->reason)->toBe('initial');
    expect($transaction->note)->toBe('Initial stock');
});

test('model can remove stock', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $product->addStock(100);

    $transaction = $product->removeStock(20, 'sale', 'Customer purchase');

    expect($transaction)->toBeInstanceOf(StockTransaction::class);
    expect($transaction->quantity)->toBe(20);
    expect($transaction->type)->toBe('out');
    expect($transaction->reason)->toBe('sale');
});

test('model can get current stock', function (): void {
    $product = Product::create(['name' => 'Test Product']);

    expect($product->getCurrentStock())->toBe(0);

    $product->addStock(100);
    expect($product->getCurrentStock())->toBe(100);

    $product->removeStock(30);
    expect($product->getCurrentStock())->toBe(70);

    $product->addStock(50);
    expect($product->getCurrentStock())->toBe(120);
});

test('model can check if has sufficient stock', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $product->addStock(50);

    expect($product->hasStock(30))->toBeTrue();
    expect($product->hasStock(50))->toBeTrue();
    expect($product->hasStock(51))->toBeFalse();
    expect($product->hasStock(100))->toBeFalse();
});

test('model can check if stock is low', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $product->addStock(5);

    // Default threshold is 10
    expect($product->isLowStock())->toBeTrue();

    $product->addStock(10);
    expect($product->isLowStock())->toBeFalse();

    // Custom threshold
    expect($product->isLowStock(20))->toBeTrue();
});

test('model can get stock history', function (): void {
    $product = Product::create(['name' => 'Test Product']);

    $product->addStock(100, 'restock');
    $product->removeStock(20, 'sale');
    $product->addStock(50, 'restock');

    $history = $product->getStockHistory();

    expect($history)->toHaveCount(3);
    expect($history->first()->reason)->toBe('restock');
    expect($history->first()->quantity)->toBe(50);
});

test('model stock transactions relationship works', function (): void {
    $product = Product::create(['name' => 'Test Product']);

    $product->addStock(100);
    $product->removeStock(20);

    expect($product->stockTransactions)->toHaveCount(2);
    expect($product->stockTransactions()->count())->toBe(2);
});
