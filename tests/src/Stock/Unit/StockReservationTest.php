<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;
use Carbon\Carbon;

describe('StockReservation Model', function (): void {
    it('can create a reservation', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $reservation = StockReservation::create([
            'stockable_type' => $product->getMorphClass(),
            'stockable_id' => $product->id,
            'cart_id' => 'test-cart-123',
            'quantity' => 5,
            'expires_at' => now()->addMinutes(30),
        ]);

        expect($reservation)->toBeInstanceOf(StockReservation::class);
        expect($reservation->quantity)->toBe(5);
        expect($reservation->cart_id)->toBe('test-cart-123');
    });

    it('has stockable relationship', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $reservation = StockReservation::create([
            'stockable_type' => $product->getMorphClass(),
            'stockable_id' => $product->id,
            'cart_id' => 'test-cart-123',
            'quantity' => 5,
            'expires_at' => now()->addMinutes(30),
        ]);

        expect($reservation->stockable)->toBeInstanceOf(Product::class);
        expect($reservation->stockable->id)->toBe($product->id);
    });

    it('casts expires_at to datetime', function (): void {
        $product = Product::create(['name' => 'Test Product']);
        $expiresAt = now()->addMinutes(30);

        $reservation = StockReservation::create([
            'stockable_type' => $product->getMorphClass(),
            'stockable_id' => $product->id,
            'cart_id' => 'test-cart-123',
            'quantity' => 5,
            'expires_at' => $expiresAt,
        ]);

        expect($reservation->expires_at)->toBeInstanceOf(Carbon::class);
    });

    describe('scopes', function (): void {
        it('filters by active (not expired) reservations', function (): void {
            $product = Product::create(['name' => 'Test Product']);

            // Active reservation
            StockReservation::create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->id,
                'cart_id' => 'active-cart',
                'quantity' => 5,
                'expires_at' => now()->addMinutes(30),
            ]);

            // Expired reservation
            StockReservation::create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->id,
                'cart_id' => 'expired-cart',
                'quantity' => 3,
                'expires_at' => now()->subMinutes(5),
            ]);

            $active = StockReservation::active()->get();

            expect($active)->toHaveCount(1);
            expect($active->first()->cart_id)->toBe('active-cart');
        });

        it('filters by expired reservations', function (): void {
            $product = Product::create(['name' => 'Test Product']);

            // Active reservation
            StockReservation::create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->id,
                'cart_id' => 'active-cart',
                'quantity' => 5,
                'expires_at' => now()->addMinutes(30),
            ]);

            // Expired reservation
            StockReservation::create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->id,
                'cart_id' => 'expired-cart',
                'quantity' => 3,
                'expires_at' => now()->subMinutes(5),
            ]);

            $expired = StockReservation::expired()->get();

            expect($expired)->toHaveCount(1);
            expect($expired->first()->cart_id)->toBe('expired-cart');
        });

        it('filters by cart id', function (): void {
            $product = Product::create(['name' => 'Test Product']);

            StockReservation::create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->id,
                'cart_id' => 'cart-1',
                'quantity' => 5,
                'expires_at' => now()->addMinutes(30),
            ]);

            StockReservation::create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->id,
                'cart_id' => 'cart-2',
                'quantity' => 3,
                'expires_at' => now()->addMinutes(30),
            ]);

            $forCart1 = StockReservation::where('cart_id', 'cart-1')->get();

            expect($forCart1)->toHaveCount(1);
            expect($forCart1->first()->cart_id)->toBe('cart-1');
        });

        it('filters by stockable model', function (): void {
            $product = Product::create(['name' => 'Test Product']);
            $product2 = Product::create(['name' => 'Other Product']);

            StockReservation::create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->id,
                'cart_id' => 'cart-1',
                'quantity' => 5,
                'expires_at' => now()->addMinutes(30),
            ]);

            StockReservation::create([
                'stockable_type' => $product2->getMorphClass(),
                'stockable_id' => $product2->id,
                'cart_id' => 'cart-2',
                'quantity' => 3,
                'expires_at' => now()->addMinutes(30),
            ]);

            $forProduct = StockReservation::where('stockable_type', $product->getMorphClass())
                ->where('stockable_id', $product->id)
                ->get();

            expect($forProduct)->toHaveCount(1);
            expect($forProduct->first()->stockable_id)->toBe($product->id);
        });
    });

    describe('isExpired method', function (): void {
        it('returns true for expired reservation', function (): void {
            $product = Product::create(['name' => 'Test Product']);

            $reservation = StockReservation::create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->id,
                'cart_id' => 'test-cart',
                'quantity' => 5,
                'expires_at' => now()->subMinutes(5),
            ]);

            expect($reservation->isExpired())->toBeTrue();
        });

        it('returns false for active reservation', function (): void {
            $product = Product::create(['name' => 'Test Product']);

            $reservation = StockReservation::create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->id,
                'cart_id' => 'test-cart',
                'quantity' => 5,
                'expires_at' => now()->addMinutes(30),
            ]);

            expect($reservation->isExpired())->toBeFalse();
        });
    });
});

