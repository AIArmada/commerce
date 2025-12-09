<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Events\LowStockDetected;
use AIArmada\Stock\Events\OutOfStock;
use AIArmada\Stock\Events\StockDeducted;
use AIArmada\Stock\Events\StockReleased;
use AIArmada\Stock\Events\StockReserved;
use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Models\StockTransaction;
use Illuminate\Foundation\Events\Dispatchable;

describe('StockReserved Event', function (): void {
    it('can be instantiated with all required arguments', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $reservation = StockReservation::create([
            'stockable_type' => $product->getMorphClass(),
            'stockable_id' => $product->id,
            'cart_id' => 'cart-123',
            'quantity' => 10,
            'expires_at' => now()->addMinutes(30),
        ]);

        $event = new StockReserved(
            stockable: $product,
            quantity: 10,
            cartId: 'cart-123',
            reservation: $reservation
        );

        expect($event->stockable)->toBe($product);
        expect($event->quantity)->toBe(10);
        expect($event->cartId)->toBe('cart-123');
        expect($event->reservation)->toBe($reservation);
    });

    it('is broadcastable', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $reservation = StockReservation::create([
            'stockable_type' => $product->getMorphClass(),
            'stockable_id' => $product->id,
            'cart_id' => 'cart-123',
            'quantity' => 10,
            'expires_at' => now()->addMinutes(30),
        ]);

        $event = new StockReserved(
            stockable: $product,
            quantity: 10,
            cartId: 'cart-123',
            reservation: $reservation
        );

        expect(class_uses_recursive($event))->toContain(Dispatchable::class);
    });
});

describe('StockReleased Event', function (): void {
    it('can be instantiated with stockable, quantity, and cart', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $event = new StockReleased(
            stockable: $product,
            quantity: 15,
            cartId: 'cart-456'
        );

        expect($event->stockable)->toBe($product);
        expect($event->quantity)->toBe(15);
        expect($event->cartId)->toBe('cart-456');
    });
});

describe('StockDeducted Event', function (): void {
    it('can be instantiated with all required arguments', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $transaction = StockTransaction::create([
            'stockable_type' => $product->getMorphClass(),
            'stockable_id' => $product->id,
            'quantity' => 5,
            'type' => 'out',
            'reason' => 'sale',
        ]);

        $event = new StockDeducted(
            stockable: $product,
            quantity: 5,
            reason: 'sale',
            orderId: null,
            transaction: $transaction
        );

        expect($event->stockable)->toBe($product);
        expect($event->quantity)->toBe(5);
        expect($event->reason)->toBe('sale');
        expect($event->orderId)->toBeNull();
        expect($event->transaction)->toBe($transaction);
    });

    it('includes optional order id', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $transaction = StockTransaction::create([
            'stockable_type' => $product->getMorphClass(),
            'stockable_id' => $product->id,
            'quantity' => 5,
            'type' => 'out',
            'reason' => 'order',
        ]);

        $event = new StockDeducted(
            stockable: $product,
            quantity: 5,
            reason: 'order',
            orderId: 'ORDER-123',
            transaction: $transaction
        );

        expect($event->orderId)->toBe('ORDER-123');
    });
});

describe('LowStockDetected Event', function (): void {
    it('can be instantiated with stockable and quantities', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $event = new LowStockDetected(
            stockable: $product,
            currentStock: 5,
            threshold: 10
        );

        expect($event->stockable)->toBe($product);
        expect($event->currentStock)->toBe(5);
        expect($event->threshold)->toBe(10);
    });
});

describe('OutOfStock Event', function (): void {
    it('can be instantiated with stockable', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $event = new OutOfStock(stockable: $product);

        expect($event->stockable)->toBe($product);
    });

    it('includes product identifier', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $event = new OutOfStock(stockable: $product);

        expect($event->stockable->id)->toBe($product->id);
    });

    it('includes product type', function (): void {
        $product = Product::create(['name' => 'Test Product']);

        $event = new OutOfStock(stockable: $product);

        expect($event->stockable->getMorphClass())->toBe($product->getMorphClass());
    });
});

describe('Event Dispatching', function (): void {
    it('can dispatch StockReserved event', function (): void {
        Event::fake([StockReserved::class]);

        $product = Product::create(['name' => 'Test Product']);

        $reservation = StockReservation::create([
            'stockable_type' => $product->getMorphClass(),
            'stockable_id' => $product->id,
            'cart_id' => 'cart-123',
            'quantity' => 10,
            'expires_at' => now()->addMinutes(30),
        ]);

        StockReserved::dispatch($product, 10, 'cart-123', $reservation);

        Event::assertDispatched(StockReserved::class, function ($event) use ($product, $reservation) {
            return $event->stockable->id === $product->id
                && $event->quantity === 10
                && $event->cartId === 'cart-123'
                && $event->reservation->id === $reservation->id;
        });
    });

    it('can dispatch StockReleased event', function (): void {
        Event::fake([StockReleased::class]);

        $product = Product::create(['name' => 'Test Product']);

        StockReleased::dispatch($product, 15, 'cart-789');

        Event::assertDispatched(StockReleased::class, function ($event) use ($product) {
            return $event->stockable->id === $product->id
                && $event->quantity === 15
                && $event->cartId === 'cart-789';
        });
    });

    it('can dispatch LowStockDetected event', function (): void {
        Event::fake([LowStockDetected::class]);

        $product = Product::create(['name' => 'Test Product']);

        LowStockDetected::dispatch($product, 5, 10);

        Event::assertDispatched(LowStockDetected::class, function ($event) use ($product) {
            return $event->stockable->id === $product->id
                && $event->currentStock === 5
                && $event->threshold === 10;
        });
    });

    it('can dispatch OutOfStock event', function (): void {
        Event::fake([OutOfStock::class]);

        $product = Product::create(['name' => 'Test Product']);

        OutOfStock::dispatch($product);

        Event::assertDispatched(OutOfStock::class, function ($event) use ($product) {
            return $event->stockable->id === $product->id;
        });
    });
});
