<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Http\Controllers\PaymentCallbackController;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\CreateOrderStep;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

describe('PaymentCallbackController', function (): void {
    beforeEach(function (): void {
        $this->controller = app(PaymentCallbackController::class);

        config()->set('checkout.redirects.success', '/orders/{order_id}');
        config()->set('checkout.redirects.failure', '/checkout/failed');
        config()->set('checkout.redirects.cancel', '/checkout/cancelled');
    });

    it('redirects to failure when session not found on success', function (): void {
        $request = Request::create('/checkout/payment/success', 'GET', ['session' => 'nonexistent']);

        $response = $this->controller->success($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed')
            ->and($response->getSession()->get('error'))->toBe('Checkout session not found');
    });

    it('redirects to failure when session not found on failure callback', function (): void {
        $request = Request::create('/checkout/payment/failure', 'GET', ['session' => 'nonexistent']);

        $response = $this->controller->failure($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed')
            ->and($response->getSession()->get('error'))->toBe('Checkout session not found');
    });

    it('redirects to cancel page when session not found on cancel', function (): void {
        $request = Request::create('/checkout/payment/cancel', 'GET', ['session' => 'nonexistent']);

        $response = $this->controller->cancel($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed');
    });

    it('resolves session via session query parameter', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-123',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/cancel', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = $this->controller->cancel($request);

        // Should find session and redirect to cancel (not failure)
        expect($response->getTargetUrl())->toContain('/checkout/cancelled');
    });

    it('resolves session via checkout_session_id query parameter', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-alt',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/cancel', 'GET', [
            'checkout_session_id' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = $this->controller->cancel($request);

        expect($response->getTargetUrl())->toContain('/checkout/cancelled');
    });

    it('includes session id in cancel redirect', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-cancel',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/cancel', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = $this->controller->cancel($request);

        expect($response->getSession()->get('checkout_session_id'))->toBe($session->id)
            ->and($response->getSession()->get('message'))->toBe('Payment was cancelled');
    });

    it('includes session id in failure redirect', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-fail',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/failure', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'valid-callback-token',
        ]);

        $response = $this->controller->failure($request);

        expect($response->getSession()->get('checkout_session_id'))->toBe($session->id)
            ->and($response->getSession()->get('error'))->toBe('Payment failed');
    });

    it('rejects callbacks without a valid token', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-cart-invalid-token',
            'selected_payment_gateway' => 'chip',
            'payment_data' => ['callback_token' => 'valid-callback-token'],
        ]);

        $request = Request::create('/checkout/payment/cancel', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'invalid-token',
        ]);

        $response = $this->controller->cancel($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed')
            ->and($response->getSession()->get('error'))->toBe('Checkout session not found');
    });

    it('does not verify payment via user-controlled query params (prevents forged status=paid)', function (): void {
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

        $response = $this->controller->success($request);

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

    it('passes customer type and finalized pricing data to the order service', function (): void {
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

        $orderService->shouldReceive('createOrder')
            ->once()
            ->withArgs(function (array $orderData, array $items) use ($customer): bool {
                expect($orderData['customer_id'])->toBe($customer->id)
                    ->and($orderData['customer_type'])->toBe($customer->getMorphClass())
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

        $orderService->shouldReceive('createOrder')->once()->andReturn($order);

        app()->instance(OrderServiceInterface::class, $orderService);

        $voucherService = mock(VoucherServiceInterface::class);
        $voucherService->shouldReceive('redeem')
            ->once()
            ->with('WELCOME10', $order->id);

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
});

describe('CheckoutService', function (): void {
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
