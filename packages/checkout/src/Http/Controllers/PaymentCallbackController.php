<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Http\Controllers;

use AIArmada\Checkout\Actions\BuildCheckoutSessionViewData;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Completed;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly HandleCheckoutPaymentCallback $handleCallback,
    ) {}

    public function success(Request $request): RedirectResponse | View
    {
        $sessionId = $this->resolveSessionId($request);

        if ($sessionId === null) {
            return $this->respondFailure('Checkout session not found');
        }

        $outcome = $this->handleCallback->handle(
            sessionId: $sessionId,
            callbackType: 'success',
        );

        $session = $outcome->session;

        if ($outcome->sessionNotFound) {
            return $this->respondFailure('Checkout session not found');
        }

        if ($outcome->alreadyCompleted) {
            return $this->respondSuccess($session);
        }

        if ($outcome->result?->success) {
            return $this->respondSuccess($session);
        }

        return $this->respondFailure($outcome->result?->message ?? 'Payment verification failed', $session);
    }

    public function failure(Request $request): RedirectResponse | View
    {
        $sessionId = $this->resolveSessionId($request);

        if ($sessionId === null) {
            return $this->respondFailure('Checkout session not found');
        }

        $outcome = $this->handleCallback->handle(
            sessionId: $sessionId,
            callbackType: 'failure',
        );

        $session = $outcome->session;

        if ($outcome->sessionNotFound) {
            return $this->respondFailure('Checkout session not found');
        }

        if ($outcome->alreadyCompleted) {
            return $this->respondSuccess($session);
        }

        return $this->respondFailure('Payment failed', $session);
    }

    public function cancel(Request $request): RedirectResponse | View
    {
        $sessionId = $this->resolveSessionId($request);

        if ($sessionId === null) {
            return $this->respondFailure('Checkout session not found');
        }

        $outcome = $this->handleCallback->handle(
            sessionId: $sessionId,
            callbackType: 'cancel',
        );

        $session = $outcome->session;

        if ($outcome->sessionNotFound) {
            return $this->respondFailure('Checkout session not found');
        }

        if ($outcome->alreadyCompleted) {
            return $this->respondSuccess($session);
        }

        return $this->respondCancel($session);
    }

    private function resolveSessionId(Request $request): ?string
    {
        $queryParam = config('checkout.defaults.session_query_param', 'session');
        $sessionId = $request->query($queryParam) ?? $request->query('checkout_session_id');

        if ($sessionId === null) {
            return null;
        }

        $session = CheckoutSession::withoutOwnerScope()
            ->whereKey($sessionId)
            ->first();

        if ($session === null) {
            return null;
        }

        if (! $this->callbackRouteMatchesGateway($request, $session)) {
            return null;
        }

        $rateLimitKey = $this->callbackRateLimitKey($session);
        $maxAttempts = (int) config('checkout.payment.callback_rate_limit.max_attempts', 10);
        $decaySeconds = (int) config('checkout.payment.callback_rate_limit.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return null;
        }

        RateLimiter::hit($rateLimitKey, $decaySeconds);

        $providedToken = $request->query('checkout_callback_token')
            ?? $request->query('callback_token')
            ?? $request->query('token');
        $paymentData = $session->payment_data ?? [];
        $expectedToken = $paymentData['callback_token'] ?? null;
        $createdAt = $paymentData['callback_token_created_at'] ?? null;
        $consumedAt = $paymentData['callback_token_consumed_at'] ?? null;

        if (! is_string($providedToken)
            || ! is_string($expectedToken)
            || $expectedToken === ''
            || ! is_string($createdAt)
            || $createdAt === ''
            || (is_string($consumedAt) && ! ($session->status instanceof Completed))
        ) {
            return null;
        }

        try {
            $expiresAt = CarbonImmutable::parse($createdAt)
                ->addSeconds((int) config('checkout.payment.callback_token_ttl', 0));
        } catch (Throwable) {
            return null;
        }

        if ($expiresAt->isPast()) {
            return null;
        }

        return hash_equals($expectedToken, $providedToken) ? $sessionId : null;
    }

    private function callbackRouteMatchesGateway(Request $request, CheckoutSession $session): bool
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return true;
        }

        $routeName = $route->getName();

        if (! is_string($routeName)) {
            return false;
        }

        $segments = explode('.', $routeName);
        $gateway = $segments[2] ?? null;

        $configuredGateways = config('checkout.routes.callbacks.success', []);

        if (! is_array($configuredGateways)
            || ! is_string($gateway)
            || ! array_key_exists($gateway, $configuredGateways)
        ) {
            return false;
        }

        return (string) $session->selected_payment_gateway === $gateway;
    }

    private function callbackRateLimitKey(CheckoutSession $session): string
    {
        return 'checkout:callback:' . hash('sha256', implode('|', [
            (string) ($session->owner_type ?? 'global'),
            (string) ($session->owner_id ?? 'global'),
            (string) $session->getKey(),
        ]));
    }

    private function respondSuccess(CheckoutSession $session): RedirectResponse | View
    {
        if ($this->shouldRenderView()) {
            $viewName = config('checkout.views.routes.success', 'checkout::success');

            return view($viewName, BuildCheckoutSessionViewData::run($session));
        }

        return $this->redirectToOrderSuccess($session);
    }

    private function respondFailure(string $message, ?CheckoutSession $session = null): RedirectResponse | View
    {
        if ($this->shouldRenderView()) {
            $viewName = config('checkout.views.routes.failure', 'checkout::failure');
            $viewData = $session ? BuildCheckoutSessionViewData::run($session) : [];
            $viewData['error'] = $message;

            return view($viewName, $viewData);
        }

        return $this->redirectToFailure($message, $session);
    }

    private function respondCancel(?CheckoutSession $session): RedirectResponse | View
    {
        if ($this->shouldRenderView()) {
            $viewName = config('checkout.views.routes.cancel', 'checkout::cancel');
            $viewData = $session ? BuildCheckoutSessionViewData::run($session) : [];
            $viewData['message'] = 'Payment was cancelled';

            return view($viewName, $viewData);
        }

        $url = config('checkout.redirects.cancel', '/checkout/cancelled');

        if ($session !== null) {
            $url = str_replace('{order_id}', $session->order_id ?? '', $url);
            $url = str_replace('{session_id}', $session->id, $url);
        }

        return redirect($url)
            ->with('checkout_session_id', $session?->id)
            ->with('message', 'Payment was cancelled');
    }

    private function shouldRenderView(): bool
    {
        return config('checkout.response_mode', 'redirect') === 'view'
            && config('checkout.views.enabled', true);
    }

    private function redirectToOrderSuccess(CheckoutSession $session): RedirectResponse
    {
        $url = config('checkout.redirects.success', '/orders/{order_id}');
        $url = str_replace('{order_id}', $session->order_id ?? '', $url);
        $url = str_replace('{session_id}', $session->id, $url);

        return redirect($url);
    }

    private function redirectToFailure(string $message, ?CheckoutSession $session = null): RedirectResponse
    {
        $url = config('checkout.redirects.failure', '/checkout/failed');

        if ($session !== null) {
            $url = str_replace('{order_id}', $session->order_id ?? '', $url);
            $url = str_replace('{session_id}', $session->id, $url);
        }

        $redirect = redirect($url)->with('error', $message);

        if ($session !== null) {
            $redirect->with('checkout_session_id', $session->id);
        }

        return $redirect;
    }
}
