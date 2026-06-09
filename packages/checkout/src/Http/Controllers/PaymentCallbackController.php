<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Http\Controllers;

use AIArmada\Checkout\Actions\BuildCheckoutSessionViewData;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

        $providedToken = $request->query('checkout_callback_token')
            ?? $request->query('callback_token')
            ?? $request->query('token');
        $expectedToken = $session->payment_data['callback_token'] ?? null;

        if (! is_string($providedToken) || ! is_string($expectedToken) || $expectedToken === '') {
            return null;
        }

        return hash_equals($expectedToken, $providedToken) ? $sessionId : null;
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
