<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Http\Controllers\PaymentCallbackController;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\CreateOrderStep;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Steps\ReserveInventoryStep;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Customers\Models\Customer;
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Data\ReservationOutcome;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery\Expectation;

use function Pest\Laravel\mock;

/**
 * @return array<string, mixed>
 */
function chipCheckoutWebhookPayload(string $reference, string $status): array
{
    $timestamp = time();

    return [
        'id' => 'purchase-' . uniqid(),
        'type' => 'purchase',
        'created_on' => $timestamp,
        'updated_on' => $timestamp,
        'client' => [
            'email' => 'checkout@example.com',
            'full_name' => 'Checkout Customer',
        ],
        'purchase' => [
            'total' => 1000,
            'currency' => 'MYR',
            'products' => [
                [
                    'name' => 'Checkout Product',
                    'price' => 1000,
                    'quantity' => 1,
                ],
            ],
        ],
        'brand_id' => 'brand-123',
        'payment' => null,
        'issuer_details' => [
            'legal_name' => 'Example Merchant',
        ],
        'transaction_data' => [
            'payment_method' => 'card',
            'attempts' => [],
        ],
        'status' => $status,
        'status_history' => [],
        'viewed_on' => null,
        'company_id' => null,
        'is_test' => true,
        'user_id' => null,
        'billing_template_id' => null,
        'client_id' => null,
        'send_receipt' => false,
        'is_recurring_token' => false,
        'recurring_token' => null,
        'skip_capture' => false,
        'force_recurring' => false,
        'reference_generated' => $reference,
        'reference' => $reference,
        'notes' => null,
        'issued' => null,
        'due' => null,
        'refund_availability' => 'all',
        'refundable_amount' => 0,
        'currency_conversion' => null,
        'payment_method_whitelist' => [],
        'success_redirect' => null,
        'failure_redirect' => null,
        'cancel_redirect' => null,
        'success_callback' => null,
        'creator_agent' => 'AIArmada/Chip',
        'platform' => 'api',
        'product' => 'purchases',
        'created_from_ip' => null,
        'invoice_url' => null,
        'checkout_url' => null,
        'direct_post_url' => null,
        'marked_as_paid' => false,
        'order_id' => null,
        'upsell_campaigns' => [],
        'referral_campaign_id' => null,
        'referral_code' => null,
        'referral_code_details' => null,
        'referral_code_generated' => null,
        'retain_level_details' => null,
        'can_retrieve' => false,
        'can_chargeback' => false,
    ];
}

