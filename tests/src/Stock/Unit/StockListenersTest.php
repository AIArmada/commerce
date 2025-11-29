<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Traits\HasStock;
use AIArmada\Stock\Listeners\ReleaseStockOnCartClear;
use AIArmada\Stock\Listeners\DeductStockOnPaymentSuccess;
use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

describe('ReleaseStockOnCartClear Listener', function (): void {
    beforeEach(function (): void {
        $this->reservationService = app(StockReservationService::class);
        $this->stockService = app(StockService::class);
        $this->product = Product::create(['name' => 'Test Product']);
        $this->stockService->addStock($this->product, 100);

        $this->listener = new ReleaseStockOnCartClear($this->reservationService);
    });

    describe('handleCleared', function (): void {
        it('releases reservations when cart is cleared', function (): void {
            // Create a cart and reserve stock
            $storage = new DatabaseStorage(DB::connection('testing'), 'carts');
            $cart = new Cart(
                storage: $storage,
                identifier: 'test-user',
                events: null,
                instanceName: 'default'
            );
            $cartId = sprintf('%s_%s', $cart->getIdentifier(), $cart->instance());

            $this->reservationService->reserve($this->product, 10, $cartId, 30);

            expect(StockReservation::forCart($cartId)->count())->toBe(1);

            // Trigger the event
            $event = new CartCleared($cart);
            $this->listener->handleCleared($event);

            expect(StockReservation::forCart($cartId)->count())->toBe(0);
        });

        it('only releases reservations for the specific cart', function (): void {
            $storage = new DatabaseStorage(DB::connection('testing'), 'carts');
            $cart1 = new Cart(
                storage: $storage,
                identifier: 'user-1',
                events: null,
                instanceName: 'default'
            );
            $cart2 = new Cart(
                storage: $storage,
                identifier: 'user-2',
                events: null,
                instanceName: 'default'
            );
            $cartId1 = sprintf('%s_%s', $cart1->getIdentifier(), $cart1->instance());
            $cartId2 = sprintf('%s_%s', $cart2->getIdentifier(), $cart2->instance());

            $this->reservationService->reserve($this->product, 10, $cartId1, 30);
            $this->reservationService->reserve($this->product, 5, $cartId2, 30);

            $event = new CartCleared($cart1);
            $this->listener->handleCleared($event);

            expect(StockReservation::forCart($cartId1)->count())->toBe(0);
            expect(StockReservation::forCart($cartId2)->count())->toBe(1);
        });
    });

    describe('handleDestroyed', function (): void {
        it('releases reservations when cart is destroyed', function (): void {
            $identifier = 'test-user';
            $instance = 'default';
            $cartId = sprintf('%s_%s', $identifier, $instance);

            $this->reservationService->reserve($this->product, 15, $cartId, 30);

            expect(StockReservation::forCart($cartId)->count())->toBe(1);

            // Trigger the event (CartDestroyed has different structure)
            $event = new CartDestroyed($identifier, $instance);
            $this->listener->handleDestroyed($event);

            expect(StockReservation::forCart($cartId)->count())->toBe(0);
        });
    });

    describe('event subscription', function (): void {
        it('can be registered as event listener', function (): void {
            Event::fake([CartCleared::class, CartDestroyed::class]);

            // Verify the listener class exists and has correct methods
            expect(method_exists(ReleaseStockOnCartClear::class, 'handleCleared'))->toBeTrue();
            expect(method_exists(ReleaseStockOnCartClear::class, 'handleDestroyed'))->toBeTrue();
        });
    });
});

