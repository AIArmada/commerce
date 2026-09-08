<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\States\Active as AffiliateActive;
use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ApplyDiscountsStep;
use AIArmada\Checkout\Steps\CreateOrderStep;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderProcessingStarted;
use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $paymentData
 */
function createCheckoutAmountReconciliationSession(array $paymentData, int $grandTotal = 1000): CheckoutSession
{
    $session = CheckoutSession::create([
        'cart_id' => 'cart-amount-reconciliation-' . Str::random(8),
        'cart_snapshot' => ['items' => []],
        'payment_data' => array_merge([
            'type' => 'card',
            'status' => PaymentStatus::Completed->value,
            'transaction_id' => 'tx-amount-reconciliation',
            'gateway' => 'chip',
            'currency' => 'MYR',
        ], $paymentData),
        'selected_payment_gateway' => 'chip',
        'payment_id' => 'payment-amount-reconciliation',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
        'currency' => 'MYR',
    ]);

    return $session->transitionStatus(Processing::class);
}

it('prefers the typed live cart bridge and never calls the snapshot builder path', function (): void {
    config()->set('checkout.create_order.confirm_payment', false);

    $customer = new Customer;
    $customer->forceFill(['id' => 'customer-live-cart']);

    $cart = new BaseCart(
        new InMemoryStorage,
        'typed-live-cart',
        events: null,
        eventsEnabled: false,
    );
    $cart->add('typed-live-item', 'Typed Live Item', 1250, 2, ['sku' => 'TYPED-LIVE-001']);

    $session = CheckoutSession::create([
        'cart_id' => 'typed-live-cart-id',
        'cart_snapshot' => [
            'items' => [
                ['name' => 'Snapshot Item', 'quantity' => 1, 'price' => 1],
            ],
        ],
        'billing_data' => ['line1' => 'Billing Street'],
        'shipping_data' => ['line1' => 'Shipping Street'],
        'payment_data' => ['type' => 'free_order'],
        'subtotal' => 1,
        'grand_total' => 1,
        'currency' => 'MYR',
    ]);
    $session->setRelation('customer', $customer);
    $session->transitionStatus(Processing::class);

    $cartManager = mock(CartManagerInterface::class);
    $cartManager->shouldReceive('getById')
        ->once()
        ->with($session->cart_id)
        ->andReturn($cart);
    app()->instance(CartManagerInterface::class, $cartManager);

    $order = new Order;
    $order->forceFill([
        'id' => (string) Str::uuid(),
        'order_number' => 'ORD-TYPED-LIVE-CART',
    ]);

    $orderService = mock(OrderServiceInterface::class);
    $orderService->shouldReceive('createFromCart')
        ->once()
        ->withArgs(function (...$arguments) use ($cart, $customer, $session): bool {
            return ($arguments[0] ?? null) === $cart
                && ($arguments[1] ?? null) === $customer
                && ($arguments[2] ?? null) === ['line1' => 'Billing Street']
                && ($arguments[3] ?? null) === ['line1' => 'Shipping Street']
                && ($arguments[4] ?? null) === 'checkout'
                && ($arguments[5] ?? null) === $session->getKey()
                && ($arguments[6] ?? null) === $session->id;
        })
        ->andReturn($order);
    $orderService->shouldReceive('createOrder')->never();
    app()->instance(OrderServiceInterface::class, $orderService);

    $result = app(CreateOrderStep::class)->handle($session);

    expect($result->isSuccessful())->toBeTrue()
        ->and($session->fresh()?->order_id)->toBe($order->id);
});