describe('PaymentCallbackController', function (): void {
    $setConfig = function (): void {
        config()->set('checkout.redirects.success', '/orders/{order_id}');
        config()->set('checkout.redirects.failure', '/checkout/failed');
        config()->set('checkout.redirects.cancel', '/checkout/cancelled');
    };

    it('redirects to failure when session not found on success', function () use ($setConfig): void {
        $setConfig();
        $request = Request::create('/checkout/payment/success', 'GET', ['session' => 'nonexistent']);

        $response = app(PaymentCallbackController::class)->success($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed')
            ->and($response->getSession()->get('error'))->toBe('Checkout session not found');
    });

    it('redirects to failure when session not found on failure callback', function () use ($setConfig): void {
        $setConfig();
        $request = Request::create('/checkout/payment/failure', 'GET', ['session' => 'nonexistent']);

        $response = app(PaymentCallbackController::class)->failure($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed')
            ->and($response->getSession()->get('error'))->toBe('Checkout session not found');
    });

    it('redirects to cancel page when session not found on cancel', function () use ($setConfig): void {
        $setConfig();
        $request = Request::create('/checkout/payment/cancel', 'GET', ['session' => 'nonexistent']);

        $response = app(PaymentCallbackController::class)->cancel($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed');
    });

    it('resolves session via session query parameter', function () use ($setConfig): void {
        $setConfig();
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-123',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/cancel', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = app(PaymentCallbackController::class)->cancel($request);

        // Should find session and redirect to cancel (not failure)
        expect($response->getTargetUrl())->toContain('/checkout/cancelled');
    });

    it('resolves session via checkout_session_id query parameter', function () use ($setConfig): void {
        $setConfig();
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-alt',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/cancel', 'GET', [
            'checkout_session_id' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = app(PaymentCallbackController::class)->cancel($request);

        expect($response->getTargetUrl())->toContain('/checkout/cancelled');
    });

    it('includes session id in cancel redirect', function () use ($setConfig): void {
        $setConfig();
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-cancel',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/cancel', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = app(PaymentCallbackController::class)->cancel($request);

        expect($response->getSession()->get('checkout_session_id'))->toBe($session->id)
            ->and($response->getSession()->get('message'))->toBe('Payment was cancelled');
    });

    it('includes session id in failure redirect', function () use ($setConfig): void {
        $setConfig();
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-fail',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/failure', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = app(PaymentCallbackController::class)->failure($request);

        expect($response->getSession()->get('checkout_session_id'))->toBe($session->id)
            ->and($response->getSession()->get('error'))->toBe('Payment failed');
    });

    it('treats failure callbacks as idempotent after checkout completion', function () use ($setConfig): void {
        $setConfig();
        $orderId = (string) Str::uuid();

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-completed-failure',
            'order_id' => $orderId,
            'status' => Completed::class,
            'completed_at' => now(),
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/failure', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = app(PaymentCallbackController::class)->failure($request);

        expect($response->getTargetUrl())->toContain('/orders/' . $orderId)
            ->and($session->fresh()->status instanceof Completed)->toBeTrue();
    });

    it('treats cancel callbacks as idempotent after checkout completion', function () use ($setConfig): void {
        $setConfig();
        $orderId = (string) Str::uuid();

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-completed-cancel',
            'order_id' => $orderId,
            'status' => Completed::class,
            'completed_at' => now(),
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/cancel', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = app(PaymentCallbackController::class)->cancel($request);

        expect($response->getTargetUrl())->toContain('/orders/' . $orderId)
            ->and($session->fresh()->status instanceof Completed)->toBeTrue();
    });

    it('treats duplicate success callbacks as idempotent once checkout completes', function () use ($setConfig): void {
        $setConfig();
        $orderId = (string) Str::uuid();

        $checkoutService = mock(CheckoutServiceInterface::class);
        /** @var Expectation $callbackExpectation */
        $callbackExpectation = $checkoutService->shouldReceive('handlePaymentCallback');
        $callbackExpectation->once()
            ->withArgs(function (CheckoutSession $session, string $callbackType, array $payload): bool {
                expect($session->status instanceof AwaitingPayment)->toBeTrue()
                    ->and($callbackType)->toBe('success')
                    ->and($payload)->toBe([]);

                return true;
            })
            ->andReturnUsing(function (CheckoutSession $session) use ($orderId): CheckoutResult {
                $session->transitionStatus(Completed::class);
                $session->update([
                    'order_id' => $orderId,
                    'payment_redirect_url' => null,
                ]);

                return CheckoutResult::success($session->fresh() ?? $session, $orderId);
            });

        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-duplicate-success',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'chip',
            'payment_redirect_url' => 'https://gateway.example.test/pay',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $firstRequest = Request::create('/checkout/payment/success', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $secondRequest = Request::create('/checkout/payment/success', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $firstResponse = app(PaymentCallbackController::class)->success($firstRequest);
        $secondResponse = app(PaymentCallbackController::class)->success($secondRequest);

        expect($firstResponse->getTargetUrl())->toContain('/orders/' . $orderId)
            ->and($secondResponse->getTargetUrl())->toContain('/orders/' . $orderId)
            ->and($session->fresh()->status instanceof Completed)->toBeTrue()
            ->and($session->fresh()->order_id)->toBe($orderId);
    });

    it('rejects callbacks without a valid token', function () use ($setConfig): void {
        $setConfig();
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-invalid-token',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/cancel', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'invalid-token',
        ]);

        $response = app(PaymentCallbackController::class)->cancel($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed')
            ->and($response->getSession()->get('error'))->toBe('Checkout session not found');
    });

    it('does not verify payment via user-controlled query params (prevents forged status=paid)', function () use ($setConfig): void {
        $setConfig();
        // Regression test for P0: callback controller must NOT pass $request->query() to
        // verifyAndCompletePayment, as attacker could append &status=paid to the success URL
        // (the callback_token is visible in their browser bar after the gateway redirect).
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-p0-exploit',
            'selected_payment_gateway' => 'chip',
            'grand_total' => 5000,
            'currency' => 'MYR',
            'payment_id' => null, // payment not actually made
            'payment_data' => ['callback_token' => 'legit-token'],
        ]);

        $request = Request::create('/checkout/payment/success', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'legit-token',
            'status' => 'paid',       // attacker-controlled forged param
            'id' => 'fake-payment-id', // attacker-controlled forged param
        ]);

        $response = app(PaymentCallbackController::class)->success($request);

        // Must NOT redirect to success — payment should not be verifiable without gateway API
        expect($session->fresh()->status instanceof Completed)->toBeFalse();
    });
});

describe('ProcessPaymentStep', function (): void {
    it('handles free orders without redundant status transitions', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-free',
            'grand_total' => 0,
            'currency' => 'USD',
        ]);

        $session->transitionStatus(Processing::class);

        $step = app(ProcessPaymentStep::class);
        $result = $step->handle($session);

        expect($result->isSuccessful())->toBeTrue()
            ->and($session->fresh()->payment_data['type'] ?? null)->toBe('free_order');
    });

    it('preserves the stored checkout actor reference in payment_data for free orders', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-free-actor',
            'grand_total' => 0,
            'currency' => 'USD',
            'payment_data' => [
                'checkout_actor' => [
                    'type' => 'fixture-user',
                    'id' => 'actor-123',
                ],
            ],
        ]);

        $session->transitionStatus(Processing::class);

        $step = app(ProcessPaymentStep::class);
        $result = $step->handle($session);

        expect($result->isSuccessful())->toBeTrue()
            ->and(data_get($session->fresh()->payment_data, 'checkout_actor.type'))->toBe('fixture-user')
            ->and(data_get($session->fresh()->payment_data, 'checkout_actor.id'))->toBe('actor-123')
            ->and($session->fresh()->payment_data['type'] ?? null)->toBe('free_order');
    });

    it('preserves callback_token in payment_data after payment initiation (P1 regression)', function (): void {
        // Regression test for P1: ProcessPaymentStep was replacing the entire payment_data
        // JSON, silently wiping the callback_token that ensureCallbackToken() had just stored.
        // Without the token, every redirect-based callback would be rejected by resolveSession().
        $mockProcessor = mock(PaymentProcessorInterface::class);
        $mockProcessor->shouldReceive('getIdentifier')->andReturn('chip');
        $mockProcessor->shouldReceive('isAvailable')->andReturn(true);
        $mockProcessor->shouldReceive('createPayment')->andReturn(
            PaymentResult::pending('pay_test_123', 'https://gateway.example.com/pay'),
        );

        $mockResolver = mock(PaymentGatewayResolverInterface::class);
        $mockResolver->shouldReceive('resolve')->andReturn($mockProcessor);

        app()->instance(PaymentGatewayResolverInterface::class, $mockResolver);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-p1',
            'selected_payment_gateway' => 'chip',
            'grand_total' => 1000,
            'currency' => 'MYR',
        ]);
        $session->transitionStatus(Processing::class);

        $step = app(ProcessPaymentStep::class);
        $result = $step->handle($session);

        $fresh = $session->fresh();

        expect($result->isSuccessful())->toBeTrue()
            ->and($fresh->payment_data)->toHaveKey('callback_token')
            ->and($fresh->payment_data['callback_token'])->not->toBeNull()
            ->and($fresh->payment_data['callback_token'])->not->toBe('')
            ->and($fresh->payment_data)->toHaveKey('gateway')
            ->and($fresh->payment_data['gateway'])->toBe('chip');
    });
});

