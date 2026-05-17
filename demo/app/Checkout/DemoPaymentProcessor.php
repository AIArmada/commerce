<?php

declare(strict_types=1);

namespace App\Checkout;

use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Support\Str;

final class DemoPaymentProcessor implements PaymentProcessorInterface
{
    public function getIdentifier(): string
    {
        return 'demo';
    }

    public function getName(): string
    {
        return 'Demo Payment Simulator';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        return true;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        $paymentId = 'demo-pay-'.Str::lower(Str::random(20));
        $redirectUrl = route('demo.payment.show', ['checkoutSession' => $session]);

        return new PaymentResult(
            status: PaymentStatus::Pending,
            paymentId: $paymentId,
            redirectUrl: $redirectUrl,
            message: 'Demo payment pending - redirect required',
            amount: $request->amount,
            currency: $request->currency,
            gatewayResponse: [
                'gateway' => 'demo',
                'checkout_session_id' => $session->id,
                'redirect_url' => $redirectUrl,
                'success_url' => $request->successUrl,
                'failure_url' => $request->failureUrl,
                'cancel_url' => $request->cancelUrl,
                'customer_email' => $request->customerEmail,
                'customer_name' => $request->customerName,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? 'pending';
        $paymentId = $payload['payment_id'] ?? $payload['id'] ?? null;

        return new PaymentResult(
            status: $this->mapStatus((string) $status),
            paymentId: is_string($paymentId) ? $paymentId : null,
            transactionId: is_string($payload['transaction_id'] ?? null) ? $payload['transaction_id'] : null,
            message: is_string($payload['message'] ?? null) ? $payload['message'] : null,
            gatewayResponse: $payload,
        );
    }

    public function getRedirectUrl(CheckoutSession $session): ?string
    {
        return route('demo.payment.show', ['checkoutSession' => $session]);
    }

    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        return PaymentResult::failed('Demo gateway refunds are not implemented.', [], $paymentId);
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        $session = CheckoutSession::withoutOwnerScope()
            ->where('payment_id', $paymentId)
            ->first();

        if (! $session instanceof CheckoutSession) {
            return PaymentResult::failed('Demo payment not found.', [], $paymentId);
        }

        $demoPayment = $session->payment_data['demo_gateway'] ?? [];
        $status = $this->mapStatus((string) ($demoPayment['status'] ?? 'pending'));

        return new PaymentResult(
            status: $status,
            paymentId: $paymentId,
            transactionId: is_string($demoPayment['transaction_id'] ?? null) ? $demoPayment['transaction_id'] : null,
            amount: (int) ($demoPayment['amount'] ?? $session->grand_total),
            currency: is_string($demoPayment['currency'] ?? null) ? $demoPayment['currency'] : $session->currency,
            message: is_string($demoPayment['message'] ?? null) ? $demoPayment['message'] : null,
            gatewayResponse: is_array($demoPayment) ? $demoPayment : [],
        );
    }

    private function mapStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'completed', 'success', 'paid' => PaymentStatus::Completed,
            'failed', 'error' => PaymentStatus::Failed,
            'cancelled', 'canceled' => PaymentStatus::Cancelled,
            'processing' => PaymentStatus::Processing,
            default => PaymentStatus::Pending,
        };
    }
}
