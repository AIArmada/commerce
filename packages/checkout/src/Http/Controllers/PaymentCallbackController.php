<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Http\Controllers;

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
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
    public function success(Request $request): RedirectResponse
    {
        $session = $this->resolveSession($request);

        if ($session === null) {
            return $this->redirectToFailure('Checkout session not found');
        }

        // If already completed, redirect to success page
        if ($session->status instanceof Completed) {
            return $this->redirectToOrderSuccess($session);
        }

        // Verify payment and complete checkout
        $result = $this->checkoutService->handlePaymentCallback($session, 'success');

        if ($result->success) {
            return $this->redirectToOrderSuccess($session->fresh());
        }

        return $this->redirectToFailure($result->message ?? 'Payment verification failed', $session);
    }

    /**
     * Handle failed payment redirect from gateway.
     */
    public function failure(Request $request): RedirectResponse
    {
        $session = $this->resolveSession($request);

        if ($session === null) {
            return $this->redirectToFailure('Checkout session not found');
        }

        // Mark payment as failed if still awaiting
        if ($session->status instanceof AwaitingPayment) {
            $this->checkoutService->handlePaymentCallback($session, 'failure');
        }

        return $this->redirectToFailure('Payment failed', $session);
    }

    /**
     * Handle cancelled payment redirect from gateway.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $session = $this->resolveSession($request);

        if ($session === null) {
            return $this->redirectToFailure('Checkout session not found');
        }

        // Mark as cancelled if still awaiting
        if ($session->status instanceof AwaitingPayment) {
            $this->checkoutService->handlePaymentCallback($session, 'cancel');
        }

        return redirect(config('checkout.redirects.cancel', '/checkout/cancelled'))
            ->with('checkout_session_id', $session->id)
            ->with('message', 'Payment was cancelled');
    }

    private function resolveSession(Request $request): ?CheckoutSession
    {
        $queryParam = config('checkout.defaults.session_query_param', 'session');
        $sessionId = $request->query($queryParam) ?? $request->query('checkout_session_id');

        if ($sessionId === null) {
            return null;
        }

        return CheckoutSession::find($sessionId);
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

        $redirect = redirect($url)->with('error', $message);

        if ($session !== null) {
            $redirect->with('checkout_session_id', $session->id);
        }

        return $redirect;
    }
}
