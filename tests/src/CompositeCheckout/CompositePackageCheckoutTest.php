<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Completed;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Orders\Contracts\FulfillmentHandler;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\CarrierOperationResult;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Support\Collection;
use Mockery\CompositeExpectation;

function bindCompositeCheckoutPayment(int $amount = 8800, bool $redirect = false): CompositeExpectation
{
    $processor = mock(PaymentProcessorInterface::class);
    $processor->shouldReceive('getIdentifier')->andReturn('chip');
    $processor->shouldReceive('isAvailable')->andReturnTrue();
    $completed = new PaymentResult(
        status: PaymentStatus::Completed,
        paymentId: 'pay_composite_checkout',
        transactionId: 'txn_composite_checkout',
        amount: $amount,
        currency: 'USD',
        provider: 'chip',
    );
    $expectation = $processor->shouldReceive('createPayment')->andReturn(
        $redirect ? PaymentResult::pending('pay_composite_checkout', 'https://gateway.example.test/pay', 'chip') : $completed,
    );
    if ($redirect) {
        $processor->shouldReceive('handleCallback')->andReturn($completed);
    }

    $resolver = mock(PaymentGatewayResolverInterface::class);
    $resolver->shouldReceive('hasGateway')->andReturnTrue();
    $resolver->shouldReceive('resolve')->andReturn($processor);
    $resolver->shouldReceive('getDefaultGateway')->andReturn('chip');

    app()->instance(PaymentGatewayResolverInterface::class, $resolver);
    config()->set('checkout.payment.default_gateway', 'chip');

    return $expectation;
}

/**
 * Global catalog rows so the checkout's explicit-global owner context resolves
 * components the same way it resolves an ordinary global product.
 *
 * @return array{0: Product, 1: Product, 2: InventoryLocation}
 */
function compositeCheckoutCatalog(int $stock = 10): array
{
    static $sequence = 0;
    $sequence++;

    return OwnerContext::withOwner(null, static function () use ($sequence, $stock): array {
        $mouse = Product::query()->create([
            'name' => 'Checkout Mouse ' . $sequence,
            'slug' => 'checkout-mouse-' . $sequence,
            'sku' => 'MOUSE-' . $sequence,
            'type' => ProductType::Simple,
            'status' => ProductStatus::Active,
            'price' => 800,
            'currency' => 'USD',
            'requires_shipping' => true,
            'tracks_inventory' => true,
            'is_taxable' => true,
            'weight' => 0.25,
            'weight_unit' => 'kg',
            'length' => 12,
            'width' => 8,
            'height' => 4,
            'dimension_unit' => 'cm',
        ]);

        $package = app(CreateCompositeProduct::class)->handle([
            'name' => 'Two Mouse Package',
            'slug' => 'two-mouse-package-' . $sequence,
            'sku' => 'PACKAGE-' . $sequence,
            'price' => 4000,
            'currency' => 'USD',
            'status' => ProductStatus::Active,
        ], [
            ['component_product_id' => (string) $mouse->getKey(), 'quantity' => 2],
        ]);

        $location = InventoryLocation::query()->firstOrCreate(
            ['code' => 'COMPOSITE-CHECKOUT-WH'],
            ['name' => 'Composite Checkout WH', 'priority' => 100],
        );

        app(InventoryService::class)->receive($mouse, $location->id, $stock);

        return [$mouse, $package, $location];
    });
}

/**
 * Two packages (two mice each) plus one standalone mouse: five mouse units on
 * two commercial lines.
 */
function compositeCheckoutCart(Product $package, Product $mouse): string
{
    return OwnerContext::withOwner(null, static function () use ($package, $mouse): string {
        CartFacade::setIdentifier('composite-paid-' . uniqid());
        CartFacade::addBuyable($package, 2);
        CartFacade::addBuyable($mouse, 1);

        return (string) CartFacade::getId();
    });
}

beforeEach(function (): void {
    config()->set('checkout.defaults.currency', 'USD');
    config()->set('checkout.integrations.shipping.require_selection', false);
    config()->set('checkout.steps.enabled.dispatch_documents', false);
    config()->set('products.features.owner.auto_assign_on_create', false);
    config()->set('products.features.owner.include_global', true);
    config()->set('orders.owner.enabled', false);
    config()->set('inventory.owner.enabled', false);
    config()->set('pricing.features.owner.enabled', false);
});

