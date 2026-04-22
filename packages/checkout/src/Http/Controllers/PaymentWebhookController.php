<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Http\Controllers;

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly CheckoutServiceInterface $checkoutService,
    ) {}

    /**
     * Handle payment webhook from gateway.
     *
     * This controller provides a unified webhook endpoint for all payment gateways.
     * It extracts the checkout session from the webhook payload and processes accordingly.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Checkout webhook received', ['payload' => $payload]);

        // Extract session ID from reference/metadata
        $sessionId = $this->extractSessionId($payload);

        if ($sessionId === null) {
            Log::warning('Webhook missing session reference', ['payload' => $payload]);

            return response()->json(['status' => 'ignored', 'reason' => 'no_session_reference']);
        }

        $session = CheckoutSession::withoutOwnerScope()->find($sessionId);

        if ($session === null) {
            Log::warning('Webhook session not found', ['session_id' => $sessionId]);

            return response()->json(['status' => 'ignored', 'reason' => 'session_not_found']);
        }

        // Already completed - acknowledge but don't process
        if ($session->status instanceof Completed) {
            return response()->json(['status' => 'acknowledged', 'reason' => 'already_completed']);
        }

        // Determine payment outcome from webhook
        $paymentStatus = $this->extractPaymentStatus($payload);

        $isInPaymentState = $session->status instanceof AwaitingPayment
            || $session->status instanceof PaymentProcessing
            || $session->status instanceof Processing;

        $canCancelFromPending = $session->status instanceof Pending
            && in_array($paymentStatus, [PaymentStatus::Failed, PaymentStatus::Cancelled], true);

        // Only process if in awaiting/processing payment state, or if a verified
        // failure/cancel notification races with the payment-state persistence.
        if (! $isInPaymentState && ! $canCancelFromPending) {
            Log::info('Webhook for session in unexpected state', [
                'session_id' => $sessionId,
                'status' => $session->status->name(),
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'invalid_state']);
        }

        $callbackType = match ($paymentStatus) {
            PaymentStatus::Completed => 'success',
            PaymentStatus::Failed => 'failure',
            PaymentStatus::Cancelled => 'cancel',
            default => null,
        };

        if ($callbackType === null) {
            // Pending/processing webhooks - just acknowledge
            return response()->json(['status' => 'acknowledged', 'reason' => 'pending_status']);
        }

        // Process the callback
        $result = $this->checkoutService->handlePaymentCallback($session, $callbackType, $payload);

        return response()->json([
            'status' => $result->success ? 'success' : 'processed',
            'checkout_completed' => $result->success,
            'order_id' => $result->orderId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSessionId(array $payload): ?string
    {
        // CHIP format
        if (isset($payload['reference'])) {
            return $payload['reference'];
        }

        // Metadata format (Stripe-style)
        if (isset($payload['metadata']['checkout_session_id'])) {
            return $payload['metadata']['checkout_session_id'];
        }

        // Data object format
        if (isset($payload['data']['object']['metadata']['checkout_session_id'])) {
            return $payload['data']['object']['metadata']['checkout_session_id'];
        }

        // Client reference ID (Stripe checkout)
        if (isset($payload['data']['object']['client_reference_id'])) {
            return $payload['data']['object']['client_reference_id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentStatus(array $payload): PaymentStatus
    {
        // CHIP status
        $status = $payload['status'] ?? $payload['data']['object']['status'] ?? null;

        return match ($status) {
            'paid', 'completed', 'succeeded', 'complete' => PaymentStatus::Completed,
            'pending', 'created', 'processing' => PaymentStatus::Pending,
            'failed', 'error', 'payment_failed' => PaymentStatus::Failed,
            'cancelled', 'canceled', 'expired' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Processing,
        };
    }
}