describe('CreateOrderStep', function (): void {
    it('creates an order when the order service is bound', function (): void {
        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-1',
        ]);

        $orderService->shouldReceive('createOrder')->andReturn($order);

        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-create-order',
            'cart_snapshot' => [
                'items' => [
                    [
                        'name' => 'Test Item',
                        'quantity' => 1,
                        'price' => 0,
                    ],
                ],
                'subtotal' => 0,
                'total' => 0,
                'item_count' => 1,
            ],
            'payment_data' => [
                'type' => 'free_order',
            ],
            'subtotal' => 0,
            'grand_total' => 0,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        $step = app(CreateOrderStep::class);
        $result = $step->handle($session);

        expect($result->isSuccessful())->toBeTrue()
            ->and($session->fresh()->order_id)->not->toBeNull();
    });

    it('does not complete checkout when payment confirmation fails', function (): void {
        config()->set('checkout.create_order.confirm_payment', true);

        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-PAYMENT-FAILED',
        ]);

        $orderService->shouldReceive('createOrder')->once()->andReturn($order);
        $orderService->shouldReceive('confirmPayment')->once()->andThrow(new RuntimeException('gateway unavailable'));
        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-payment-failed',
            'cart_snapshot' => ['items' => []],
            'payment_data' => [
                'type' => 'card',
                'status' => PaymentStatus::Completed->value,
                'transaction_id' => 'tx-payment-failed',
                'gateway' => 'chip',
            ],
            'selected_payment_gateway' => 'chip',
            'payment_id' => 'payment-failed',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        $result = app(CreateOrderStep::class)->handle($session);

        expect($result->isSuccessful())->toBeFalse()
            ->and($session->fresh()->order_id)->not->toBeNull()
            ->and($session->fresh()->completed_at)->toBeNull();
    });

    it('responds with the stored order on retry after a failed confirmation', function (): void {
        config()->set('checkout.create_order.confirm_payment', true);

        // Persist a real order so the session relationship resolves on retry
        $order = Order::create([
            'order_number' => 'ORD-RETRY-' . Str::random(6),
            'currency' => 'USD',
            'grand_total' => 1000,
        ]);

        $orderService = mock(OrderServiceInterface::class);
        $orderService->shouldReceive('createOrder')->once()->andReturn($order);
        $orderService->shouldReceive('confirmPayment')->twice()->andReturnUsing(function () use ($order) {
            static $call = 0;
            $call++;

            if ($call === 1) {
                throw new RuntimeException('gateway unavailable');
            }

            return $order;
        });

        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-retry-safe',
            'cart_snapshot' => ['items' => []],
            'payment_data' => [
                'type' => 'card',
                'status' => PaymentStatus::Completed->value,
                'transaction_id' => 'tx-retry-safe',
                'gateway' => 'chip',
            ],
            'selected_payment_gateway' => 'chip',
            'payment_id' => 'payment-retry-safe',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        // First attempt — payment fails
        $firstResult = app(CreateOrderStep::class)->handle($session);

        expect($firstResult->isSuccessful())->toBeFalse();

        // Reload session — order_id was persisted despite payment failure
        $session = $session->fresh();
        expect($session->order_id)->not->toBeNull();

        // Second attempt — payment succeeds, order reused
        $secondResult = app(CreateOrderStep::class)->handle($session);

        expect($secondResult->isSuccessful())->toBeTrue()
            ->and($session->fresh()->order_id)->toBe($order->id);

        app(CheckoutFinalizer::class)->finalize($session->fresh());

        expect($session->fresh()->completed_at)->not->toBeNull();
    });

    it('does not commit inventory reservations when payment confirmation fails', function (): void {
        config()->set('checkout.create_order.confirm_payment', true);

        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-PAYMENT-FAILED-NO-INV',
        ]);

        $orderService->shouldReceive('createOrder')->once()->andReturn($order);
        $orderService->shouldReceive('confirmPayment')->once()->andThrow(new RuntimeException('gateway unavailable'));
        app()->instance(OrderServiceInterface::class, $orderService);

        $inventoryService = mock(CheckoutReservationServiceInterface::class);
        $inventoryService->shouldReceive('commit')->never();
        app()->instance(CheckoutReservationServiceInterface::class, $inventoryService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-payment-failed-no-inv',
            'cart_snapshot' => ['items' => []],
            'payment_data' => [
                'type' => 'card',
                'status' => PaymentStatus::Completed->value,
                'transaction_id' => 'tx-payment-failed-no-inv',
                'gateway' => 'chip',
            ],
            'selected_payment_gateway' => 'chip',
            'payment_id' => 'payment-failed-no-inv',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        $result = app(CreateOrderStep::class)->handle($session);

        expect($result->isSuccessful())->toBeFalse();
    });

    it('commits inventory reservations when payment confirmation succeeds for paid orders', function (): void {
        config()->set('checkout.create_order.confirm_payment', true);

        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-PAID-INVENTORY',
        ]);

        $orderService->shouldReceive('createOrder')->once()->andReturn($order);
        $orderService->shouldReceive('confirmPayment')->once()->andReturn($order);

        app()->instance(OrderServiceInterface::class, $orderService);

        $inventoryService = mock(CheckoutReservationServiceInterface::class);
        app()->instance(CheckoutReservationServiceInterface::class, $inventoryService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-paid-inventory-order',
            'selected_payment_gateway' => 'chip',
            'payment_id' => 'pay_paid_inventory_123',
            'cart_snapshot' => [
                'items' => [
                    [
                        'name' => 'Paid Inventory Item',
                        'quantity' => 1,
                        'price' => 1000,
                    ],
                ],
            ],
            'pricing_data' => [
                'inventory_reservation' => [
                    'reference' => 'test-cart-paid-inventory-order',
                    'state' => 'active',
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ],
            'payment_data' => [
                'status' => PaymentStatus::Completed->value,
                'transaction_id' => 'txn_paid_inventory_123',
            ],
            'subtotal' => 1000,
            'grand_total' => 1000,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        $step = app(CreateOrderStep::class);

        expect($step->handle($session)->isSuccessful())->toBeTrue();
    });

    it('still commits inventory reservations for paid orders when payment confirmation is disabled', function (): void {
        config()->set('checkout.create_order.confirm_payment', false);

        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-PAID-WITHOUT-CONFIRM',
        ]);

        $orderService->shouldReceive('createOrder')->once()->andReturn($order);
        $orderService->shouldReceive('confirmPayment')->never();

        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-paid-without-confirm',
            'selected_payment_gateway' => 'chip',
            'payment_id' => 'pay_without_confirm_123',
            'cart_snapshot' => [
                'items' => [
                    [
                        'name' => 'Paid Item Without Confirmation',
                        'quantity' => 1,
                        'price' => 1000,
                    ],
                ],
            ],
            'pricing_data' => [
                'inventory_reservation' => [
                    'reference' => 'test-cart-paid-without-confirm',
                    'state' => 'active',
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ],
            'payment_data' => [
                'status' => PaymentStatus::Completed->value,
                'transaction_id' => 'txn_without_confirm_123',
            ],
            'subtotal' => 1000,
            'grand_total' => 1000,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        $inventoryService = mock(CheckoutReservationServiceInterface::class);
        app()->instance(CheckoutReservationServiceInterface::class, $inventoryService);

        $step = app(CreateOrderStep::class);

        expect($step->handle($session)->isSuccessful())->toBeTrue();
    });

    it('still commits inventory reservations for free orders', function (): void {
        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-FREE-INVENTORY',
        ]);

        $orderService->shouldReceive('createOrder')->once()->andReturn($order);
        $orderService->shouldReceive('confirmPayment')->never();

        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-free-inventory-order',
            'cart_snapshot' => [
                'items' => [
                    [
                        'name' => 'Free Inventory Item',
                        'quantity' => 1,
                        'price' => 0,
                    ],
                ],
            ],
            'pricing_data' => [
                'inventory_reservation' => [
                    'reference' => 'test-cart-free-inventory-order',
                    'state' => 'active',
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ],
            'payment_data' => [
                'type' => 'free_order',
            ],
            'subtotal' => 0,
            'grand_total' => 0,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        $inventoryService = mock(CheckoutReservationServiceInterface::class);
        app()->instance(CheckoutReservationServiceInterface::class, $inventoryService);

        $step = app(CreateOrderStep::class);

        expect($step->handle($session)->isSuccessful())->toBeTrue();
    });

    it('passes customer type and finalized pricing data to the order service', function (): void {
        config()->set('customers.database.tables.customers', 'customers');

        $customer = Customer::create([
            'first_name' => 'Priced',
            'last_name' => 'Customer',
            'email' => 'priced-customer@example.com',
            'is_guest' => false,
        ]);

        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-PRICED',
        ]);

        /** @var Expectation $createOrder */
        $createOrder = $orderService->shouldReceive('createOrder');
        $createOrder->once()
            ->withArgs(function (array $orderData, array $items) use ($customer): bool {
                expect($orderData['customer_id'])->toBe($customer->id)
                    ->and($orderData['customer_type'])->toBe($customer->getMorphClass())
                    ->and(data_get($orderData, 'metadata.cart_id'))->toBe('test-cart-priced-order')
                    ->and(data_get($orderData, 'metadata.cart_snapshot_id'))->toBe('snapshot-row-123')
                    ->and(data_get($orderData, 'metadata.cart_identifier'))->toBe('public-cart-identifier-123')
                    ->and(data_get($orderData, 'metadata.cart_instance'))->toBe('public-checkout')
                    ->and($items)->toHaveCount(1)
                    ->and($items[0]['unit_price'])->toBe(1000)
                    ->and($items[0]['discount_amount'])->toBe(200)
                    ->and($items[0]['tax_amount'])->toBe(0)
                    ->and($items[0]['metadata']['pricing_breakdown'])->toBe([
                        ['source' => 'price-list'],
                    ]);

                return true;
            })
            ->andReturn($order);

        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-priced-order',
            'customer_id' => $customer->id,
            'cart_snapshot' => [
                'id' => 'snapshot-row-123',
                'identifier' => 'public-cart-identifier-123',
                'instance' => 'public-checkout',
                'items' => [
                    [
                        'name' => 'Priced Item',
                        'quantity' => 1,
                        'price' => 1000,
                    ],
                ],
            ],
            'pricing_data' => [
                'items' => [
                    [
                        'quantity' => 1,
                        'unit_price' => 800,
                        'original_unit_price' => 1000,
                        'line_total' => 800,
                        'pricing_breakdown' => [
                            ['source' => 'price-list'],
                        ],
                    ],
                ],
            ],
            'payment_data' => [
                'type' => 'free_order',
            ],
            'subtotal' => 800,
            'grand_total' => 800,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        $step = app(CreateOrderStep::class);

        expect($step->handle($session)->isSuccessful())->toBeTrue();
    });

    it('redeems applied vouchers after a successful order', function (): void {
        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-VOUCHER',
        ]);

        /** @var Expectation $createOrderExpectation */
        $createOrderExpectation = $orderService->shouldReceive('createOrder');
        $createOrderExpectation->once()->andReturn($order);

        app()->instance(OrderServiceInterface::class, $orderService);

        $voucherService = mock(VoucherServiceInterface::class);
        app()->instance(VoucherServiceInterface::class, $voucherService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-voucher-order',
            'cart_snapshot' => [
                'items' => [
                    [
                        'name' => 'Voucher Item',
                        'quantity' => 1,
                        'price' => 1000,
                    ],
                ],
            ],
            'payment_data' => [
                'type' => 'free_order',
            ],
            'discount_data' => [
                'vouchers' => [
                    ['code' => 'WELCOME10'],
                ],
            ],
            'subtotal' => 1000,
            'grand_total' => 1000,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);
        $session->refresh();

        $step = new CreateOrderStep(new VouchersAdapter);

        expect($step->handle($session)->isSuccessful())->toBeTrue();
    });

    it('prefers an explicit purchasable over a plain product id when building order items', function (): void {
        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-PURCHASABLE',
        ]);

        /** @var Expectation $createOrderExpectation */
        $createOrderExpectation = $orderService->shouldReceive('createOrder');
        $createOrderExpectation->once()
            ->withArgs(function (array $orderData, array $items): bool {
                expect($items)->toHaveCount(1)
                    ->and($items[0]['purchasable_id'])->toBe('variant-123')
                    ->and($items[0]['purchasable_type'])->toBe('variant-model');

                return true;
            })
            ->andReturn($order);

        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-explicit-purchasable',
            'cart_snapshot' => [
                'items' => [
                    [
                        'name' => 'Variant Item',
                        'quantity' => 1,
                        'price' => 1000,
                        'product_id' => 'product-123',
                        'purchasable_id' => 'variant-123',
                        'purchasable_type' => 'variant-model',
                    ],
                ],
            ],
            'payment_data' => [
                'type' => 'free_order',
            ],
            'subtotal' => 1000,
            'grand_total' => 1000,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        $step = app(CreateOrderStep::class);

        expect($step->handle($session)->isSuccessful())->toBeTrue();
    });

    it('prefers an explicit attribute purchasable over an attribute product id when building order items', function (): void {
        $orderService = mock(OrderServiceInterface::class);
        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'TEST-ORDER-ATTRIBUTE-PURCHASABLE',
        ]);

        /** @var Expectation $createOrderExpectation */
        $createOrderExpectation = $orderService->shouldReceive('createOrder');
        $createOrderExpectation->once()
            ->withArgs(function (array $orderData, array $items): bool {
                expect($items)->toHaveCount(1)
                    ->and($items[0]['purchasable_id'])->toBe('variant-attribute-123')
                    ->and($items[0]['purchasable_type'])->toBe('variant-model');

                return true;
            })
            ->andReturn($order);

        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-attribute-purchasable',
            'cart_snapshot' => [
                'items' => [
                    [
                        'name' => 'Variant Attribute Item',
                        'quantity' => 1,
                        'price' => 1000,
                        'attributes' => [
                            'product_id' => 'product-attribute-123',
                            'purchasable_id' => 'variant-attribute-123',
                            'purchasable_type' => 'variant-model',
                        ],
                    ],
                ],
            ],
            'payment_data' => [
                'type' => 'free_order',
            ],
            'subtotal' => 1000,
            'grand_total' => 1000,
            'currency' => 'USD',
        ]);
        $session = $session->transitionStatus(Processing::class);

        $step = app(CreateOrderStep::class);

        expect($step->handle($session)->isSuccessful())->toBeTrue();
    });

    it('reserves inventory against the checkout cart id', function (): void {
        $inventoryService = mock(CheckoutReservationServiceInterface::class);
        $outcome = new ReservationOutcome(
            reference: 'test-cart-reserve-reference',
            state: 'active',
            expiresAt: now()->addSeconds(900)->toIso8601String(),
            lines: [
                ['product_id' => 'product-123', 'variant_id' => 'variant-123', 'quantity' => 2],
            ],
        );
        $inventoryService->shouldReceive('reserve')
            ->once()
            ->with('test-cart-reserve-reference', Mockery::on(fn ($lines) => count($lines) === 1), 900)
            ->andReturn($outcome);

        app()->instance(CheckoutReservationServiceInterface::class, $inventoryService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-reserve-reference',
            'cart_snapshot' => [
                'items' => [
                    [
                        'name' => 'Reserved Item',
                        'quantity' => 2,
                        'product_id' => 'product-123',
                        'variant_id' => 'variant-123',
                    ],
                ],
            ],
        ]);

        $step = app(ReserveInventoryStep::class);

        expect($step->handle($session)->isSuccessful())->toBeTrue()
            ->and(data_get($session->fresh()->pricing_data, 'inventory_reservation.reference'))->toBe('test-cart-reserve-reference');
    });

    it('releases reserved inventory against the checkout cart id during rollback', function (): void {
        $inventoryService = mock(CheckoutReservationServiceInterface::class);
        $inventoryService->shouldReceive('release')
            ->once()
            ->with('test-cart-release-reference')
            ->andReturn(new ReservationOutcome('test-cart-release-reference', 'released'));

        app()->instance(CheckoutReservationServiceInterface::class, $inventoryService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-release-reference',
            'pricing_data' => [
                'inventory_reservation' => [
                    'reference' => 'test-cart-release-reference',
                    'state' => 'active',
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
                'reservations_expire_at' => now()->addMinutes(15)->toIso8601String(),
            ],
        ]);

        $step = app(ReserveInventoryStep::class);
        $step->rollback($session);

        expect(data_get($session->fresh()->pricing_data, 'inventory_reservation'))->toBeNull()
            ->and(data_get($session->fresh()->pricing_data, 'reservations_expire_at'))->toBeNull();
    });

    it('depends on tax before payment when the tax step is enabled', function (): void {
        config()->set('checkout.integrations.inventory.reserve_before_payment', true);

        $stepRegistry = mock(CheckoutStepRegistryInterface::class);
        $stepRegistry->shouldReceive('has')->once()->with('calculate_tax')->andReturn(true);
        $stepRegistry->shouldReceive('isEnabled')->once()->with('calculate_tax')->andReturn(true);

        $step = new ReserveInventoryStep(stepRegistry: $stepRegistry);

        expect($step->getDependencies())->toBe(['calculate_pricing', 'calculate_tax']);
    });

    it('does not depend on tax before payment when the tax step is disabled', function (): void {
        config()->set('checkout.integrations.inventory.reserve_before_payment', true);

        $stepRegistry = mock(CheckoutStepRegistryInterface::class);
        $stepRegistry->shouldReceive('has')->once()->with('calculate_tax')->andReturn(true);
        $stepRegistry->shouldReceive('isEnabled')->once()->with('calculate_tax')->andReturn(false);

        $step = new ReserveInventoryStep(stepRegistry: $stepRegistry);

        expect($step->getDependencies())->toBe(['calculate_pricing']);
    });

    it('depends on payment when configured to reserve inventory after payment', function (): void {
        config()->set('checkout.integrations.inventory.reserve_before_payment', false);

        $step = new ReserveInventoryStep;

        expect($step->getDependencies())->toBe(['calculate_pricing', 'process_payment']);
    });

    it('surfaces free order failure instead of logging', function (): void {
        $orderService = mock(OrderServiceInterface::class);
        $orderService->shouldReceive('createOrder')
            ->once()
            ->andThrow(new RuntimeException('Simulated order creation failure'));

        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-free-failure',
            'cart_snapshot' => ['items' => [['name' => 'Free Item', 'quantity' => 1, 'price' => 0]]],
            'payment_data' => ['type' => 'free_order'],
            'subtotal' => 0,
            'grand_total' => 0,
            'currency' => 'USD',
        ]);
        $session->transitionStatus(Processing::class);

        $step = app(CreateOrderStep::class);

        expect(fn () => $step->handle($session))
            ->toThrow(RuntimeException::class, 'Simulated order creation failure');

        expect($session->fresh()->status instanceof Completed)->toBeFalse();
    });
});

