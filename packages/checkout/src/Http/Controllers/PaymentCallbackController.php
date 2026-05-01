<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Http\Controllers;

use AIArmada\Checkout\Actions\BuildCheckoutSessionViewData;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly CheckoutServiceInterface $checkoutService,
    ) {}

    /**
     * Handle successful payment redirect from gateway.
     */
    public function success(Request $request): RedirectResponse | View
    {
        $session = $this->resolveSession($request);

        if ($session === null) {
            return $this->respondFailure('Checkout session not found');
        }

        // If already completed, respond with success
        if ($session->status instanceof Completed) {
            return $this->respondSuccess($session);
        }

        // Verify payment via gateway API — never trust redirect query params as payment proof.
        // Query params are user-controlled; passing them would allow forging ?status=paid.
        $result = $this->checkoutService->handlePaymentCallback($session, 'success', []);

        if ($result->success) {
            return $this->respondSuccess($session->fresh());
        }

        return $this->respondFailure($result->message ?? 'Payment verification failed', $session);
    }

    /**
     * Handle failed payment redirect from gateway.
     */
    public function failure(Request $request): RedirectResponse | View
    {
        $session = $this->resolveSession($request);

        if ($session === null) {
            return $this->respondFailure('Checkout session not found');
        }

        // Mark payment as failed if still awaiting
        if (
            $session->status instanceof Pending
            ||
            $session->status instanceof AwaitingPayment
            || $session->status instanceof PaymentProcessing
            || $session->status instanceof Processing
        ) {
            $this->checkoutService->handlePaymentCallback($session, 'failure');
        }

        return $this->respondFailure('Payment failed', $session);
    }

    /**
     * Handle cancelled payment redirect from gateway.
     */
    public function cancel(Request $request): RedirectResponse | View
    {
        $session = $this->resolveSession($request);

        if ($session === null) {
            return $this->respondFailure('Checkout session not found');
        }

        // Mark as cancelled if still awaiting
        if (
            $session->status instanceof Pending
            ||
            $session->status instanceof AwaitingPayment
            || $session->status instanceof PaymentProcessing
            || $session->status instanceof Processing
        ) {
            $this->checkoutService->handlePaymentCallback($session, 'cancel');
        }

        return $this->respondCancel($session);
    }

    private function resolveSession(Request $request): ?CheckoutSession
    {
        $queryParam = config('checkout.defaults.session_query_param', 'session');
        $sessionId = $request->query($queryParam) ?? $request->query('checkout_session_id');

        if ($sessionId === null) {
            return null;
        }

        // Intentional cross-tenant lookup: payment gateways redirect back without owner context.
        // Access is guarded below by hash_equals() on the per-session callback_token stored in payment_data.
        $session = CheckoutSession::withoutOwnerScope()->find($sessionId);

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

        return hash_equals($expectedToken, $providedToken) ? $session : null;
    }

    /**
     * Respond to successful payment - either redirect or render view.
     */
    private function respondSuccess(CheckoutSession $session): RedirectResponse | View
    {
        if ($this->shouldRenderView()) {
            $viewName = config('checkout.views.routes.success', 'checkout::success');

            return view($viewName, BuildCheckoutSessionViewData::run($session));
        }

        return $this->redirectToOrderSuccess($session);
    }

    /**
     * Respond to failed payment - either redirect or render view.
     */
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

    /**
     * Respond to cancelled payment - either redirect or render view.
     */
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

    /**
     * Check if we should render views instead of redirecting.
     */
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
