<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Http\Controllers\PaymentCallbackController;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

it('rejects an expired callback token', function (): void {
    config()->set('checkout.payment.callback_token_ttl', 3600);
    config()->set('checkout.redirects.failure', '/checkout/failed');

    $session = CheckoutSession::create([
        'cart_id' => 'callback-token-expired',
        'status' => AwaitingPayment::class,
        'selected_payment_gateway' => 'chip',
        'payment_data' => [
            'callback_token' => 'expired-token',
            'callback_token_created_at' => CarbonImmutable::now()->subHours(2)->toIso8601String(),
        ],
    ]);

    $response = app(PaymentCallbackController::class)->success(Request::create(
        '/checkout/payment/chip/success',
        'GET',
        [
            'session' => $session->getKey(),
            'checkout_callback_token' => 'expired-token',
        ],
    ));

    expect($response->getTargetUrl())->toContain('/checkout/failed')
        ->and($session->fresh()->status)->toBeInstanceOf(AwaitingPayment::class);
});

it('consumes a successful callback token without reprocessing a replay', function (): void {
    config()->set('checkout.redirects.success', '/orders/{order_id}');
    config()->set('checkout.redirects.failure', '/checkout/failed');

    $orderId = (string) Str::uuid();
    $checkoutService = mock(CheckoutServiceInterface::class);
    $checkoutService->shouldReceive('handlePaymentCallback')
        ->once()
        ->andReturnUsing(function (CheckoutSession $session) use ($orderId): CheckoutResult {
            $session->transitionStatus(Completed::class);
            $session->update([
                'order_id' => $orderId,
                'payment_redirect_url' => null,
            ]);

            return CheckoutResult::success($session, $orderId);
        });
    app()->instance(CheckoutServiceInterface::class, $checkoutService);

    $session = CheckoutSession::create([
        'cart_id' => 'callback-token-single-use',
        'status' => AwaitingPayment::class,
        'selected_payment_gateway' => 'chip',
        'payment_data' => [
            'callback_token' => 'single-use-token',
            'callback_token_created_at' => CarbonImmutable::now()->toIso8601String(),
        ],
    ]);

    $request = static function () use ($session): Request {
        return Request::create(
            '/checkout/payment/chip/success',
            'GET',
            [
                'session' => $session->getKey(),
                'checkout_callback_token' => 'single-use-token',
            ],
        );
    };

    $controller = app(PaymentCallbackController::class);
    $firstResponse = $controller->success($request());
    $secondResponse = $controller->success($request());

    expect($firstResponse->getTargetUrl())->toContain('/orders/' . $orderId)
        ->and($secondResponse->getTargetUrl())->toContain('/orders/' . $orderId)
        ->and($session->fresh()->status)->toBeInstanceOf(Completed::class)
        ->and($session->fresh()->payment_data['callback_token_consumed_at'] ?? null)->toBeString();
});

it('rate-limits callback attempts per session before token processing', function (): void {
    config()->set('checkout.payment.callback_rate_limit.max_attempts', 1);
    config()->set('checkout.payment.callback_rate_limit.decay_seconds', 600);
    config()->set('checkout.redirects.failure', '/checkout/failed');

    $checkoutService = mock(CheckoutServiceInterface::class);
    $checkoutService->shouldReceive('handlePaymentCallback')->never();
    app()->instance(CheckoutServiceInterface::class, $checkoutService);

    $session = CheckoutSession::create([
        'cart_id' => 'callback-token-rate-limited',
        'status' => AwaitingPayment::class,
        'selected_payment_gateway' => 'chip',
        'payment_data' => [
            'callback_token' => 'rate-limit-token',
            'callback_token_created_at' => CarbonImmutable::now()->toIso8601String(),
        ],
    ]);

    $controller = app(PaymentCallbackController::class);
    $firstResponse = $controller->success(Request::create(
        '/checkout/payment/chip/success',
        'GET',
        [
            'session' => $session->getKey(),
            'checkout_callback_token' => 'wrong-token',
        ],
    ));
    $secondResponse = $controller->success(Request::create(
        '/checkout/payment/chip/success',
        'GET',
        [
            'session' => $session->getKey(),
            'checkout_callback_token' => 'rate-limit-token',
        ],
    ));

    expect($firstResponse->getTargetUrl())->toContain('/checkout/failed')
        ->and($secondResponse->getTargetUrl())->toContain('/checkout/failed')
        ->and($session->fresh()->status)->toBeInstanceOf(AwaitingPayment::class);
});