describe('CheckoutService', function (): void {
    it('bridges chip purchase paid events into checkout success callbacks', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-chip-paid',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'chip',
        ]);

        $payload = chipCheckoutWebhookPayload($session->id, 'paid');

        $checkoutService = mock(CheckoutServiceInterface::class);
        /** @var Expectation $callbackExpectation */
        $callbackExpectation = $checkoutService->shouldReceive('handlePaymentCallback');
        $callbackExpectation->once()
            ->withArgs(function (CheckoutSession $resolvedSession, string $callbackType, array $incomingPayload) use ($payload, $session): bool {
                expect($resolvedSession->id)->toBe($session->id)
                    ->and($callbackType)->toBe('success')
                    ->and($incomingPayload)->toBe($payload);

                return true;
            })
            ->andReturn(CheckoutResult::success($session));

        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        PurchasePaid::dispatch(PurchaseData::from($payload), $payload);
    });

    it('bridges chip purchase payment failure events into checkout failure callbacks', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-chip-failure',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'chip',
        ]);

        $payload = chipCheckoutWebhookPayload($session->id, 'error');

        $checkoutService = mock(CheckoutServiceInterface::class);
        /** @var Expectation $callbackExpectation */
        $callbackExpectation = $checkoutService->shouldReceive('handlePaymentCallback');
        $callbackExpectation->once()
            ->withArgs(function (CheckoutSession $resolvedSession, string $callbackType, array $incomingPayload) use ($payload, $session): bool {
                expect($resolvedSession->id)->toBe($session->id)
                    ->and($callbackType)->toBe('failure')
                    ->and($incomingPayload)->toBe($payload);

                return true;
            })
            ->andReturn(CheckoutResult::failed($session, 'Payment failed'));

        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        PurchasePaymentFailure::dispatch(PurchaseData::from($payload), $payload);
    });

    it('bridges chip purchase cancelled events into checkout cancel callbacks', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-chip-cancelled',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'chip',
        ]);

        $payload = chipCheckoutWebhookPayload($session->id, 'cancelled');

        $checkoutService = mock(CheckoutServiceInterface::class);
        /** @var Expectation $callbackExpectation */
        $callbackExpectation = $checkoutService->shouldReceive('handlePaymentCallback');
        $callbackExpectation->once()
            ->withArgs(function (CheckoutSession $resolvedSession, string $callbackType, array $incomingPayload) use ($payload, $session): bool {
                expect($resolvedSession->id)->toBe($session->id)
                    ->and($callbackType)->toBe('cancel')
                    ->and($incomingPayload)->toBe($payload);

                return true;
            })
            ->andReturn(CheckoutResult::failed($session, 'Payment was cancelled'));

        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        PurchaseCancelled::dispatch(PurchaseData::from($payload), $payload);
    });

    it('ignores duplicate chip purchase paid events for already completed sessions', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-chip-completed',
            'status' => Completed::class,
            'selected_payment_gateway' => 'chip',
            'completed_at' => now(),
        ]);

        $payload = chipCheckoutWebhookPayload($session->id, 'paid');

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')->never();

        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        PurchasePaid::dispatch(PurchaseData::from($payload), $payload);
    });

    it('ignores chip purchase events for checkout sessions using another gateway', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-chip-wrong-gateway',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'cashier',
        ]);

        $payload = chipCheckoutWebhookPayload($session->id, 'paid');

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')->never();

        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        PurchasePaid::dispatch(PurchaseData::from($payload), $payload);
    });

    it('completes successfully when a step sets the session to completed', function (): void {
        $registry = new CheckoutStepRegistry;

        $registry->register('complete_order', new class implements CheckoutStepInterface
        {
            public function getIdentifier(): string
            {
                return 'complete_order';
            }

            public function getName(): string
            {
                return 'Complete Order';
            }

            public function validate(CheckoutSession $session): array
            {
                return [];
            }

            public function handle(CheckoutSession $session): StepResult
            {
                $session->transitionStatus(Completed::class);

                return StepResult::success($this->getIdentifier());
            }

            public function canSkip(CheckoutSession $session): bool
            {
                return false;
            }

            public function rollback(CheckoutSession $session): void {}

            public function getDependencies(): array
            {
                return [];
            }
        });

        $registry->setOrder(['complete_order']);

        $service = new CheckoutService(
            stepRegistry: $registry,
            events: app(Dispatcher::class),
            stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
            finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
            paymentResolver: null,
        );

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-checkout-complete',
            'step_states' => [
                'complete_order' => 'pending',
            ],
            'current_step' => 'complete_order',
        ]);

        $result = $service->processCheckout($session);

        expect($result->success)->toBeTrue();
    });

    it('returns awaiting payment without double-counting attempts on retry redirects', function (): void {
        $redirectingPaymentStep = new class implements CheckoutStepInterface
        {
            public function getIdentifier(): string
            {
                return 'process_payment';
            }

            public function getName(): string
            {
                return 'Process Payment';
            }

            public function validate(CheckoutSession $session): array
            {
                return [];
            }

            public function handle(CheckoutSession $session): StepResult
            {
                $session->update([
                    'payment_attempts' => $session->payment_attempts + 1,
                    'payment_redirect_url' => 'https://gateway.example.test/redirect',
                ]);

                return StepResult::success($this->getIdentifier());
            }

            public function canSkip(CheckoutSession $session): bool
            {
                return false;
            }

            public function rollback(CheckoutSession $session): void {}

            public function getDependencies(): array
            {
                return [];
            }
        };

        $downstreamTracker = new class
        {
            public bool $executed = false;
        };

        $downstreamStep = new class($downstreamTracker) implements CheckoutStepInterface
        {
            public function __construct(
                private readonly object $downstreamTracker,
            ) {}

            public function getIdentifier(): string
            {
                return 'create_order';
            }

            public function getName(): string
            {
                return 'Create Order';
            }

            public function validate(CheckoutSession $session): array
            {
                return [];
            }

            public function handle(CheckoutSession $session): StepResult
            {
                $this->downstreamTracker->executed = true;

                return StepResult::success($this->getIdentifier());
            }

            public function canSkip(CheckoutSession $session): bool
            {
                return false;
            }

            public function rollback(CheckoutSession $session): void {}

            public function getDependencies(): array
            {
                return ['process_payment'];
            }
        };

        $registry = new CheckoutStepRegistry;
        $registry->register('process_payment', $redirectingPaymentStep);
        $registry->register('create_order', $downstreamStep);
        $registry->setOrder(['process_payment', 'create_order']);

        $service = new CheckoutService(
            stepRegistry: $registry,
            events: app(Dispatcher::class),
            stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
            finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
            paymentResolver: null,
        );

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-retry-payment',
            'status' => PaymentFailed::class,
            'step_states' => [
                'process_payment' => 'failed',
                'create_order' => 'pending',
            ],
            'payment_attempts' => 0,
        ]);

        $result = $service->retryPayment($session);

        expect($result->requiresRedirect())->toBeTrue()
            ->and($session->fresh()->payment_attempts)->toBe(1)
            ->and($downstreamTracker->executed)->toBeFalse();
    });

    it('continues with inventory before customer persistence after successful payment callbacks', function (): void {
        $tracker = new class
        {
            /** @var array<int, string> */
            public array $steps = [];
        };

        $processPaymentStep = new class implements CheckoutStepInterface
        {
            public function getIdentifier(): string
            {
                return 'process_payment';
            }

            public function getName(): string
            {
                return 'Process Payment';
            }

            public function validate(CheckoutSession $session): array
            {
                return [];
            }

            public function handle(CheckoutSession $session): StepResult
            {
                return StepResult::success($this->getIdentifier());
            }

            public function canSkip(CheckoutSession $session): bool
            {
                return false;
            }

            public function rollback(CheckoutSession $session): void {}

            public function getDependencies(): array
            {
                return [];
            }
        };

        $trackedStep = static function (object $tracker, string $identifier, array $dependencies = []): CheckoutStepInterface {
            return new class($tracker, $identifier, $dependencies) implements CheckoutStepInterface
            {
                public function __construct(
                    private readonly object $tracker,
                    private readonly string $identifier,
                    private readonly array $dependencies,
                ) {}

                public function getIdentifier(): string
                {
                    return $this->identifier;
                }

                public function getName(): string
                {
                    return $this->identifier;
                }

                public function validate(CheckoutSession $session): array
                {
                    return [];
                }

                public function handle(CheckoutSession $session): StepResult
                {
                    $this->tracker->steps[] = $this->identifier;

                    return StepResult::success($this->identifier);
                }

                public function canSkip(CheckoutSession $session): bool
                {
                    return false;
                }

                public function rollback(CheckoutSession $session): void {}

                public function getDependencies(): array
                {
                    return $this->dependencies;
                }
            };
        };

        $registry = new CheckoutStepRegistry;
        $registry->register('process_payment', $processPaymentStep);
        $registry->register('reserve_inventory', $trackedStep($tracker, 'reserve_inventory', ['process_payment']));
        $registry->register('persist_customer', $trackedStep($tracker, 'persist_customer', ['process_payment']));
        $registry->register('create_order', $trackedStep($tracker, 'create_order', ['persist_customer']));
        $registry->setOrder(['process_payment', 'reserve_inventory', 'persist_customer', 'create_order']);

        $service = new CheckoutService(
            stepRegistry: $registry,
            events: app(Dispatcher::class),
            stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
            finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
            paymentResolver: null,
        );

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-post-payment-phase',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'chip',
            'step_states' => [
                'process_payment' => 'pending',
                'reserve_inventory' => 'pending',
                'persist_customer' => 'pending',
                'create_order' => 'pending',
            ],
            'payment_data' => [
                'callback_token' => 'callback-token',
            ],
        ]);

        $result = $service->handlePaymentCallback($session, 'success', ['status' => 'paid']);

        expect($result->success)->toBeTrue()
            ->and($tracker->steps)->toBe(['reserve_inventory', 'persist_customer', 'create_order'])
            ->and($session->fresh()->status instanceof Completed)->toBeTrue();
    });

    it('dispatches CheckoutCompleted exactly once for a completed checkout', function (): void {
        Event::fake([CheckoutCompleted::class]);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-checkout-completed-once',
        ]);

        $finalizer = new CheckoutFinalizer(app(Dispatcher::class));
        $result = $finalizer->finalize($session);

        expect($result->success)->toBeTrue();

        Event::assertDispatched(CheckoutCompleted::class, 1);
    });

    it('clears cart only after checkout completion', function (): void {
        $cartManager = mock(CartManagerInterface::class);
        $cartManager->shouldReceive('getById')->once()->with('test-cart-clear')->andReturnNull();

        $finalizer = new CheckoutFinalizer(app(Dispatcher::class), $cartManager);

        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-clear',
        ]);

        $result = $finalizer->finalize($session);

        expect($result->success)->toBeTrue()
            ->and($session->fresh()->status instanceof Completed)->toBeTrue();

        $failSession = CheckoutSession::create([
            'cart_id' => 'test-cart-clear-fail',
            'step_states' => ['fail_step' => 'pending'],
            'current_step' => 'fail_step',
        ]);

        $failRegistry = new CheckoutStepRegistry;

        $failRegistry->register('fail_step', new class implements CheckoutStepInterface
        {
            public function getIdentifier(): string
            {
                return 'fail_step';
            }

            public function getName(): string
            {
                return 'Fail Step';
            }

            public function validate(CheckoutSession $session): array
            {
                return [];
            }

            public function handle(CheckoutSession $session): StepResult
            {
                return StepResult::failed($this->getIdentifier(), 'Step failed intentionally');
            }

            public function canSkip(CheckoutSession $session): bool
            {
                return false;
            }

            public function rollback(CheckoutSession $session): void {}

            public function getDependencies(): array
            {
                return [];
            }
        });

        $failRegistry->setOrder(['fail_step']);

        $failService = new CheckoutService(
            stepRegistry: $failRegistry,
            events: app(Dispatcher::class),
            stepExecutor: new StepExecutor($failRegistry, app(Dispatcher::class)),
            finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
            paymentResolver: null,
        );

        $failResult = $failService->processCheckout($failSession);

        expect($failResult->success)->toBeFalse()
            ->and($failSession->fresh()->status instanceof Completed)->toBeFalse();
    });
});