it('falls back to the snapshot builder only when the live cart is gone', function (): void {
    config()->set('checkout.create_order.confirm_payment', false);

    $customer = new Customer;
    $customer->forceFill(['id' => 'customer-missing-cart']);

    $session = CheckoutSession::create([
        'cart_id' => 'missing-live-cart-id',
        'cart_snapshot' => [
            'items' => [
                ['name' => 'Snapshot Item', 'quantity' => 1, 'price' => 900],
            ],
        ],
        'payment_data' => ['type' => 'free_order'],
        'subtotal' => 900,
        'grand_total' => 900,
        'currency' => 'MYR',
    ]);
    $session->setRelation('customer', $customer);
    $session->transitionStatus(Processing::class);

    $cartManager = mock(CartManagerInterface::class);
    $cartManager->shouldReceive('getById')
        ->once()
        ->with($session->cart_id)
        ->andReturnNull();
    app()->instance(CartManagerInterface::class, $cartManager);

    $order = new Order;
    $order->forceFill([
        'id' => (string) Str::uuid(),
        'order_number' => 'ORD-SNAPSHOT-FALLBACK',
    ]);

    $capturedOrderData = null;
    $orderService = mock(OrderServiceInterface::class);
    $orderService->shouldReceive('createOrder')
        ->once()
        ->andReturnUsing(function (array $orderData) use (&$capturedOrderData, $order): Order {
            $capturedOrderData = $orderData;

            return $order;
        });
    $orderService->shouldReceive('createFromCart')->never();
    app()->instance(OrderServiceInterface::class, $orderService);

    $result = app(CreateOrderStep::class)->handle($session);

    expect($result->isSuccessful())->toBeTrue()
        ->and(data_get($capturedOrderData, 'subtotal'))->toBe(900)
        ->and(data_get($capturedOrderData, 'grand_total'))->toBe(900)
        ->and($session->fresh()?->order_id)->toBe($order->id);
});

