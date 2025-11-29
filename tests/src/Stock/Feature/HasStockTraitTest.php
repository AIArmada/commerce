<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Models\StockReservation;
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

test('model can get available stock accounting for reservations', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $product->addStock(100);

    // No reservations
    expect($product->getAvailableStock())->toBe(100);

    // Create reservation
    $product->reserveStock(20, 'test-cart', 30);

    expect($product->getAvailableStock())->toBe(80);

    // Release reservation
    $product->releaseReservedStock('test-cart');

    expect($product->getAvailableStock())->toBe(100);
});

test('model can reserve stock', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $product->addStock(100);

    $reservation = $product->reserveStock(15, 'reserve-cart', 30);

    expect($reservation)->not->toBeNull();
    expect($reservation->quantity)->toBe(15);
    expect($reservation->cart_id)->toBe('reserve-cart');
});

test('model can release reserved stock', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $product->addStock(100);

    $product->reserveStock(10, 'release-cart', 30);
    $released = $product->releaseReservedStock('release-cart');

    expect($released)->toBeTrue();
    expect(StockReservation::where('cart_id', 'release-cart')->count())->toBe(0);
});

test('model can get specific reservation', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $product->addStock(100);

    $product->reserveStock(12, 'get-reservation-cart', 30);

    $reservation = $product->getReservation('get-reservation-cart');

    expect($reservation)->not->toBeNull();
    expect($reservation->quantity)->toBe(12);
});

test('model can get total reserved quantity', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $product->addStock(100);

    $product->reserveStock(8, 'reserved1-cart', 30);
    $product->reserveStock(7, 'reserved2-cart', 30);

    expect($product->getReservedQuantity())->toBe(15);
});

test('model can check available stock sufficiency', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $product->addStock(50);

    expect($product->hasAvailableStock(40))->toBeTrue();
    expect($product->hasAvailableStock(60))->toBeFalse();

    // With reservation
    $product->reserveStock(20, 'has-available-cart', 30);

    expect($product->hasAvailableStock(20))->toBeTrue(); // 50 - 20 = 30 >= 20
    expect($product->hasAvailableStock(40))->toBeFalse(); // 30 < 40
});