describe('CheckoutState behavior methods', function (): void {
    it('pending state allows cancellation', function (): void {
        $session = new CheckoutSession;

        expect((new Pending($session))->canCancel())->toBeTrue()
            ->and((new Pending($session))->canModify())->toBeTrue()
            ->and((new Pending($session))->canRetryPayment())->toBeFalse()
            ->and((new Pending($session))->isTerminal())->toBeFalse();
    });

    it('processing state allows cancellation and modification', function (): void {
        $session = new CheckoutSession;

        expect((new Processing($session))->canCancel())->toBeTrue()
            ->and((new Processing($session))->canModify())->toBeTrue()
            ->and((new Processing($session))->canRetryPayment())->toBeFalse()
            ->and((new Processing($session))->isTerminal())->toBeFalse();
    });

    it('awaiting payment state allows cancellation but not modification', function (): void {
        $session = new CheckoutSession;

        expect((new AwaitingPayment($session))->canCancel())->toBeTrue()
            ->and((new AwaitingPayment($session))->canModify())->toBeFalse()
            ->and((new AwaitingPayment($session))->canRetryPayment())->toBeFalse()
            ->and((new AwaitingPayment($session))->isTerminal())->toBeFalse();
    });

    it('payment failed state allows retry and cancellation but not completion', function (): void {
        $session = new CheckoutSession;

        expect((new PaymentFailed($session))->canCancel())->toBeTrue()
            ->and((new PaymentFailed($session))->canModify())->toBeTrue()
            ->and((new PaymentFailed($session))->canRetryPayment())->toBeTrue()
            ->and((new PaymentFailed($session))->isTerminal())->toBeFalse();
    });

    it('completed state is terminal and cannot be modified or cancelled', function (): void {
        $session = new CheckoutSession;

        expect((new Completed($session))->canCancel())->toBeFalse()
            ->and((new Completed($session))->canModify())->toBeFalse()
            ->and((new Completed($session))->canRetryPayment())->toBeFalse()
            ->and((new Completed($session))->isTerminal())->toBeTrue();
    });

    it('cancelled state is terminal', function (): void {
        $session = new CheckoutSession;

        expect((new Cancelled($session))->canCancel())->toBeFalse()
            ->and((new Cancelled($session))->canModify())->toBeFalse()
            ->and((new Cancelled($session))->isTerminal())->toBeTrue();
    });
});
