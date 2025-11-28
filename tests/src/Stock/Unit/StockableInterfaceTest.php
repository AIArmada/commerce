<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Contracts\StockableInterface;
use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Models\StockTransaction;
use AIArmada\Stock\Services\StockService;
use AIArmada\Stock\Traits\HasStock;

describe('StockableInterface Implementation', function (): void {
    beforeEach(function (): void {
        $this->stockService = app(StockService::class);
        $this->product = Product::create(['name' => 'Test Product']);
    });

    describe('Product model with HasStock trait', function (): void {
        it('implements StockableInterface methods via HasStock trait', function (): void {
            // Verify the trait provides the interface methods
            expect(method_exists($this->product, 'getCurrentStock'))->toBeTrue();
            expect(method_exists($this->product, 'hasStock'))->toBeTrue();
            expect(method_exists($this->product, 'isLowStock'))->toBeTrue();
            expect(method_exists($this->product, 'addStock'))->toBeTrue();
            expect(method_exists($this->product, 'removeStock'))->toBeTrue();
            expect(method_exists($this->product, 'getAvailableStock'))->toBeTrue();
            expect(method_exists($this->product, 'reserveStock'))->toBeTrue();
            expect(method_exists($this->product, 'releaseReservedStock'))->toBeTrue();
        });
    });

    describe('getCurrentStock', function (): void {
        it('returns zero for new product', function (): void {
            expect($this->product->getCurrentStock())->toBe(0);
        });

        it('returns correct stock after adding', function (): void {
            $this->stockService->addStock($this->product, 100);

            expect($this->product->getCurrentStock())->toBe(100);
        });

        it('returns correct stock after adding and removing', function (): void {
            $this->stockService->addStock($this->product, 100);
            $this->stockService->removeStock($this->product, 30);

            expect($this->product->getCurrentStock())->toBe(70);
        });
    });

    describe('hasStock', function (): void {
        beforeEach(function (): void {
            $this->stockService->addStock($this->product, 50);
        });

        it('returns true when stock is available', function (): void {
            expect($this->product->hasStock(30))->toBeTrue();
        });

        it('returns true when exactly enough stock', function (): void {
            expect($this->product->hasStock(50))->toBeTrue();
        });

        it('returns false when not enough stock', function (): void {
            expect($this->product->hasStock(51))->toBeFalse();
        });

        it('defaults to checking for 1 unit', function (): void {
            expect($this->product->hasStock())->toBeTrue();
        });
    });

    describe('isLowStock', function (): void {
        it('returns true when below default threshold', function (): void {
            $this->stockService->addStock($this->product, 5);

            expect($this->product->isLowStock())->toBeTrue();
        });

        it('returns false when above default threshold', function (): void {
            $this->stockService->addStock($this->product, 15);

            expect($this->product->isLowStock())->toBeFalse();
        });

        it('uses custom threshold when provided', function (): void {
            $this->stockService->addStock($this->product, 15);

            expect($this->product->isLowStock(20))->toBeTrue();
            expect($this->product->isLowStock(10))->toBeFalse();
        });
    });

    describe('addStock via trait', function (): void {
        it('adds stock and returns transaction', function (): void {
            $transaction = $this->product->addStock(50, 'restock', 'Test note');

            expect($transaction)->toBeInstanceOf(StockTransaction::class);
            expect($transaction->quantity)->toBe(50);
            expect($transaction->type)->toBe('in');
            expect($transaction->reason)->toBe('restock');
            expect($transaction->note)->toBe('Test note');
        });

        it('updates current stock', function (): void {
            $this->product->addStock(75);

            expect($this->product->getCurrentStock())->toBe(75);
        });
    });

    describe('removeStock via trait', function (): void {
        beforeEach(function (): void {
            $this->stockService->addStock($this->product, 100);
        });

        it('removes stock and returns transaction', function (): void {
            $transaction = $this->product->removeStock(30, 'sale', 'Order #123');

            expect($transaction)->toBeInstanceOf(StockTransaction::class);
            expect($transaction->quantity)->toBe(30);
            expect($transaction->type)->toBe('out');
            expect($transaction->reason)->toBe('sale');
        });

        it('updates current stock', function (): void {
            $this->product->removeStock(40);

            expect($this->product->getCurrentStock())->toBe(60);
        });
    });

    describe('getAvailableStock', function (): void {
        beforeEach(function (): void {
            $this->stockService->addStock($this->product, 100);
        });

        it('returns full stock when no reservations', function (): void {
            expect($this->product->getAvailableStock())->toBe(100);
        });

        it('accounts for active reservations', function (): void {
            StockReservation::create([
                'stockable_type' => $this->product->getMorphClass(),
                'stockable_id' => $this->product->id,
                'cart_id' => 'cart-1',
                'quantity' => 30,
                'expires_at' => now()->addMinutes(30),
            ]);

            expect($this->product->getAvailableStock())->toBe(70);
        });

        it('ignores expired reservations', function (): void {
            StockReservation::create([
                'stockable_type' => $this->product->getMorphClass(),
                'stockable_id' => $this->product->id,
                'cart_id' => 'expired-cart',
                'quantity' => 50,
                'expires_at' => now()->subMinutes(5),
            ]);

            expect($this->product->getAvailableStock())->toBe(100);
        });
    });

    describe('reserveStock', function (): void {
        beforeEach(function (): void {
            $this->stockService->addStock($this->product, 100);
        });

        it('creates reservation and returns it', function (): void {
            $reservation = $this->product->reserveStock(25, 'cart-123', 30);

            expect($reservation)->toBeInstanceOf(StockReservation::class);
            expect($reservation->quantity)->toBe(25);
            expect($reservation->cart_id)->toBe('cart-123');
        });

        it('returns null when insufficient stock', function (): void {
            $reservation = $this->product->reserveStock(150, 'cart-123', 30);

            expect($reservation)->toBeNull();
        });

        it('reduces available stock', function (): void {
            $this->product->reserveStock(40, 'cart-123', 30);

            expect($this->product->getAvailableStock())->toBe(60);
        });
    });

    describe('releaseReservedStock', function (): void {
        beforeEach(function (): void {
            $this->stockService->addStock($this->product, 100);
        });

        it('releases reservation and returns true', function (): void {
            $this->product->reserveStock(30, 'cart-123', 30);

            expect(StockReservation::forCart('cart-123')->count())->toBe(1);

            $result = $this->product->releaseReservedStock('cart-123');

            expect($result)->toBeTrue();
            expect(StockReservation::forCart('cart-123')->count())->toBe(0);
        });

        it('returns false when no reservation exists', function (): void {
            $result = $this->product->releaseReservedStock('non-existent');

            expect($result)->toBeFalse();
        });

        it('restores available stock', function (): void {
            $this->product->reserveStock(40, 'cart-123', 30);
            expect($this->product->getAvailableStock())->toBe(60);

            $this->product->releaseReservedStock('cart-123');
            expect($this->product->getAvailableStock())->toBe(100);
        });
    });
});

