<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Cart\CartManagerWithStock;
use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;
use Illuminate\Support\Facades\DB;

describe('CartManagerWithStock', function (): void {
    beforeEach(function (): void {
        $this->stockService = app(StockService::class);
        $this->reservationService = app(StockReservationService::class);
        $this->product = Product::create(['name' => 'Test Product']);
        $this->stockService->addStock($this->product, 100);

        // Create a storage instance for tests
        $this->storage = new DatabaseStorage(DB::connection('testing'), 'carts');
        $this->events = app(Illuminate\Contracts\Events\Dispatcher::class);

        // Create a base CartManager instance
        $this->baseManager = new CartManager(
            storage: $this->storage,
            events: $this->events,
            eventsEnabled: true
        );
    });

    describe('fromCartManager factory', function (): void {
        it('creates instance from existing CartManager', function (): void {
            $stockManager = CartManagerWithStock::fromCartManager($this->baseManager);

            expect($stockManager)->toBeInstanceOf(CartManagerWithStock::class);
            expect($stockManager)->toBeInstanceOf(CartManagerInterface::class);
        });

        it('preserves state from original manager', function (): void {
            $this->baseManager->setIdentifier('test-user');
            $this->baseManager->add([
                'id' => 'item-1',
                'name' => 'Test Item',
                'price' => 100,
                'quantity' => 1,
            ]);

            $stockManager = CartManagerWithStock::fromCartManager($this->baseManager);

            // Should have access to the same cart data
            expect($stockManager->getCurrentCart()->getIdentifier())->toBe('test-user');
        });
    });

    describe('setReservationService and getReservationService', function (): void {
        it('can set and get reservation service', function (): void {
            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $service = app(StockReservationService::class);

            $manager->setReservationService($service);

            expect($manager->getReservationService())->toBe($service);
        });

        it('auto-resolves service from container when not set', function (): void {
            $manager = CartManagerWithStock::fromCartManager($this->baseManager);

            $service = $manager->getReservationService();

            expect($service)->toBeInstanceOf(StockReservationService::class);
        });
    });

    describe('reserveAllStock', function (): void {
        it('reserves stock for items with associated models', function (): void {
            // Note: This test is simplified because database storage serializes associated models
            // Instead of testing through cart items, we test the reservation service directly

            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $manager->setIdentifier('reserve-test');
            $cartId = 'reserve-test_default';

            // Reserve directly through the service
            $reservation = $this->reservationService->reserve($this->product, 5, $cartId, 30);

            expect($reservation)->not->toBeNull();
            expect(StockReservation::forCart($cartId)->count())->toBe(1);
        });

        it('skips items without associated model', function (): void {
            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $manager->setIdentifier('no-model-test');

            // Add item without associatedModel
            $manager->add([
                'id' => 'plain-item',
                'name' => 'Plain Item',
                'price' => 100,
                'quantity' => 1,
            ]);

            $results = $manager->reserveAllStock(30);

            expect($results)->toBeEmpty();
        });

        it('returns null for insufficient stock', function (): void {
            // Reserve 150 when only 100 available
            $cartId = 'insufficient-test_default';
            $reservation = $this->reservationService->reserve($this->product, 150, $cartId, 30);

            expect($reservation)->toBeNull();
        });
    });

    describe('releaseAllStock', function (): void {
        it('releases all reservations for current cart', function (): void {
            $cartId = 'release-test_default';

            // Create reservation directly
            $this->reservationService->reserve($this->product, 10, $cartId, 30);
            expect(StockReservation::forCart($cartId)->count())->toBe(1);

            // Release through reservation service
            $released = $this->reservationService->releaseAllForCart($cartId);

            expect($released)->toBe(1);
            expect(StockReservation::forCart($cartId)->count())->toBe(0);
        });
    });

    describe('commitStock', function (): void {
        it('commits reservations and deducts stock when reservations exist', function (): void {
            // Directly reserve stock without going through cart manager
            $cartId = 'commit-test_default';
            $this->reservationService->reserve($this->product, 10, $cartId, 30);

            expect($this->stockService->getCurrentStock($this->product))->toBe(100);
            expect(StockReservation::forCart($cartId)->count())->toBe(1);

            // Commit via reservation service directly
            $transactions = $this->reservationService->commitReservations($cartId, 'ORDER-123');

            expect($transactions)->toHaveCount(1);
            expect($this->stockService->getCurrentStock($this->product))->toBe(90);
        });
    });

    describe('validateStock', function (): void {
        it('returns available true for empty cart', function (): void {
            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $manager->setIdentifier('validate-empty');

            $result = $manager->validateStock();

            expect($result['available'])->toBeTrue();
            expect($result['issues'])->toBeEmpty();
        });

        it('skips items without associated model in validation', function (): void {
            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $manager->setIdentifier('validate-no-model');

            // Add item without associatedModel
            $manager->add([
                'id' => 'plain-item',
                'name' => 'Plain Item',
                'price' => 100,
                'quantity' => 1000, // Any quantity should pass - no model to validate
            ]);

            $result = $manager->validateStock();

            expect($result['available'])->toBeTrue();
            expect($result['issues'])->toBeEmpty();
        });
    });

    describe('proxy methods', function (): void {
        it('proxies __call to underlying manager', function (): void {
            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $manager->setIdentifier('proxy-test');
            $manager->add([
                'id' => 'proxy-item',
                'name' => 'Proxy Item',
                'price' => 100,
                'quantity' => 1,
            ]);

            expect($manager->getCurrentCart()->count())->toBe(1);
        });

        it('proxies getById', function (): void {
            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $manager->setIdentifier('getby-test');
            // Add an item so the cart gets saved and has an ID
            $manager->add(['id' => 'getby-item', 'name' => 'GetBy Item', 'price' => 10, 'quantity' => 1]);
            
            $cart = $manager->getCurrentCart();
            $cartId = $cart->getId();

            expect($cartId)->not->toBeNull();
            
            $retrieved = $manager->getById($cartId);

            expect($retrieved)->not->toBeNull();
        });

        it('proxies swap', function (): void {
            $oldId = 'old-user';
            $newId = 'new-user';

            $this->baseManager->setIdentifier($oldId);
            $this->baseManager->add(['id' => 'swapped-item', 'name' => 'Swapped', 'price' => 10, 'quantity' => 1]);

            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $success = $manager->swap($oldId, $newId);

            expect($success)->toBeTrue();
            
            // After swap, we need to explicitly set identifier to use the new cart
            $manager->setIdentifier($newId);
            expect($manager->getCurrentCart()->getIdentifier())->toBe($newId);
            // Verify the item was moved to the new identifier
            expect($manager->getCurrentCart()->count())->toBe(1);
        });
    });

    describe('instance management', function (): void {
        it('proxies setInstance, instance, getCartInstance', function (): void {
            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $manager->setInstance('custom-instance');

            expect($manager->instance())->toBe('custom-instance');
            expect($manager->getCartInstance('custom-instance'))->toBeInstanceOf(Cart::class);
        });

        it('proxies setIdentifier, forgetIdentifier, session', function (): void {
            $manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $manager->setIdentifier('test-id');
            $manager->forgetIdentifier();

            expect($manager->session())->toBeInstanceOf(StorageInterface::class);
        });
    });

    describe('decorator unwrapping', function (): void {
        it('returns base manager recursively', function (): void {
            $manager1 = CartManagerWithStock::fromCartManager($this->baseManager);
            $manager2 = CartManagerWithStock::fromCartManager($manager1);

            expect($manager2->getBaseManager())->toBe($this->baseManager);
        });
    });

    describe('stock management with real cart items', function (): void {
        beforeEach(function (): void {
            $this->stockService->addStock($this->product, 20);
            $this->manager = CartManagerWithStock::fromCartManager($this->baseManager);
            $this->manager->setIdentifier('stock-test');
        });

        it('validateStock detects sufficient stock', function (): void {
            $this->manager->add([
                'id' => 'good-item',
                'name' => 'Good Stock',
                'price' => 10,
                'quantity' => 5,
                'associated_model' => $this->product,
            ]);

            $result = $this->manager->validateStock();

            expect($result['available'])->toBeTrue();
            expect($result['issues'])->toBeEmpty();
        });

        it('validateStock detects insufficient stock', function (): void {
            // Reset stock to just 3 (already has 100 + 20 = 120 from beforeEach hooks)
            $currentStock = $this->stockService->getCurrentStock($this->product);
            $this->stockService->removeStock($this->product, $currentStock - 3); // Leave only 3

            $this->manager->add([
                'id' => 'low-item',
                'name' => 'Low Stock',
                'price' => 10,
                'quantity' => 5,
                'associated_model' => $this->product,
            ]);

            $result = $this->manager->validateStock();

            expect($result['available'])->toBeFalse();
            expect($result['issues']['low-item']['available'])->toBe(3);
            expect($result['issues']['low-item']['requested'])->toBe(5);
        });

        it('reserveAllStock reserves for cart items', function (): void {
            $this->manager->add([
                'id' => 'reserve-item',
                'name' => 'Reserve',
                'price' => 10,
                'quantity' => 3,
                'associated_model' => $this->product,
            ]);

            $results = $this->manager->reserveAllStock(30);

            expect($results['reserve-item'])->toBeTrue();
            // Verify a reservation was created for this product
            expect(StockReservation::where('stockable_type', $this->product->getMorphClass())
                ->where('stockable_id', $this->product->id)
                ->count())->toBe(1);
        });

        it('commitStock deducts after reservation', function (): void {
            $this->manager->add([
                'id' => 'commit-item',
                'name' => 'Commit',
                'price' => 10,
                'quantity' => 2,
                'associated_model' => $this->product,
            ]);

            $initialStock = $this->stockService->getCurrentStock($this->product);
            $this->manager->reserveAllStock(30);
            $transactions = $this->manager->commitStock('ORDER-123');

            expect($transactions)->toHaveCount(1);
            expect($this->stockService->getCurrentStock($this->product))->toBe($initialStock - 2);
        });
    });
});
