<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Events\LowStockDetected;
use AIArmada\Stock\Events\OutOfStock;
use AIArmada\Stock\Events\StockDeducted;
use AIArmada\Stock\Events\StockReleased;
use AIArmada\Stock\Events\StockReserved;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;
use Illuminate\Support\Facades\Event;

describe('Event Configuration Flags', function (): void {
    beforeEach(function (): void {
        $this->stockService = app(StockService::class);
        $this->reservationService = app(StockReservationService::class);
        $this->product = Product::create(['name' => 'Test Product']);
        $this->stockService->addStock($this->product, 100);
    });

    describe('StockReserved event', function (): void {
        it('dispatches when config enabled (default)', function (): void {
            Event::fake([StockReserved::class]);
            config(['stock.events.reserved' => true]);

            $this->reservationService->reserve($this->product, 10, 'cart-123', 30);

            Event::assertDispatched(StockReserved::class);
        });

        it('does not dispatch when config disabled', function (): void {
            Event::fake([StockReserved::class]);
            config(['stock.events.reserved' => false]);

            // Need fresh instance to respect config
            $service = new StockReservationService(app(StockService::class));
            $service->reserve($this->product, 10, 'cart-123', 30);

            Event::assertNotDispatched(StockReserved::class);
        });
    });

    describe('StockReleased event', function (): void {
        it('dispatches when config enabled (default)', function (): void {
            Event::fake([StockReleased::class]);
            config(['stock.events.released' => true]);

            $this->reservationService->reserve($this->product, 10, 'cart-123', 30);
            $this->reservationService->release($this->product, 'cart-123');

            Event::assertDispatched(StockReleased::class);
        });

        it('does not dispatch when config disabled', function (): void {
            Event::fake([StockReleased::class]);
            config(['stock.events.released' => false]);

            $service = new StockReservationService(app(StockService::class));
            $service->reserve($this->product, 10, 'cart-123', 30);
            $service->release($this->product, 'cart-123');

            Event::assertNotDispatched(StockReleased::class);
        });
    });

    describe('StockDeducted event', function (): void {
        it('dispatches when config enabled (default)', function (): void {
            Event::fake([StockDeducted::class]);
            config(['stock.events.deducted' => true]);

            $this->reservationService->deductStock($this->product, 10, 'sale', 'ORDER-001');

            Event::assertDispatched(StockDeducted::class);
        });

        it('does not dispatch when config disabled', function (): void {
            Event::fake([StockDeducted::class]);
            config(['stock.events.deducted' => false]);

            $service = new StockReservationService(app(StockService::class));
            $service->deductStock($this->product, 10, 'sale', 'ORDER-001');

            Event::assertNotDispatched(StockDeducted::class);
        });
    });

    describe('LowStockDetected event', function (): void {
        beforeEach(function (): void {
            // Reduce stock to trigger low stock on next deduction
            $this->stockService->removeStock($this->product, 95);
        });

        it('dispatches when config enabled and threshold reached', function (): void {
            Event::fake([LowStockDetected::class]);
            config(['stock.events.low_stock' => true]);

            $this->reservationService->deductStock($this->product, 2, 'sale');

            Event::assertDispatched(LowStockDetected::class);
        });

        it('does not dispatch when config disabled', function (): void {
            Event::fake([LowStockDetected::class]);
            config(['stock.events.low_stock' => false]);

            $service = new StockReservationService(app(StockService::class));
            $service->deductStock($this->product, 2, 'sale');

            Event::assertNotDispatched(LowStockDetected::class);
        });
    });

    describe('OutOfStock event', function (): void {
        beforeEach(function (): void {
            // Reduce stock to trigger out of stock on next deduction
            $this->stockService->removeStock($this->product, 99);
        });

        it('dispatches when config enabled and stock depleted', function (): void {
            Event::fake([OutOfStock::class]);
            config(['stock.events.out_of_stock' => true]);

            $this->reservationService->deductStock($this->product, 1, 'sale');

            Event::assertDispatched(OutOfStock::class);
        });

        it('does not dispatch when config disabled', function (): void {
            Event::fake([OutOfStock::class]);
            config(['stock.events.out_of_stock' => false]);

            $service = new StockReservationService(app(StockService::class));
            $service->deductStock($this->product, 1, 'sale');

            Event::assertNotDispatched(OutOfStock::class);
        });
    });
});

describe('Cleanup Configuration', function (): void {
    beforeEach(function (): void {
        $this->stockService = app(StockService::class);
        $this->product = Product::create(['name' => 'Test Product']);
        $this->stockService->addStock($this->product, 100);
    });

    it('respects keep_expired_for_minutes config', function (): void {
        config(['stock.cleanup.keep_expired_for_minutes' => 10]);

        // Create reservation expired 5 minutes ago (within grace period)
        \AIArmada\Stock\Models\StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'recently-expired',
            'quantity' => 10,
            'expires_at' => now()->subMinutes(5),
        ]);

        // Create reservation expired 15 minutes ago (beyond grace period)
        \AIArmada\Stock\Models\StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'old-expired',
            'quantity' => 20,
            'expires_at' => now()->subMinutes(15),
        ]);

        $service = new StockReservationService(app(StockService::class));
        $cleaned = $service->cleanupExpired();

        // Only the older one should be cleaned
        expect($cleaned)->toBe(1);
        expect(\AIArmada\Stock\Models\StockReservation::count())->toBe(1);
        expect(\AIArmada\Stock\Models\StockReservation::first()->cart_id)->toBe('recently-expired');
    });

    it('cleans all expired when keep_expired_for_minutes is 0', function (): void {
        config(['stock.cleanup.keep_expired_for_minutes' => 0]);

        // Create expired reservations
        \AIArmada\Stock\Models\StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'expired-1',
            'quantity' => 10,
            'expires_at' => now()->subMinutes(1),
        ]);

        \AIArmada\Stock\Models\StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'expired-2',
            'quantity' => 20,
            'expires_at' => now()->subMinutes(30),
        ]);

        $service = new StockReservationService(app(StockService::class));
        $cleaned = $service->cleanupExpired();

        expect($cleaned)->toBe(2);
        expect(\AIArmada\Stock\Models\StockReservation::count())->toBe(0);
    });
});