it('refreshes voucher-driven affiliate overrides before creating order metadata', function (): void {
    config()->set('checkout.integrations.vouchers.enabled', true);
    config()->set('checkout.create_order.confirm_payment', false);

    Affiliate::create([
        'code' => 'AFFE2E3',
        'name' => 'Affiliate Three',
        'status' => AffiliateActive::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'MYR',
        'default_voucher_code' => 'AFFE2E3SAVE5',
    ]);

    Voucher::query()->create([
        'code' => 'AFFE2E3SAVE5',
        'name' => 'Affiliate Voucher',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    Cart::setInstance('public-checkout');
    Cart::setIdentifier('affiliate-voucher-override');
    Cart::clear();
    Cart::clearConditions();
    Cart::clearMetadata();
    Cart::clearVouchers();
    Cart::add('sku-affiliate-voucher', 'Affiliate Voucher Product', 9700, 1, ['sku' => 'AFF-VOUCHER-001']);
    Cart::setMetadataBatch([
        'voucher_codes' => ['AFFE2E3SAVE5'],
        'promo_code' => 'AFFE2E3SAVE5',
    ]);

    $cartId = Cart::getId();

    expect($cartId)->not->toBeNull();

    $session = app(CheckoutServiceInterface::class)->startCheckout($cartId);

    expect(data_get($session->cart_snapshot, 'metadata.affiliate'))->toBeNull();

    $session->update([
        'subtotal' => 9700,
        'grand_total' => 9700,
        'currency' => 'MYR',
        'selected_payment_gateway' => 'chip',
        'payment_data' => ['type' => 'free_order'],
    ]);

    $cartManager = app(CartManagerInterface::class);
    $liveCart = $cartManager->getById($cartId);

    expect($liveCart)->not->toBeNull();

    $applyDiscountsStep = new ApplyDiscountsStep(vouchersAdapter: new VouchersAdapter, cartManager: $cartManager);
    $applyDiscountsResult = $applyDiscountsStep->handle($session);

    expect($applyDiscountsResult->isSuccessful())->toBeTrue();

    $session->refresh();

    $attribution = AffiliateAttribution::query()
        ->where('cart_identifier', Cart::getIdentifier())
        ->where('cart_instance', Cart::instance())
        ->latest('last_seen_at')
        ->first();

    expect($attribution)->not->toBeNull()
        ->and($attribution?->affiliate_code)->toBe('AFFE2E3')
        ->and($attribution?->voucher_code)->toBe('AFFE2E3SAVE5')
        ->and(data_get($session->cart_snapshot, 'metadata.affiliate'))->toBeNull()
        ->and(data_get($session->cart_snapshot, 'metadata.promo_code'))->toBe('AFFE2E3SAVE5')
        ->and($session->discount_total)->toBe(500)
        ->and($session->grand_total)->toBe(9200);

    $snapshotCartManager = mock(CartManagerInterface::class);
    $snapshotCartManager->shouldReceive('getById')
        ->once()
        ->with($session->cart_id)
        ->andReturnNull();
    app()->instance(CartManagerInterface::class, $snapshotCartManager);

    $capturedOrderData = null;

    $orderService = mock(OrderServiceInterface::class);
    $orderService->shouldReceive('createOrder')
        ->once()
        ->andReturnUsing(function (array $orderData) use (&$capturedOrderData): Order {
            $capturedOrderData = $orderData;

            $order = new Order;
            $order->id = (string) Str::uuid();
            $order->order_number = 'ORD-AFFILIATE-VOUCHER-OVERRIDE';

            return $order;
        });

    app()->instance(OrderServiceInterface::class, $orderService);

    $createOrderResult = app(CreateOrderStep::class)->handle($session);

    expect($createOrderResult->isSuccessful())->toBeTrue()
        ->and(data_get($capturedOrderData, 'metadata.affiliate_code'))->toBeNull()
        ->and(data_get($capturedOrderData, 'metadata.affiliate_id'))->toBeNull()
        ->and(data_get($capturedOrderData, 'metadata.voucher_codes.0'))->toBe('AFFE2E3SAVE5')
        ->and(data_get($capturedOrderData, 'metadata.promo_code'))->toBe('AFFE2E3SAVE5');
});

it('confirms payment when the reported amount exactly matches the checkout total', function (): void {
    config()->set('checkout.create_order.confirm_payment', true);

    $order = new Order;
    $order->forceFill([
        'id' => (string) Str::uuid(),
        'order_number' => 'ORD-AMOUNT-MATCH',
    ]);

    $orderService = mock(OrderServiceInterface::class);
    $orderService->shouldReceive('createOrder')->once()->andReturn($order);
    $orderService->shouldReceive('confirmPayment')
        ->once()
        ->withArgs(fn (...$arguments): bool => ($arguments[3] ?? null) === 1000)
        ->andReturn($order);
    app()->instance(OrderServiceInterface::class, $orderService);

    $session = createCheckoutAmountReconciliationSession(['amount' => 1000]);
    $result = app(CreateOrderStep::class)->handle($session);

    expect($result->isSuccessful())->toBeTrue()
        ->and($session->fresh()?->order_id)->toBe($order->id);
});

it('blocks payment confirmation when the reported amount mismatches the checkout total', function (): void {
    config()->set('checkout.create_order.confirm_payment', true);
    Event::fake([OrderPaid::class, OrderProcessingStarted::class]);
    Log::spy();

    $order = new Order;
    $order->forceFill([
        'id' => (string) Str::uuid(),
        'order_number' => 'ORD-AMOUNT-MISMATCH',
    ]);

    $orderService = mock(OrderServiceInterface::class);
    $orderService->shouldReceive('createOrder')->once()->andReturn($order);
    $orderService->shouldReceive('confirmPayment')->never();
    app()->instance(OrderServiceInterface::class, $orderService);

    $session = createCheckoutAmountReconciliationSession(['amount' => 999]);
    $result = app(CreateOrderStep::class)->handle($session);
    $freshSession = $session->fresh();

    expect($result->isSuccessful())->toBeFalse()
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.status'))->toBe('mismatch')
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.expected_amount'))->toBe(1000)
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.received_amount'))->toBe(999)
        ->and($freshSession?->error_message)->toBe('Payment amount does not match the checkout total.');

    Event::assertNotDispatched(OrderPaid::class);
    Event::assertNotDispatched(OrderProcessingStarted::class);
    Log::shouldHaveReceived('warning')->once();
});

it('blocks payment confirmation when the reported currency mismatches the checkout currency', function (): void {
    config()->set('checkout.create_order.confirm_payment', true);
    Event::fake([OrderPaid::class, OrderProcessingStarted::class]);
    Log::spy();

    $order = new Order;
    $order->forceFill([
        'id' => (string) Str::uuid(),
        'order_number' => 'ORD-CURRENCY-MISMATCH',
    ]);

    $orderService = mock(OrderServiceInterface::class);
    $orderService->shouldReceive('createOrder')->once()->andReturn($order);
    $orderService->shouldReceive('confirmPayment')->never();
    app()->instance(OrderServiceInterface::class, $orderService);

    $session = createCheckoutAmountReconciliationSession([
        'amount' => 1000,
        'currency' => 'USD',
    ]);
    $result = app(CreateOrderStep::class)->handle($session);
    $freshSession = $session->fresh();

    expect($result->isSuccessful())->toBeFalse()
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.status'))->toBe('mismatch')
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.reason'))->toBe('currency_mismatch')
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.expected_currency'))->toBe('MYR')
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.received_currency'))->toBe('USD')
        ->and($freshSession?->error_message)->toBe('Payment currency does not match the checkout currency.');

    Event::assertNotDispatched(OrderPaid::class);
    Event::assertNotDispatched(OrderProcessingStarted::class);
    Log::shouldHaveReceived('warning')->once();
});

it('blocks payment confirmation when the payment currency is missing', function (): void {
    config()->set('checkout.create_order.confirm_payment', true);
    Event::fake([OrderPaid::class, OrderProcessingStarted::class]);

    $order = new Order;
    $order->forceFill([
        'id' => (string) Str::uuid(),
        'order_number' => 'ORD-CURRENCY-MISSING',
    ]);

    $orderService = mock(OrderServiceInterface::class);
    $orderService->shouldReceive('createOrder')->once()->andReturn($order);
    $orderService->shouldReceive('confirmPayment')->never();
    app()->instance(OrderServiceInterface::class, $orderService);

    $session = createCheckoutAmountReconciliationSession([
        'amount' => 1000,
        'currency' => null,
    ]);
    $result = app(CreateOrderStep::class)->handle($session);
    $freshSession = $session->fresh();

    expect($result->isSuccessful())->toBeFalse()
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.reason'))->toBe('currency_mismatch')
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.received_currency'))->toBeNull()
        ->and($freshSession?->error_message)->toBe('Payment currency does not match the checkout currency.');

    Event::assertNotDispatched(OrderPaid::class);
    Event::assertNotDispatched(OrderProcessingStarted::class);
});

it('fails closed when the payment amount is missing', function (): void {
    config()->set('checkout.create_order.confirm_payment', true);
    Event::fake([OrderPaid::class, OrderProcessingStarted::class]);

    $order = new Order;
    $order->forceFill([
        'id' => (string) Str::uuid(),
        'order_number' => 'ORD-AMOUNT-MISSING',
    ]);

    $orderService = mock(OrderServiceInterface::class);
    $orderService->shouldReceive('createOrder')->once()->andReturn($order);
    $orderService->shouldReceive('confirmPayment')->never();
    app()->instance(OrderServiceInterface::class, $orderService);

    $session = createCheckoutAmountReconciliationSession([]);
    $result = app(CreateOrderStep::class)->handle($session);
    $freshSession = $session->fresh();

    expect($result->isSuccessful())->toBeFalse()
        ->and(data_get($freshSession?->payment_data, 'amount_reconciliation.reason'))->toBe('amount_missing')
        ->and($freshSession?->error_message)->toBe('Payment amount does not match the checkout total.');

    Event::assertNotDispatched(OrderPaid::class);
    Event::assertNotDispatched(OrderProcessingStarted::class);
});