describe('HasStock Trait Additional Methods', function (): void {
    beforeEach(function (): void {
        $this->stockService = app(StockService::class);
        $this->product = Product::create(['name' => 'Test Product']);
        $this->stockService->addStock($this->product, 100);
    });

    describe('stockTransactions relationship', function (): void {
        it('returns stock transactions collection', function (): void {
            $this->product->addStock(50, 'restock');
            $this->product->removeStock(20, 'sale');

            $transactions = $this->product->stockTransactions;

            expect($transactions)->toHaveCount(3); // Initial + 2 new
            expect($transactions->first())->toBeInstanceOf(StockTransaction::class);
        });
    });

    describe('stockReservations relationship', function (): void {
        it('returns stock reservations collection', function (): void {
            $this->product->reserveStock(10, 'cart-1', 30);
            $this->product->reserveStock(20, 'cart-2', 30);

            $reservations = $this->product->stockReservations;

            expect($reservations)->toHaveCount(2);
            expect($reservations->first())->toBeInstanceOf(StockReservation::class);
        });
    });

    describe('getStockHistory', function (): void {
        it('returns transactions in descending order', function (): void {
            $this->product->addStock(50, 'restock');
            $this->product->removeStock(20, 'sale');
            $this->product->addStock(10, 'return');

            $history = $this->product->getStockHistory();

            // Most recent first
            expect($history->first()->reason)->toBe('return');
        });
    });
});
