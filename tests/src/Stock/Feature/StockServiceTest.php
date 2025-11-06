<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Models\StockTransaction;
use AIArmada\Stock\Services\StockService;

test('service can add stock to model', function (): void {
    $service = app(StockService::class);
    $product = Product::create(['name' => 'Test Product']);

    $transaction = $service->addStock($product, 100, 'restock', 'Supplier delivery');

    expect($transaction)->toBeInstanceOf(StockTransaction::class);
    expect($transaction->stockable_id)->toBe($product->id);
    expect($transaction->quantity)->toBe(100);
    expect($transaction->type)->toBe('in');
    expect($transaction->reason)->toBe('restock');
    expect($transaction->note)->toBe('Supplier delivery');
});

test('service can remove stock from model', function (): void {
    $service = app(StockService::class);
    $product = Product::create(['name' => 'Test Product']);

    $transaction = $service->removeStock($product, 50, 'sale', 'Customer order');

    expect($transaction)->toBeInstanceOf(StockTransaction::class);
    expect($transaction->stockable_id)->toBe($product->id);
    expect($transaction->quantity)->toBe(50);
    expect($transaction->type)->toBe('out');
    expect($transaction->reason)->toBe('sale');
});

test('service can get current stock for model', function (): void {
    $service = app(StockService::class);
    $product = Product::create(['name' => 'Test Product']);

    expect($service->getCurrentStock($product))->toBe(0);

    $service->addStock($product, 100);
    expect($service->getCurrentStock($product))->toBe(100);

    $service->removeStock($product, 30);
    expect($service->getCurrentStock($product))->toBe(70);
});

test('service can adjust stock', function (): void {
    $service = app(StockService::class);
    $product = Product::create(['name' => 'Test Product']);

    $service->addStock($product, 100);

    // Adjust up
    $transaction = $service->adjustStock($product, 100, 120);
    expect($transaction)->toBeInstanceOf(StockTransaction::class);
    expect($transaction->type)->toBe('in');
    expect($transaction->quantity)->toBe(20);

    // Adjust down
    $transaction = $service->adjustStock($product, 120, 100);
    expect($transaction)->toBeInstanceOf(StockTransaction::class);
    expect($transaction->type)->toBe('out');
    expect($transaction->quantity)->toBe(20);

    // No adjustment needed
    $transaction = $service->adjustStock($product, 100, 100);
    expect($transaction)->toBeNull();
});

test('service can check if model has stock', function (): void {
    $service = app(StockService::class);
    $product = Product::create(['name' => 'Test Product']);

    $service->addStock($product, 50);

    expect($service->hasStock($product, 30))->toBeTrue();
    expect($service->hasStock($product, 50))->toBeTrue();
    expect($service->hasStock($product, 51))->toBeFalse();
});

test('service can check if stock is low', function (): void {
    $service = app(StockService::class);
    $product = Product::create(['name' => 'Test Product']);

    $service->addStock($product, 5);

    expect($service->isLowStock($product))->toBeTrue();

    $service->addStock($product, 10);
    expect($service->isLowStock($product))->toBeFalse();

    // Custom threshold
    expect($service->isLowStock($product, 20))->toBeTrue();
});

test('service can get stock history', function (): void {
    $service = app(StockService::class);
    $product = Product::create(['name' => 'Test Product']);

    $service->addStock($product, 100, 'restock');
    $service->removeStock($product, 20, 'sale');
    $service->addStock($product, 50, 'restock');

    $history = $service->getStockHistory($product);

    expect($history)->toHaveCount(3);
    expect($history->first()->reason)->toBe('restock');
    expect($history->first()->quantity)->toBe(50);
});

test('service creates transactions with proper morphable relationship', function (): void {
    $service = app(StockService::class);
    $product = Product::create(['name' => 'Test Product']);

    $transaction = $service->addStock($product, 100);

    expect($transaction->stockable_type)->toBe($product->getMorphClass());
    expect($transaction->stockable_id)->toBe($product->id);
    expect($transaction->stockable)->toBeInstanceOf(Product::class);
    expect($transaction->stockable->id)->toBe($product->id);
});
