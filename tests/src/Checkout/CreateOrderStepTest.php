<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\States\Active as AffiliateActive;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ApplyDiscountsStep;
use AIArmada\Checkout\Steps\CreateOrderStep;
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
        ], $paymentData),
        'selected_payment_gateway' => 'chip',
        'payment_id' => 'payment-amount-reconciliation',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
        'currency' => 'MYR',
    ]);

    return $session->transitionStatus(Processing::class);
}

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

it('uses the checkout total when the payment amount is missing', function (): void {
    config()->set('checkout.create_order.confirm_payment', true);

    $order = new Order;
    $order->forceFill([
        'id' => (string) Str::uuid(),
        'order_number' => 'ORD-AMOUNT-MISSING',
    ]);

    $orderService = mock(OrderServiceInterface::class);
    $orderService->shouldReceive('createOrder')->once()->andReturn($order);
    $orderService->shouldReceive('confirmPayment')
        ->once()
        ->withArgs(fn (...$arguments): bool => ($arguments[3] ?? null) === 1000)
        ->andReturn($order);
    app()->instance(OrderServiceInterface::class, $orderService);

    $session = createCheckoutAmountReconciliationSession([]);
    $result = app(CreateOrderStep::class)->handle($session);

    expect($result->isSuccessful())->toBeTrue();
});