describe('StockReservationService', function (): void {
    beforeEach(function (): void {
        $this->service = app(StockReservationService::class);
        $this->stockService = app(StockService::class);
        $this->product = Product::create(['name' => 'Test Product']);

        // Add initial stock
        $this->stockService->addStock($this->product, 100);
    });

    describe('reserve', function (): void {
        it('can reserve stock for a cart', function (): void {
            $reservation = $this->service->reserve(
                $this->product,
                10,
                'cart-123',
                30
            );

            expect($reservation)->toBeInstanceOf(StockReservation::class);
            expect($reservation->quantity)->toBe(10);
            expect($reservation->cart_id)->toBe('cart-123');
        });

        it('returns null when insufficient stock', function (): void {
            $reservation = $this->service->reserve(
                $this->product,
                150, // More than available
                'cart-123',
                30
            );

            expect($reservation)->toBeNull();
        });

        it('updates existing reservation for same cart and product', function (): void {
            // First reservation
            $first = $this->service->reserve($this->product, 10, 'cart-123', 30);

            // Second reservation for same cart/product - should update
            $second = $this->service->reserve($this->product, 15, 'cart-123', 30);

            expect($second->id)->toBe($first->id);
            expect($second->quantity)->toBe(15);

            // Only one reservation should exist
            $count = StockReservation::where('cart_id', 'cart-123')
                ->where('stockable_type', $this->product->getMorphClass())
                ->where('stockable_id', $this->product->id)
                ->count();

            expect($count)->toBe(1);
        });
    });

    describe('release', function (): void {
        it('releases a specific reservation', function (): void {
            $this->service->reserve($this->product, 10, 'cart-123', 30);

            $released = $this->service->release($this->product, 'cart-123');

            expect($released)->toBeTrue();
            expect(StockReservation::where('cart_id', 'cart-123')->count())->toBe(0);
        });

        it('returns false when no reservation exists', function (): void {
            $released = $this->service->release($this->product, 'non-existent');

            expect($released)->toBeFalse();
        });
    });

    describe('releaseAllForCart', function (): void {
        it('releases all reservations for a cart', function (): void {
            $product2 = Product::create(['name' => 'Product 2']);
            $this->stockService->addStock($product2, 50);

            $this->service->reserve($this->product, 10, 'cart-123', 30);
            $this->service->reserve($product2, 5, 'cart-123', 30);
            $this->service->reserve($this->product, 3, 'other-cart', 30);

            $released = $this->service->releaseAllForCart('cart-123');

            expect($released)->toBe(2);
            expect(StockReservation::where('cart_id', 'cart-123')->count())->toBe(0);
            expect(StockReservation::where('cart_id', 'other-cart')->count())->toBe(1);
        });
    });

    describe('getAvailableStock', function (): void {
        it('accounts for reservations in available stock', function (): void {
            expect($this->service->getAvailableStock($this->product))->toBe(100);

            $this->service->reserve($this->product, 30, 'cart-1', 30);

            expect($this->service->getAvailableStock($this->product))->toBe(70);

            $this->service->reserve($this->product, 20, 'cart-2', 30);

            expect($this->service->getAvailableStock($this->product))->toBe(50);
        });

        it('ignores expired reservations', function (): void {
            // Create an "expired" reservation manually
            StockReservation::create([
                'stockable_type' => $this->product->getMorphClass(),
                'stockable_id' => $this->product->id,
                'cart_id' => 'expired-cart',
                'quantity' => 30,
                'expires_at' => now()->subMinutes(5),
            ]);

            expect($this->service->getAvailableStock($this->product))->toBe(100);
        });
    });

    describe('getReservation', function (): void {
        it('retrieves reservation for specific cart and product', function (): void {
            $this->service->reserve($this->product, 10, 'cart-123', 30);

            $reservation = $this->service->getReservation($this->product, 'cart-123');

            expect($reservation)->toBeInstanceOf(StockReservation::class);
            expect($reservation->quantity)->toBe(10);
        });

        it('returns null for non-existent reservation', function (): void {
            $reservation = $this->service->getReservation($this->product, 'non-existent');

            expect($reservation)->toBeNull();
        });
    });

    describe('cleanupExpired', function (): void {
        it('removes expired reservations', function (): void {
            // Active reservation
            $this->service->reserve($this->product, 10, 'active-cart', 30);

            // Manually create expired reservations
            StockReservation::create([
                'stockable_type' => $this->product->getMorphClass(),
                'stockable_id' => $this->product->id,
                'cart_id' => 'expired-1',
                'quantity' => 5,
                'expires_at' => now()->subMinutes(10),
            ]);

            StockReservation::create([
                'stockable_type' => $this->product->getMorphClass(),
                'stockable_id' => $this->product->id,
                'cart_id' => 'expired-2',
                'quantity' => 3,
                'expires_at' => now()->subMinutes(5),
            ]);

            $cleaned = $this->service->cleanupExpired();

            expect($cleaned)->toBe(2);
            expect(StockReservation::count())->toBe(1);
            expect(StockReservation::first()->cart_id)->toBe('active-cart');
        });
    });

    describe('commitReservations', function (): void {
        it('deducts stock and removes reservations', function (): void {
            $this->service->reserve($this->product, 10, 'cart-123', 30);

            $transactions = $this->service->commitReservations('cart-123');

            expect($transactions)->toHaveCount(1);
            expect($transactions[0]->quantity)->toBe(10);
            expect($transactions[0]->type)->toBe('out');

            // Reservation should be removed
            expect(StockReservation::where('cart_id', 'cart-123')->count())->toBe(0);

            // Stock should be deducted
            expect($this->stockService->getCurrentStock($this->product))->toBe(90);
        });

        it('includes order reference in transaction', function (): void {
            $this->service->reserve($this->product, 10, 'cart-123', 30);

            $transactions = $this->service->commitReservations('cart-123', 'ORDER-001');

            expect($transactions[0]->reason)->toBe('sale');
            expect($transactions[0]->note)->toContain('ORDER-001');
        });
    });

    describe('getReservedQuantity', function (): void {
        it('returns total reserved quantity for a product', function (): void {
            $this->service->reserve($this->product, 10, 'cart-1', 30);
            $this->service->reserve($this->product, 20, 'cart-2', 30);
            $this->service->reserve($this->product, 5, 'cart-3', 30);

            $total = $this->service->getReservedQuantity($this->product);

            expect($total)->toBe(35);
        });

        it('ignores expired reservations in total', function (): void {
            $this->service->reserve($this->product, 10, 'active-cart', 30);

            StockReservation::create([
                'stockable_type' => $this->product->getMorphClass(),
                'stockable_id' => $this->product->id,
                'cart_id' => 'expired-cart',
                'quantity' => 50,
                'expires_at' => now()->subMinutes(5),
            ]);

            $total = $this->service->getReservedQuantity($this->product);

            expect($total)->toBe(10);
        });
    });

    describe('deductStock', function (): void {
        it('can deduct stock directly', function (): void {
            $product = Product::create(['name' => 'Test Product']);
            $this->stockService->addStock($product, 100);

            $transaction = $this->service->deductStock($product, 25, 'direct-sale', 'ORDER-DIRECT');

            expect($transaction->quantity)->toBe(25);
            expect($transaction->reason)->toBe('direct-sale');
            expect($this->stockService->getCurrentStock($product))->toBe(75);
        });
    });

    describe('extend', function (): void {
        it('can extend reservation', function (): void {
            $reservation = $this->service->reserve($this->product, 10, 'extend-cart', 30);
            $oldExpires = $reservation->expires_at;

            $extended = $this->service->extend($this->product, 'extend-cart', 60);

            // expires_at should be approximately 60 minutes from now
            expect(abs($extended->expires_at->diffInMinutes(now())))->toBeGreaterThan(50);
            expect($extended->expires_at)->not->toBe($oldExpires);
        });
    });

    describe('hasAvailableStock', function (): void {
        it('checks availability', function (): void {
            expect($this->service->hasAvailableStock($this->product, 50))->toBeTrue();
            expect($this->service->hasAvailableStock($this->product, 150))->toBeFalse();

            $this->service->reserve($this->product, 60, 'has-check-cart', 30);

            expect($this->service->hasAvailableStock($this->product, 30))->toBeTrue();
            expect($this->service->hasAvailableStock($this->product, 50))->toBeFalse();
        });
    });
});
