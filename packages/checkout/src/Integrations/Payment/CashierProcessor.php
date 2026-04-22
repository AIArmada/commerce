<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations\Payment;

use AIArmada\Cashier\GatewayManager;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Cashier multi-gateway payment processor.
 *
 * Uses the Cashier package which wraps multiple payment gateways.
 * Requires a Billable customer model for payment processing.
 */
final class CashierProcessor implements PaymentProcessorInterface
{
    public function getIdentifier(): string
    {
        return 'cashier';
    }

    public function getName(): string
    {
        return 'Cashier (Multi-Gateway)';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        if (! class_exists(GatewayManager::class)) {
            return false;
        }

        $customer = $session->customer;

        return $customer instanceof Model && method_exists($customer, 'charge');
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        try {
            $customer = $session->customer;

            if (! $customer instanceof Model || ! method_exists($customer, 'charge')) {
                return PaymentResult::failed('Cashier requires a Billable customer model');
            }

            $gateway = app(GatewayManager::class)->gateway();

            /** @phpstan-ignore method.notFound */
            $payment = $customer->charge($request->amount, [
                'description' => $request->description,
                'redirect_urls' => [
                    'success' => $request->successUrl,
                    'failure' => $request->failureUrl,
                    'cancel' => $request->cancelUrl,
                ],
                'metadata' => $request->metadata,
            ]);

            $redirectUrl = $payment->checkout_url ?? $payment->redirect_url ?? null;
            $paymentId = $payment->id ?? 'unknown';

            if ($redirectUrl !== null) {
                return PaymentResult::pending($paymentId, $redirectUrl);
            }

            if (($payment->status ?? '') === 'completed') {
                return PaymentResult::success(
                    paymentId: $paymentId,
                    transactionId: $payment->transaction_id ?? null,
                    amount: $payment->amount ?? $request->amount,
                );
            }

            return PaymentResult::processing($paymentId);
        } catch (Throwable $e) {
            return PaymentResult::failed($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): PaymentResult
    {
        try {
            $paymentId = $payload['id'] ?? $payload['payment_id'] ?? null;
            $status = $payload['status'] ?? 'unknown';

            $paymentStatus = match ($status) {
                'completed', 'success', 'paid' => PaymentStatus::Completed,
                'failed', 'error' => PaymentStatus::Failed,
                'cancelled', 'canceled' => PaymentStatus::Cancelled,
                'refunded' => PaymentStatus::Refunded,
                default => PaymentStatus::Processing,
            };

            return new PaymentResult(
                status: $paymentStatus,
                paymentId: $paymentId,
                transactionId: $payload['transaction_id'] ?? null,
                message: $payload['message'] ?? null,
                gatewayResponse: $payload,
            );
        } catch (Throwable $e) {
            return PaymentResult::failed($e->getMessage());
        }
    }

    public function getRedirectUrl(CheckoutSession $session): ?string
    {
        return $session->payment_redirect_url;
    }

    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        try {
            $gateway = app(GatewayManager::class)->gateway();

            $refund = $gateway->refund($paymentId, $amount);

            return new PaymentResult(
                status: PaymentStatus::Refunded,
                paymentId: $paymentId,
                amount: $amount,
                message: 'Refund processed successfully',
                gatewayResponse: (array) $refund,
            );
        } catch (Throwable $e) {
            return PaymentResult::failed("Refund failed: {$e->getMessage()}", [], $paymentId);
        }
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        try {
            $payment = app(GatewayManager::class)
                ->gateway()
                ->findPayment($paymentId);

            if ($payment === null) {
                return PaymentResult::failed('Payment not found', [], $paymentId);
            }

            $status = match (true) {
                $payment->isSucceeded() => PaymentStatus::Completed,
                $payment->isFailed() => PaymentStatus::Failed,
                $payment->isCanceled() => PaymentStatus::Cancelled,
                $payment->isPending() => PaymentStatus::Pending,
                default => PaymentStatus::Processing,
            };

            return new PaymentResult(
                status: $status,
                paymentId: $payment->id(),
                redirectUrl: $payment->redirectUrl(),
                amount: $payment->rawAmount(),
                currency: $payment->currency(),
                message: $payment->status(),
                gatewayResponse: $payment->toArray(),
            );
        } catch (Throwable $e) {
            return PaymentResult::failed($e->getMessage(), [], $paymentId);
        }
    }
}
