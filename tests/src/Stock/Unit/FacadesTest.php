<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Facades\Stock;
use AIArmada\Stock\Facades\StockReservations;
use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Models\StockTransaction;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;

describe('Stock Facade', function (): void {
    beforeEach(function (): void {
        $this->product = Product::create(['name' => 'Test Product']);
    });

    it('resolves to StockService', function (): void {
        expect(Stock::getFacadeRoot())->toBeInstanceOf(StockService::class);
    });

    it('can add stock via facade', function (): void {
        $transaction = Stock::addStock($this->product, 100, 'restock', 'Test note');

        expect($transaction)->toBeInstanceOf(StockTransaction::class);
        expect($transaction->quantity)->toBe(100);
        expect($transaction->type)->toBe('in');
    });

    it('can remove stock via facade', function (): void {
        Stock::addStock($this->product, 100);
        $transaction = Stock::removeStock($this->product, 30, 'sale');

        expect($transaction)->toBeInstanceOf(StockTransaction::class);
        expect($transaction->quantity)->toBe(30);
        expect($transaction->type)->toBe('out');
    });

    it('can get current stock via facade', function (): void {
        Stock::addStock($this->product, 100);
        Stock::removeStock($this->product, 25);

        expect(Stock::getCurrentStock($this->product))->toBe(75);
    });

    it('can adjust stock via facade', function (): void {
        Stock::addStock($this->product, 100);

        $transaction = Stock::adjustStock($this->product, 100, 120);

        expect($transaction)->toBeInstanceOf(StockTransaction::class);
        expect($transaction->type)->toBe('in');
        expect($transaction->quantity)->toBe(20);
    });

    it('can check has stock via facade', function (): void {
        Stock::addStock($this->product, 50);

        expect(Stock::hasStock($this->product, 30))->toBeTrue();
        expect(Stock::hasStock($this->product, 60))->toBeFalse();
    });

    it('can check is low stock via facade', function (): void {
        Stock::addStock($this->product, 5);

        expect(Stock::isLowStock($this->product))->toBeTrue();

        Stock::addStock($this->product, 20);

        expect(Stock::isLowStock($this->product))->toBeFalse();
    });

    it('can get stock history via facade', function (): void {
        Stock::addStock($this->product, 100, 'restock');
        Stock::removeStock($this->product, 20, 'sale');

        $history = Stock::getStockHistory($this->product);

        expect($history)->toHaveCount(2);
    });
});

describe('StockReservations Facade', function (): void {
    beforeEach(function (): void {
        $this->product = Product::create(['name' => 'Test Product']);
        Stock::addStock($this->product, 100);
    });

    it('resolves to StockReservationService', function (): void {
        expect(StockReservations::getFacadeRoot())->toBeInstanceOf(StockReservationService::class);
    });

    it('can reserve stock via facade', function (): void {
        $reservation = StockReservations::reserve($this->product, 10, 'cart-123', 30);

        expect($reservation)->toBeInstanceOf(StockReservation::class);
        expect($reservation->quantity)->toBe(10);
        expect($reservation->cart_id)->toBe('cart-123');
    });

    it('can release stock via facade', function (): void {
        StockReservations::reserve($this->product, 10, 'cart-123', 30);

        $released = StockReservations::release($this->product, 'cart-123');

        expect($released)->toBeTrue();
        expect(StockReservation::count())->toBe(0);
    });

    it('can release all for cart via facade', function (): void {
        $product2 = Product::create(['name' => 'Product 2']);
        Stock::addStock($product2, 50);

        StockReservations::reserve($this->product, 10, 'cart-123', 30);
        StockReservations::reserve($product2, 5, 'cart-123', 30);

        $count = StockReservations::releaseAllForCart('cart-123');

        expect($count)->toBe(2);
        expect(StockReservation::count())->toBe(0);
    });

    it('can get available stock via facade', function (): void {
        expect(StockReservations::getAvailableStock($this->product))->toBe(100);

        StockReservations::reserve($this->product, 30, 'cart-123', 30);

        expect(StockReservations::getAvailableStock($this->product))->toBe(70);
    });

    it('can check has available stock via facade', function (): void {
        StockReservations::reserve($this->product, 90, 'cart-123', 30);

        expect(StockReservations::hasAvailableStock($this->product, 10))->toBeTrue();
        expect(StockReservations::hasAvailableStock($this->product, 15))->toBeFalse();
    });

    it('can get reserved quantity via facade', function (): void {
        StockReservations::reserve($this->product, 10, 'cart-1', 30);
        StockReservations::reserve($this->product, 20, 'cart-2', 30);

        expect(StockReservations::getReservedQuantity($this->product))->toBe(30);
    });

    it('can get reservation via facade', function (): void {
        StockReservations::reserve($this->product, 10, 'cart-123', 30);

        $reservation = StockReservations::getReservation($this->product, 'cart-123');

        expect($reservation)->toBeInstanceOf(StockReservation::class);
        expect($reservation->quantity)->toBe(10);
    });

    it('can extend reservation via facade', function (): void {
        StockReservations::reserve($this->product, 10, 'cart-123', 30);

        $reservation = StockReservations::extend($this->product, 'cart-123', 60);

        expect($reservation)->toBeInstanceOf(StockReservation::class);
        expect($reservation->expires_at->isFuture())->toBeTrue();
    });

    it('can commit reservations via facade', function (): void {
        StockReservations::reserve($this->product, 10, 'cart-123', 30);

        $transactions = StockReservations::commitReservations('cart-123', 'ORDER-001');

        expect($transactions)->toHaveCount(1);
        expect($transactions[0]->quantity)->toBe(10);
        expect($transactions[0]->type)->toBe('out');
        expect(Stock::getCurrentStock($this->product))->toBe(90);
        expect(StockReservation::count())->toBe(0);
    });

    it('can deduct stock directly via facade', function (): void {
        $transaction = StockReservations::deductStock($this->product, 25, 'sale', 'ORDER-002');

        expect($transaction)->toBeInstanceOf(StockTransaction::class);
        expect($transaction->quantity)->toBe(25);
        expect(Stock::getCurrentStock($this->product))->toBe(75);
    });

    it('can cleanup expired via facade', function (): void {
        // Active reservation
        StockReservations::reserve($this->product, 10, 'active-cart', 30);

        // Manually create expired reservation
        StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'expired-cart',
            'quantity' => 20,
            'expires_at' => now()->subMinutes(10),
        ]);

        $cleaned = StockReservations::cleanupExpired();

        expect($cleaned)->toBe(1);
        expect(StockReservation::count())->toBe(1);
    });
});
