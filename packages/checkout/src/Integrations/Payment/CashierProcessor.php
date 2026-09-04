<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations\Payment;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Checkout\Contracts\ProviderAwarePaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use Throwable;

/**
 * Cashier multi-gateway payment processor.
 *
 * Uses the Cashier package which wraps multiple payment gateways.
 * Requires a Billable customer model for payment processing.
 */
final class CashierProcessor implements ProviderAwarePaymentProcessorInterface
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

        return $this->resolveBillable($session) instanceof BillableContract;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        try {
            $billable = $this->resolveBillable($session);

            if ($billable === null) {
                return PaymentResult::failed('Cashier requires a billable customer model');
            }

            $options = $this->buildChargeOptions($request);
            $provider = $this->requestedProvider($request);
            $gateway = app(GatewayManager::class)->gateway($provider);
            $provider = $gateway->name();
            $payment = $gateway->charge($billable, $request->amount, $request->paymentMethod, $options);

            return $this->toPaymentResult($payment, $provider);
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
            $status = is_string($status) ? mb_strtolower(mb_trim($status)) : 'unknown';

            $paymentStatus = match ($status) {
                'completed', 'success', 'paid' => PaymentStatus::Completed,
                'failed', 'error' => PaymentStatus::Failed,
                'cancelled', 'canceled' => PaymentStatus::Cancelled,
                'refunded' => PaymentStatus::Refunded,
                'partially_refunded' => PaymentStatus::PartiallyRefunded,
                'pending_refund', 'attempted_refund' => PaymentStatus::Processing,
                default => PaymentStatus::Processing,
            };

            return new PaymentResult(
                status: $paymentStatus,
                paymentId: $paymentId,
                transactionId: $payload['transaction_id'] ?? null,
                message: $payload['message'] ?? null,
                gatewayResponse: $payload,
                provider: is_string($payload['provider'] ?? null) ? $payload['provider'] : null,
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
        return $this->refundForProvider('', $paymentId, $amount, $reason);
    }

    public function refundForProvider(
        string $provider,
        string $paymentId,
        int $amount,
        ?string $reason = null,
    ): PaymentResult {
        try {
            $gateway = app(GatewayManager::class)->gateway($provider !== '' ? $provider : null);

            $refund = $gateway->refund($paymentId, $amount);
            $response = $refund instanceof PaymentContract ? $refund->toArray() : (array) $refund;
            $status = $refund instanceof PaymentContract ? $refund->status() : data_get($response, 'status');
            $status = is_string($status) ? mb_strtolower(mb_trim($status)) : null;
            $paymentStatus = match ($status) {
                'refunded' => PaymentStatus::Refunded,
                'partially_refunded' => PaymentStatus::PartiallyRefunded,
                'pending_refund', 'attempted_refund' => PaymentStatus::Processing,
                'failed', 'error', 'blocked' => PaymentStatus::Failed,
                // A response that cannot be classified is not proof that the
                // provider completed the refund. PaymentContract implementations
                // may expose the provider's definitive refund state separately.
                default => $refund instanceof PaymentContract && $refund->isRefunded()
                    ? PaymentStatus::Refunded
                    : PaymentStatus::Processing,
            };

            $transactionId = $this->refundTransactionId(
                $refund,
                $response,
                $paymentId,
            );

            return new PaymentResult(
                status: $paymentStatus,
                paymentId: $paymentId,
                transactionId: $transactionId,
                amount: $amount,
                message: $paymentStatus === PaymentStatus::Processing
                    ? 'Refund is being processed by the payment provider'
                    : ($paymentStatus === PaymentStatus::Failed ? 'Payment provider could not process the refund' : 'Refund processed successfully'),
                gatewayResponse: $response,
                provider: $gateway->name(),
            );
        } catch (Throwable $e) {
            return PaymentResult::failed("Refund failed: {$e->getMessage()}", [], $paymentId);
        }
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        return $this->checkStatusForProvider('', $paymentId);
    }

    public function checkStatusForProvider(string $provider, string $paymentId): PaymentResult
    {
        try {
            $payment = app(GatewayManager::class)
                ->gateway($provider !== '' ? $provider : null)
                ->findPayment($paymentId);

            if ($payment === null) {
                return PaymentResult::failed('Payment not found', [], $paymentId);
            }

            $providerStatus = mb_strtolower($payment->status());
            $status = $this->mapPaymentStatus($payment, $providerStatus);

            return new PaymentResult(
                status: $status,
                paymentId: $payment->id(),
                redirectUrl: $payment->redirectUrl(),
                amount: $payment->rawAmount(),
                currency: $payment->currency(),
                message: $providerStatus,
                gatewayResponse: $payment->toArray(),
                provider: $payment->gateway(),
            );
        } catch (Throwable $e) {
            return PaymentResult::failed($e->getMessage(), [], $paymentId);
        }
    }

    private function mapPaymentStatus(PaymentContract $payment, ?string $providerStatus = null): PaymentStatus
    {
        $providerStatus ??= mb_strtolower($payment->status());

        return match ($providerStatus) {
            'refunded' => PaymentStatus::Refunded,
            'partially_refunded' => PaymentStatus::PartiallyRefunded,
            'pending_refund', 'attempted_refund' => PaymentStatus::Processing,
            'failed', 'error' => PaymentStatus::Failed,
            'cancelled', 'canceled', 'expired' => PaymentStatus::Cancelled,
            'pending', 'created' => PaymentStatus::Pending,
            default => match (true) {
                $payment->isSucceeded() => PaymentStatus::Completed,
                $payment->isFailed() => PaymentStatus::Failed,
                $payment->isCanceled() => PaymentStatus::Cancelled,
                $payment->isPending() => PaymentStatus::Pending,
                default => PaymentStatus::Processing,
            },
        };
    }

    private function resolveBillable(CheckoutSession $session): ?BillableContract
    {
        return $session->billable instanceof BillableContract ? $session->billable : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChargeOptions(PaymentRequest $request): array
    {
        $description = $request->description ?? 'Payment';

        return [
            'description' => $description,
            'product_name' => $description,
            'success_url' => $request->successUrl,
            'failure_url' => $request->failureUrl,
            'cancel_url' => $request->cancelUrl,
            'currency' => $request->currency,
            'reference' => $description,
            'metadata' => $request->metadata,
        ];
    }

    private function toPaymentResult(PaymentContract $payment, string $provider): PaymentResult
    {
        $response = $payment->toArray();
        $paymentId = $payment->id();
        $redirectUrl = $payment->redirectUrl();
        $status = mb_strtolower($payment->status());
        $amount = $payment->rawAmount();
        $currency = $payment->currency();
        $transactionId = $this->stringValue(
            $response['transaction_id'] ?? $response['reference_generated'] ?? $response['reference'] ?? null,
        );

        if ($paymentId === '') {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                message: 'Cashier did not return a payment identifier.',
                amount: $amount,
                currency: $currency,
                gatewayResponse: $response,
                provider: $provider,
            );
        }

        if ($redirectUrl !== null && $redirectUrl !== '') {
            return new PaymentResult(
                status: PaymentStatus::Pending,
                paymentId: $paymentId,
                redirectUrl: $redirectUrl,
                message: 'Payment pending - redirect required',
                amount: $amount,
                currency: $currency,
                gatewayResponse: $response,
                provider: $provider,
            );
        }

        $paymentStatus = $this->paymentStatus($payment, $status);

        return new PaymentResult(
            status: $paymentStatus,
            paymentId: $paymentId,
            transactionId: $transactionId,
            message: $this->stringValue($response['message'] ?? null) ?? ($status !== '' ? $status : null),
            amount: $amount,
            currency: $currency,
            gatewayResponse: $response,
            provider: $provider,
        );
    }

    private function requestedProvider(PaymentRequest $request): ?string
    {
        $provider = is_string($request->provider) ? mb_trim($request->provider) : '';

        if ($provider !== '' && $provider !== $this->getIdentifier()) {
            return $provider;
        }

        return null;
    }

    /**
     * Extract a provider-side refund operation id when one is exposed. Some
     * gateways return the original payment object after creating a refund;
     * that original id is deliberately excluded here.
     *
     * @param  array<string, mixed>  $response
     */
    private function refundTransactionId(mixed $refund, array $response, string $paymentId): ?string
    {
        $candidates = [
            data_get($response, 'refund_id'),
            data_get($response, 'transaction_id'),
            data_get($response, 'refund.id'),
            data_get($response, 'id'),
            $refund instanceof PaymentContract ? $refund->id() : null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $candidate = mb_trim((string) $candidate);

            if ($candidate !== '' && $candidate !== $paymentId) {
                return $candidate;
            }
        }

        return null;
    }

    private function paymentStatus(PaymentContract $payment, string $status): PaymentStatus
    {
        if ($payment->isSucceeded() || in_array($status, ['completed', 'succeeded', 'success', 'paid', 'cleared', 'settled'], true)) {
            return PaymentStatus::Completed;
        }

        if ($payment->isFailed() || in_array($status, ['failed', 'error', 'blocked'], true)) {
            return PaymentStatus::Failed;
        }

        if ($payment->isCanceled() || in_array($status, ['cancelled', 'canceled', 'expired'], true)) {
            return PaymentStatus::Cancelled;
        }

        if ($payment->isPending() || in_array($status, ['pending', 'created', 'viewed', 'requires_action', 'requires_confirmation'], true)) {
            return PaymentStatus::Pending;
        }

        return PaymentStatus::Processing;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = mb_trim((string) $value);

        return $value === '' ? null : $value;
    }
}