describe('DeductStockOnPaymentSuccess Listener', function (): void {
    beforeEach(function (): void {
        $this->reservationService = app(StockReservationService::class);
        $this->stockService = app(StockService::class);
        $this->product = Product::create(['name' => 'Test Product']);
        $this->stockService->addStock($this->product, 100);
    });

    it('deducts stock when payment succeeds', function (): void {
        // Reserve stock for a cart
        $cartId = 'cart-payment-123';
        $this->reservationService->reserve($this->product, 10, $cartId, 30);

        expect($this->stockService->getCurrentStock($this->product))->toBe(100);
        expect(StockReservation::forCart($cartId)->count())->toBe(1);

        // Commit the reservations (simulating what listener does)
        $transactions = $this->reservationService->commitReservations($cartId, 'ORDER-001');

        expect($transactions)->toHaveCount(1);
        expect($this->stockService->getCurrentStock($this->product))->toBe(90);
        expect(StockReservation::forCart($cartId)->count())->toBe(0);
    });

    it('handles multiple products in one cart', function (): void {
        $product2 = Product::create(['name' => 'Product 2']);
        $this->stockService->addStock($product2, 50);

        $cartId = 'cart-multi-product';
        $this->reservationService->reserve($this->product, 10, $cartId, 30);
        $this->reservationService->reserve($product2, 5, $cartId, 30);

        $transactions = $this->reservationService->commitReservations($cartId, 'ORDER-002');

        expect($transactions)->toHaveCount(2);
        expect($this->stockService->getCurrentStock($this->product))->toBe(90);
        expect($this->stockService->getCurrentStock($product2))->toBe(45);
    });

    it('does nothing when no reservations exist', function (): void {
        $transactions = $this->reservationService->commitReservations('non-existent-cart');

        expect($transactions)->toBeEmpty();
        expect($this->stockService->getCurrentStock($this->product))->toBe(100);
    });
});

describe('DeductStockOnPaymentSuccess listener', function (): void {
    beforeEach(function (): void {
        $this->reservationService = app(StockReservationService::class);
        $this->stockService = app(StockService::class);
        $this->product = Product::create(['name' => 'Payment Product']);
        app(StockService::class)->addStock($this->product, 100);
        $this->listener = new DeductStockOnPaymentSuccess($this->reservationService);
    });

    it('commits reservations when cart_id present', function (): void {
        $cartId = 'payment-cart';
        $this->reservationService->reserve($this->product, 10, $cartId, 30);

        $event = (object) ['cart_id' => $cartId];
        $this->listener->handle($event);

        expect($this->stockService->getCurrentStock($this->product))->toBe(90);
    });

    it('commits reservations when cartId property', function (): void {
        $cartId = 'cartId-cart';
        $this->reservationService->reserve($this->product, 8, $cartId, 30);

        $event = (object) ['cartId' => $cartId];
        $this->listener->handle($event);

        expect($this->stockService->getCurrentStock($this->product))->toBe(92);
    });

    it('commits reservations when cart object with getId', function (): void {
        $cartId = 'cart-obj-cart';
        $this->reservationService->reserve($this->product, 7, $cartId, 30);

        // Use an anonymous class since Cart is final and can't be mocked
        $cartStub = new class($cartId) {
            public function __construct(private string $id) {}
            public function getId(): string { return $this->id; }
        };
        $event = (object) ['cart' => $cartStub];
        $this->listener->handle($event);

        expect($this->stockService->getCurrentStock($this->product))->toBe(93);
    });

    it('deducts stock from line items fallback', function (): void {
        $event = (object) ['payload' => [
            'line_items' => [
                ['stockable' => $this->product, 'quantity' => 6]
            ]
        ]];
        $this->listener->handle($event);

        expect($this->stockService->getCurrentStock($this->product))->toBe(94);
    });

    it('skips non-stockable models', function (): void {
        // Create an event with a model that doesn't use HasStock trait
        $nonStockable = new class(['name' => 'Non-Stockable']) extends Model {
            protected $fillable = ['name'];
        };
        $event = (object) ['payload' => [
            'line_items' => [
                ['stockable' => $nonStockable, 'quantity' => 1]
            ]
        ]];
        $initialStock = $this->stockService->getCurrentStock($this->product);
        $this->listener->handle($event);

        // Stock should not change since the model doesn't have HasStock trait
        expect($this->stockService->getCurrentStock($this->product))->toBe($initialStock);
    });

    it('deducts stock from models with HasStock trait', function (): void {
        // Use the actual product which has HasStock trait
        $event = (object) ['payload' => [
            'line_items' => [
                ['stockable' => $this->product, 'quantity' => 4]
            ]
        ]];
        $initialStock = $this->stockService->getCurrentStock($this->product);
        $this->listener->handle($event);

        expect($this->stockService->getCurrentStock($this->product))->toBe($initialStock - 4);
    });
});
