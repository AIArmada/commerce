<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Http\Controllers;

use AIArmada\Checkout\Actions\BuildCheckoutSessionViewData;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\States\Completed;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

final class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly HandleCheckoutPaymentCallback $handleCallback,
        private readonly ?PaymentAttemptManager $attemptManager = null,
    ) {}

    public function success(Request $request): RedirectResponse | View
    {
        $callback = $this->resolveCallback($request);

        if ($callback === null) {
            return $this->respondFailure('Checkout session not found');
        }

        $outcome = $this->handleCallback->handle(
            sessionId: $callback['id'],
            callbackType: 'success',
            expectedGateways: $callback['gateway'] !== null ? [$callback['gateway']] : null,
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
        $callback = $this->resolveCallback($request);

        if ($callback === null) {
            return $this->respondFailure('Checkout session not found');
        }

        $outcome = $this->handleCallback->handle(
            sessionId: $callback['id'],
            callbackType: 'failure',
            expectedGateways: $callback['gateway'] !== null ? [$callback['gateway']] : null,
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
        $callback = $this->resolveCallback($request);

        if ($callback === null) {
            return $this->respondFailure('Checkout session not found');
        }

        $outcome = $this->handleCallback->handle(
            sessionId: $callback['id'],
            callbackType: 'cancel',
            expectedGateways: $callback['gateway'] !== null ? [$callback['gateway']] : null,
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

    /**
     * @return array{id: string, gateway: string|null}|null
     */
    private function resolveCallback(Request $request): ?array
    {
        $queryParam = config('checkout.defaults.session_query_param', 'session');
        $sessionId = $request->query($queryParam) ?? $request->query('checkout_session_id');

        if (! is_string($sessionId) || ! Str::isUuid($sessionId)) {
            return null;
        }

        $session = CheckoutSession::withoutOwnerScope()
            ->whereKey($sessionId)
            ->first();

        if ($session === null) {
            return null;
        }

        $route = $this->callbackRouteContext($request);

        // A route that names a configured callback gateway is authoritative:
        // the frozen active attempt controls it, not the mutable selection.
        if ($route['matched'] && ! $this->callbackRouteMatchesGateway($session, $route['gateway'])) {
            return null;
        }

        $rateLimitKey = $this->callbackRateLimitKey($request, $session);
        $maxAttempts = (int) config('checkout.payment.callback_rate_limit.max_attempts', 10);
        $decaySeconds = (int) config('checkout.payment.callback_rate_limit.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return null;
        }

        // Only failed token validations consume the budget. Counting every
        // hit up front lets anyone holding a session id burn the budget and
        // block legitimate gateway callbacks.
        if (! $this->callbackTokenValid($request, $session)) {
            RateLimiter::hit($rateLimitKey, $decaySeconds);

            return null;
        }

        return [
            'id' => $sessionId,
            'gateway' => $route['matched'] ? $route['gateway'] : null,
        ];
    }

    private function callbackTokenValid(Request $request, CheckoutSession $session): bool
    {
        $providedToken = $request->query('checkout_callback_token')
            ?? $request->query('callback_token')
            ?? $request->query('token');

        if (! is_string($providedToken)) {
            return false;
        }

        $paymentData = $session->payment_data ?? [];
        $expectedToken = $paymentData['callback_token'] ?? null;
        $createdAt = $paymentData['callback_token_created_at'] ?? null;
        $consumedAt = $paymentData['callback_token_consumed_at'] ?? null;

        if (! is_string($expectedToken)
            || $expectedToken === ''
            || ! is_string($createdAt)
            || $createdAt === ''
            || (is_string($consumedAt) && ! ($session->status instanceof Completed))
        ) {
            return false;
        }

        try {
            $expiresAt = CarbonImmutable::parse($createdAt)
                ->addSeconds((int) config('checkout.payment.callback_token_ttl', 0));
        } catch (Throwable) {
            return false;
        }

        if ($expiresAt->isPast()) {
            return false;
        }

        return hash_equals($expectedToken, $providedToken);
    }

    /**
     * @return array{matched: bool, gateway: string|null}
     */
    private function callbackRouteContext(Request $request): array
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return ['matched' => false, 'gateway' => null];
        }

        $routeName = $route->getName();

        if (! is_string($routeName)) {
            return ['matched' => true, 'gateway' => null];
        }

        $segments = explode('.', $routeName);
        $gateway = $segments[2] ?? null;
        $callbackType = $segments[3] ?? null;

        if (! is_string($callbackType) || ! in_array($callbackType, ['success', 'failure', 'cancel'], true)) {
            return ['matched' => true, 'gateway' => null];
        }

        $configuredGateways = config("checkout.routes.callbacks.{$callbackType}", []);

        if (! is_array($configuredGateways)
            || ! is_string($gateway)
            || ! array_key_exists($gateway, $configuredGateways)
        ) {
            return ['matched' => true, 'gateway' => null];
        }

        return ['matched' => true, 'gateway' => $gateway];
    }

    private function callbackRouteMatchesGateway(CheckoutSession $session, ?string $gateway): bool
    {
        if ($gateway === null) {
            return false;
        }

        return $this->attemptManager()->currentAttemptGateway($session) === $gateway;
    }

    private function attemptManager(): PaymentAttemptManager
    {
        return $this->attemptManager ?? app(PaymentAttemptManager::class);
    }

    private function callbackRateLimitKey(Request $request, CheckoutSession $session): string
    {
        return 'checkout:callback:' . hash('sha256', implode('|', [
            (string) ($session->owner_type ?? 'global'),
            (string) ($session->owner_id ?? 'global'),
            (string) $session->getKey(),
            (string) $request->ip(),
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