it('reserves and commits five component units once for a paid composite checkout', function (): void {
    bindCompositeCheckoutPayment(9800);

    [$mouse, $package, $location] = compositeCheckoutCatalog();
    $cartId = compositeCheckoutCart($package, $mouse);

    $checkout = app(CheckoutServiceInterface::class);
    $session = $checkout->startCheckout($cartId);

    $result = $checkout->processCheckout($session);

    expect($result->success)->toBeTrue();

    $session = CheckoutSession::query()->where('cart_id', $cartId)->firstOrFail();
    $order = Order::query()->findOrFail($session->order_id);

    expect($session->status->is(Completed::class))->toBeTrue()
        ->and($order->items()->count())->toBe(2)
        ->and($order->subtotal)->toBe(8800)
        ->and($order->shipping_total)->toBe(1000)
        ->and($order->grand_total)->toBe(9800);

    $packageLine = $order->items()->where('purchasable_id', $package->getKey())->firstOrFail();
    $standaloneLine = $order->items()->where('purchasable_id', $mouse->getKey())->firstOrFail();

    expect($packageLine->quantity)->toBe(2)
        ->and($packageLine->unit_price)->toBe(4000)
        ->and($standaloneLine->quantity)->toBe(1)
        ->and($standaloneLine->unit_price)->toBe(800)
        ->and($packageLine->metadata['components'] ?? [])->toHaveCount(1)
        ->and($packageLine->metadata['components'][0]['quantity'])->toBe(2)
        ->and($standaloneLine->metadata['components'] ?? null)->toBeNull();

    $inventory = app(InventoryService::class);

    expect($inventory->getLevel($mouse, $location->id)?->fresh()->quantity_on_hand)->toBe(5)
        ->and($inventory->getLevel($package, $location->id))->toBeNull()
        ->and(InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->where('inventoryable_type', $mouse->getMorphClass())
            ->where('inventoryable_id', $mouse->getKey())
            ->count())->toBe(1)
        ->and(InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->where('inventoryable_type', $mouse->getMorphClass())
            ->where('inventoryable_id', $mouse->getKey())
            ->sum('quantity'))->toBe(5)
        ->and(InventoryMovement::query()
            ->where('inventoryable_id', $package->getKey())
            ->where('type', MovementType::Shipment->value)
            ->count())->toBe(0);

    $reservation = InventoryReservation::query()->where('reference', $session->getKey())->firstOrFail();

    expect($reservation->status)->toBe(InventoryReservation::STATE_COMMITTED)
        ->and($reservation->order_id)->toBe($order->getKey())
        ->and(InventoryAllocation::query()->where('cart_id', $session->getKey())->count())->toBe(0);
});

it('ships the package and the standalone line as five units of the same stock identity', function (): void {
    bindCompositeCheckoutPayment(9800);

    [$mouse, $package] = compositeCheckoutCatalog();
    $cartId = compositeCheckoutCart($package, $mouse);

    $checkout = app(CheckoutServiceInterface::class);
    $checkoutResult = $checkout->processCheckout($checkout->startCheckout($cartId));
    expect($checkoutResult->success)->toBeTrue();

    $order = Order::query()->findOrFail(
        CheckoutSession::query()->where('cart_id', $cartId)->value('order_id'),
    );

    app(OrderService::class)->addAddress($order, [
        'name' => 'Checkout Customer',
        'line1' => '456 Shipping Road',
        'city' => 'Johor Bahru',
        'state' => 'Johor',
        'postcode' => '80000',
        'country' => 'MY',
    ], 'shipping');

    $driver = mock(ShippingDriverInterface::class);
    $driver->shouldReceive('servicesDestination')->once()->andReturnTrue();
    $driver->shouldReceive('getRates')->once()->andReturnUsing(
        static function (AddressData $origin, AddressData $destination, array $packages): Collection {
            expect(array_column($packages, 'weight'))->toBe([1000, 250])
                ->and(array_column($packages, 'length'))->toBe([12, 12]);

            return collect([new RateQuoteData('manual', 'standard', 1000, 'USD', 3)]);
        },
    );
    $driver->shouldReceive('createShipment')->once()->andReturn(CarrierOperationResult::succeeded('COMPOSITE-TRACK'));
    $manager = mock(ShippingManager::class);
    $manager->shouldReceive('getAvailableDrivers')->once()->andReturn(['manual']);
    $manager->shouldReceive('driver')->with('manual')->andReturn($driver);
    app()->instance(ShippingManager::class, $manager);

    $handler = app(FulfillmentHandler::class);
    expect($handler->getRates($order))->toHaveCount(1);

    $result = $handler->createShipment($order, ['carrier' => 'manual']);
    expect($result['error'])->toBeNull();

    $shipment = Shipment::query()->findOrFail($result['shipment_id']);
    $items = $shipment->items()->get();
    $packageItems = $items->whereNotNull('package_unit_ordinal')->sortBy('package_unit_ordinal')->values();
    $standaloneItems = $items->whereNull('package_unit_ordinal');

    expect($shipment->total_weight)->toBe(1250)
        ->and($items->sum('quantity'))->toBe(5)
        ->and($items->pluck('shippable_item_id')->unique()->values()->all())->toBe([(string) $mouse->getKey()])
        ->and($packageItems->pluck('package_unit_ordinal')->all())->toBe([1, 2])
        ->and($packageItems->pluck('quantity')->all())->toBe([2, 2])
        ->and($standaloneItems->pluck('quantity')->values()->all())->toBe([1]);
});

