<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\Fixtures\TestOwner;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\States\Returned;
use AIArmada\Orders\States\Shipped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\Cart\InMemoryStorage;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

describe('OrderService', function (): void {
    describe('Order Creation', function (): void {
        it('ignores caller-supplied owner fields and assigns current owner context', function (): void {
            config()->set('orders.owner.enabled', true);
            config()->set('orders.owner.auto_assign_on_create', true);

            Schema::dropIfExists('test_owners');
            Schema::create('test_owners', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });

            $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

            app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
            {
                public function __construct(
                    private readonly ?Model $owner,
                ) {}

                public function resolve(): ?Model
                {
                    return $this->owner;
                }
            });

            $service = app(OrderService::class);

            $order = $service->createOrder([
                'order_number' => 'ORD-SVC-OWNER-' . uniqid(),
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
                'subtotal' => 10000,
                'grand_total' => 10000,
                'currency' => 'MYR',
            ], [
                [
                    'name' => 'Product 1',
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'tax_amount' => 0,
                ],
            ]);

            expect($order->owner_type)->toBe($ownerA->getMorphClass())
                ->and($order->owner_id)->toBe($ownerA->getKey());
        });

        it('can create an order with items and addresses', function (): void {
            $service = app(OrderService::class);

            $orderData = [
                'order_number' => 'ORD-SVC1-' . uniqid(),
                'subtotal' => 10000,
                'shipping_total' => 500,
                'tax_total' => 600,
                'grand_total' => 11100,
                'currency' => 'MYR',
                'notes' => 'Test order',
            ];

            $items = [
                [
                    'name' => 'Product 1',
                    'quantity' => 2,
                    'unit_price' => 2500,
                    'tax_amount' => 300,
                ],
                [
                    'name' => 'Product 2',
                    'quantity' => 1,
                    'unit_price' => 5000,
                    'tax_amount' => 300,
                ],
            ];

            $billingAddress = [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'line1' => '123 Billing St',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
            ];

            $shippingAddress = [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'line1' => '456 Shipping St',
                'city' => 'Penang',
                'postcode' => '10000',
                'country' => 'MY',
            ];

            $order = OwnerContext::withOwner(null, function () use ($service, $orderData, $items, $billingAddress, $shippingAddress): Order {
                return $service->createOrder($orderData, $items, $billingAddress, $shippingAddress);
            });

            expect($order)->toBeInstanceOf(Order::class)
                ->and($order->order_number)->toBe($orderData['order_number'])
                ->and($order->status)->toBeInstanceOf(PendingPayment::class)
                ->and($order->items)->toHaveCount(2)
                ->and($order->primaryAddress('billing'))->not->toBeNull()
                ->and($order->primaryAddress('shipping'))->not->toBeNull()
                ->and(data_get($order->primaryAddress('billing')?->metadata, Order::ADDRESS_CONTACT_METADATA_KEY . '.first_name'))->toBe('John')
                ->and(data_get($order->primaryAddress('shipping')?->metadata, Order::ADDRESS_CONTACT_METADATA_KEY . '.first_name'))->toBe('Jane');
        });

        it('can add items to an order', function (): void {
            $service = app(OrderService::class);
            $order = Order::create([
                'order_number' => 'ORD-SVC2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 0,
                'grand_total' => 0,
            ]);

            $itemData = [
                'name' => 'Test Product',
                'quantity' => 3,
                'unit_price' => 2000,
                'tax_amount' => 180,
                'sku' => 'TEST-001',
            ];

            $item = $service->addItem($order, $itemData);

            expect($item->name)->toBe('Test Product')
                ->and($item->quantity)->toBe(3)
                ->and($item->unit_price)->toBe(2000)
                ->and($item->total)->toBe(6180); // (3*2000) + 180
        });

        it('can add addresses to an order', function (): void {
            $service = app(OrderService::class);
            $order = Order::create([
                'order_number' => 'ORD-SVC3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 0,
                'grand_total' => 0,
            ]);

            $addressData = [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'line1' => '789 Test Ave',
                'city' => 'JB',
                'postcode' => '80000',
                'country' => 'MY',
                'phone' => '0123456789',
            ];
            $addressSnapshot = $addressData;

            $service->addAddress($order, $addressData, 'billing');

            $order->refresh();

            expect($order->primaryAddress('billing'))->not->toBeNull()
                ->and(data_get($order->primaryAddress('billing')?->metadata, Order::ADDRESS_CONTACT_METADATA_KEY . '.first_name'))->toBe('John')
                ->and(data_get($order->primaryAddress('billing')?->metadata, Order::ADDRESS_CONTACT_METADATA_KEY . '.phone'))->toBe('0123456789')
                ->and($addressData)->toBe($addressSnapshot);
        });
    });

    describe('Order Operations', function (): void {
        it('can recalculate order totals', function (): void {
            $service = app(OrderService::class);
            $order = Order::create([
                'order_number' => 'ORD-SVC4-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 0,
                'grand_total' => 0,
            ]);

            // Add items
            $service->addItem($order, [
                'name' => 'Item 1',
                'quantity' => 1,
                'unit_price' => 5000,
                'tax_amount' => 300,
            ]);

            $service->addItem($order, [
                'name' => 'Item 2',
                'quantity' => 2,
                'unit_price' => 2500,
                'tax_amount' => 150,
            ]);

            $order->shipping_total = 500;
            $order->discount_total = 200;
            $order->save();

            $updatedOrder = $service->recalculateTotals($order);

            expect($updatedOrder->subtotal)->toBe(10450) // 5300 + 5150 (item totals)
                ->and($updatedOrder->tax_total)->toBe(450) // 300 + 150 (tax_amounts)
                ->and($updatedOrder->grand_total)->toBe(10750); // 10450 + 500 - 200
        });

        it('maps the typed cart contract through a cart manager', function (): void {
            $service = app(OrderService::class);

            $cart = new Cart(new InMemoryStorage, 'cart_123');
            $cart->setMetadata('currency', 'MYR');
            $cart->add('prod_1', 'Test Product', 8000, 2, [
                'sku' => 'TEST-001',
                'options' => ['color' => 'red'],
                'metadata' => ['custom' => 'data'],
            ]);
            $cart->addDiscount('promotion', '2000');
            $cart->addShipping('shipping', 1000);
            $cart->addTax('tax', '1200');

            $cartManager = Mockery::mock(CartManagerInterface::class);
            $cartManager->expects('getCurrentCart')->once()->andReturn($cart);

            // Create a simple test model
            $customer = new class extends Model
            {
                protected $table = 'users';

                public function getKey()
                {
                    return 1;
                }

                public function getMorphClass()
                {
                    return 'User';
                }
            };

            $billingAddress = [
                'first_name' => 'John',
                'last_name' => 'Cart',
                'line1' => '123 Cart Street',
                'city' => 'Cart City',
                'postcode' => '12345',
                'country' => 'MY',
            ];

            $order = OwnerContext::withOwner(null, function () use ($service, $cartManager, $customer, $billingAddress): Order {
                return $service->createFromCart(
                    $cartManager,
                    $customer,
                    $billingAddress,
                    sessionId: 'session-cart-123',
                );
            });

            expect($order)
                ->toBeInstanceOf(Order::class)
                ->and($order->subtotal)->toBe($cart->getRawSubtotal())
                ->and($order->discount_total)->toBe(
                    $cart->getItems()->getTotalDiscount()
                    + $cart->getConditionsByType('discount')->getTotalDiscount($cart->getRawSubtotal()),
                )
                ->and($order->shipping_total)->toBe(
                    $cart->getConditionsByType('shipping')->getTotalCharges($cart->getRawSubtotal()),
                )
                ->and($order->tax_total)->toBe(
                    $cart->getConditionsByType('tax')->getTotalCharges($cart->getRawSubtotal()),
                )
                ->and($order->grand_total)->toBe($cart->getRawTotal())
                ->and($order->metadata['session_id'])->toBe('session-cart-123')
                ->and($order->items)->toHaveCount(1)
                ->and($order->items->first()->purchasable_id)->toBe('prod_1')
                ->and($order->items->first()->sku)->toBe('TEST-001')
                ->and($order->primaryAddress('billing'))->not->toBeNull();
        });

        it('rejects the former duck-typed cart payload', function (): void {
            $service = app(OrderService::class);
            $customer = new class extends Model {};

            expect(fn () => $service->createFromCart((object) [], $customer))
                ->toThrow(TypeError::class);
        });

        it('fails fast for createOrder when owner mode is enabled and no owner context exists', function (): void {
            config()->set('orders.owner.enabled', true);

            app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
            {
                public function resolve(): ?Model
                {
                    return null;
                }
            });

            $service = app(OrderService::class);

            $orderData = [
                'order_number' => 'ORD-SVC-NOCTX-' . Str::upper(Str::random(8)),
                'subtotal' => 10000,
                'grand_total' => 10000,
                'currency' => 'MYR',
            ];

            $items = [
                [
                    'name' => 'No Context Product',
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'tax_amount' => 0,
                ],
            ];

            expect(fn () => $service->createOrder($orderData, $items))
                ->toThrow(RuntimeException::class, 'Owner context is required');
        });

        it('fails fast for confirmPayment when owner mode is enabled and no owner context exists', function (): void {
            config()->set('orders.owner.enabled', false);

            Schema::dropIfExists('test_owners');
            Schema::create('test_owners', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });

            $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

            $order = new Order([
                'order_number' => 'ORD-SVC-MUT-NOCTX-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);
            $order->assignOwner($ownerA);
            $order->save();

            config()->set('orders.owner.enabled', true);

            app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
            {
                public function resolve(): ?Model
                {
                    return null;
                }
            });

            $service = app(OrderService::class);

            expect(fn () => $service->confirmPayment($order, 'txn_mut_ctx_1', 'stripe', 10000))
                ->toThrow(RuntimeException::class, 'matching owner context is required');
        });

        it('fails fast for cancel when owner mode is enabled and owner context mismatches', function (): void {
            config()->set('orders.owner.enabled', false);

            Schema::dropIfExists('test_owners');
            Schema::create('test_owners', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });

            $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

            $order = new Order([
                'order_number' => 'ORD-SVC-MUT-XOWNER-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);
            $order->assignOwner($ownerA);
            $order->save();

            config()->set('orders.owner.enabled', true);

            app()->instance(OwnerResolverInterface::class, new class($ownerB) implements OwnerResolverInterface
            {
                public function __construct(
                    private readonly ?Model $owner,
                ) {}

                public function resolve(): ?Model
                {
                    return $this->owner;
                }
            });

            $service = app(OrderService::class);

            expect(fn () => $service->cancel($order, 'Cross owner test'))
                ->toThrow(RuntimeException::class, 'Cross-owner mutation blocked');
        });
    });

    describe('Order Operations', function (): void {
        it('can cancel an order', function (): void {
            $service = app(OrderService::class);
            $order = Order::create([
                'order_number' => 'ORD-CANCEL-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->cancel($order, 'Customer request', 'admin@example.com');

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Canceled::class);
            expect($order->cancellation_reason)->toBe('Customer request');
            expect($order->orderNotes)->toHaveCount(1);
        });

        it('can confirm payment for an order', function (): void {
            $service = app(OrderService::class);
            $order = Order::create([
                'order_number' => 'ORD-PAY-CONFIRM-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->confirmPayment($order, 'txn_123', 'stripe', 10000);

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Processing::class);
            expect($order->paid_at)->not->toBeNull();
            expect($order->payments)->toHaveCount(1);
        });

        it('can ship an order', function (): void {
            $service = app(OrderService::class);
            $order = Order::create([
                'order_number' => 'ORD-SHIP-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->ship($order, 'J&T', 'JT123456789', 'ship_123');

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Shipped::class);
            expect($order->shipped_at)->not->toBeNull();
        });

        it('can confirm delivery', function (): void {
            $service = app(OrderService::class);
            $order = Order::create([
                'order_number' => 'ORD-DELIVER-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->confirmDelivery($order, ['delivered_by' => 'customer']);

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Delivered::class);
            expect($order->delivered_at)->not->toBeNull();
        });

        it('can process refund', function (): void {
            $service = app(OrderService::class);
            $order = Order::create([
                'order_number' => 'ORD-REFUND-' . uniqid(),
                'status' => Returned::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->processRefund($order, 5000, 'ref_txn_123', 'Customer return');

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Returned::class);
            expect($order->refunds)->toHaveCount(1);
            expect($order->refunds->first()->amount)->toBe(5000);

            $result = $service->processRefund($order, 5000, 'ref_txn_124', 'Customer return');

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Refunded::class);
            expect($order->refunds)->toHaveCount(2);
        });
    });
});