it('rolls the reservation back and creates no order when component stock runs out before payment', function (): void {
    bindCompositeCheckoutPayment();

    [$mouse, $package, $location] = compositeCheckoutCatalog();
    $cartId = compositeCheckoutCart($package, $mouse);

    app(InventoryService::class)->ship($mouse, $location->id, 6, 'concurrent sale');

    $checkout = app(CheckoutServiceInterface::class);
    $result = $checkout->processCheckout($checkout->startCheckout($cartId));

    expect($result->success)->toBeFalse()
        ->and(Order::query()->count())->toBe(0)
        ->and(InventoryLevel::query()
            ->where('inventoryable_id', $mouse->getKey())
            ->value('quantity_on_hand'))->toBe(4)
        ->and(InventoryAllocation::query()->where('cart_id', $cartId)->count())->toBe(0);
});

it('refuses to charge when the package price changed after checkout started', function (): void {
    $payment = bindCompositeCheckoutPayment();

    [$mouse, $package] = compositeCheckoutCatalog();
    $cartId = compositeCheckoutCart($package, $mouse);

    $checkout = app(CheckoutServiceInterface::class);
    $session = $checkout->startCheckout($cartId);

    $package->forceFill(['price' => 3500]);

    OwnerContext::withOwner(null, static fn () => $package->save());

    $result = $checkout->processCheckout($session);

    expect($result->success)->toBeFalse()
        ->and($result->errors['cart'] ?? null)->toContain('price changed')
        ->and(Order::query()->count())->toBe(0);

    $payment->never();
});

it('completes a redirected composite payment once across repeated callbacks', function (): void {
    bindCompositeCheckoutPayment(9800, redirect: true)->once();

    [$mouse, $package, $location] = compositeCheckoutCatalog();
    $cartId = compositeCheckoutCart($package, $mouse);
    $checkout = app(CheckoutServiceInterface::class);
    $session = $checkout->startCheckout($cartId);

    $checkout->processCheckout($session);
    $session->refresh();

    expect($session->payment_id)->toBe('pay_composite_checkout')
        ->and($session->order_id)->toBeNull();

    OwnerContext::withOwner(null, static fn () => $package->update(['price' => 3500]));

    OwnerContext::withOwner(null, static function () use ($cartId): void {
        CartFacade::getById($cartId)->add('new-cart-line', 'Next purchase', 100);
    });

    $payload = ['id' => 'pay_composite_checkout', 'status' => 'paid'];
    $first = $checkout->handlePaymentCallback($session, 'success', $payload);
    $second = $checkout->handlePaymentCallback($session, 'success', $payload);

    expect($first->success)->toBeTrue()
        ->and($second->success)->toBeTrue()
        ->and(Order::query()->count())->toBe(1)
        ->and($session->fresh()->order_id)->toBe($first->orderId)
        ->and(app(InventoryService::class)->getLevel($mouse, $location->id)?->quantity_on_hand)->toBe(5)
        ->and(OwnerContext::withOwner(null, static fn () => CartFacade::getById($cartId)?->has('new-cart-line')))->toBeTrue()
        ->and(InventoryMovement::query()->where('type', MovementType::Shipment->value)->count())->toBe(1)
        ->and(InventoryReservation::query()->where('reference', $session->id)->value('status'))
        ->toBe(InventoryReservation::STATE_COMMITTED);
});

it('charges the reviewed package price list amount once', function (): void {
    bindCompositeCheckoutPayment(8800)->once();

    [$mouse, $package] = compositeCheckoutCatalog();
    OwnerContext::withOwner(null, static function () use ($package): void {
        $list = PriceList::query()->create([
            'name' => 'Package Prices',
            'slug' => 'package-prices',
            'currency' => 'USD',
            'is_active' => true,
            'is_default' => true,
        ]);
        Price::query()->create([
            'price_list_id' => $list->getKey(),
            'priceable_type' => $package->getMorphClass(),
            'priceable_id' => $package->getKey(),
            'amount' => 3500,
            'currency' => 'USD',
            'min_quantity' => 1,
        ]);
    });

    $cartId = compositeCheckoutCart($package, $mouse);
    $checkout = app(CheckoutServiceInterface::class);
    $session = $checkout->startCheckout($cartId);
    expect(array_column($session->cart_snapshot['items'], 'price'))->toContain(3500);

    $result = $checkout->processCheckout($session);
    self::assertTrue($result->success, json_encode([
        $result->message, $result->errors, $session->fresh()->pricing_data,
    ], JSON_THROW_ON_ERROR));
    $order = Order::query()->findOrFail($result->orderId);
    $line = $order->items()->where('purchasable_id', $package->getKey())->firstOrFail();

    expect($order->grand_total)->toBe(8800)
        ->and($line->unit_price)->toBe(4000)
        ->and($line->discount_amount)->toBe(1000)
        ->and($line->total)->toBe(7000);
});
