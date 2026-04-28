<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Events\CheckoutPaymentCompleted;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Processing;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class ProcessPaymentStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly PaymentGatewayResolverInterface $paymentResolver,
    ) {}

    public function getIdentifier(): string
    {
        return 'process_payment';
    }

    public function getName(): string
    {
        return 'Process Payment';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['calculate_shipping'];
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $errors = [];

        if ($session->grand_total <= 0) {
            // Free order - no payment needed
            return $errors;
        }

        $gateway = $session->selected_payment_gateway ?? config('checkout.payment.default_gateway');

        if ($gateway !== null && ! $this->paymentResolver->hasGateway($gateway)) {
            $errors['payment_gateway'] = "Selected payment gateway '{$gateway}' is not available";
        }

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        // Handle free orders (e.g., 100% discount)
        if ($session->grand_total <= 0) {
            $session->update([
                'payment_data' => [
                    'type' => 'free_order',
                    'processed_at' => now()->toIso8601String(),
                ],
            ]);
            if (! $session->status->is(Processing::class)) {
                $session->transitionStatus(Processing::class);
            }

            return $this->success('No payment required - free order');
        }

        $gateway = $session->selected_payment_gateway;
        $processor = $this->paymentResolver->resolve($gateway);

        // Check if processor is available for this session
        if (! $processor->isAvailable($session)) {
            return $this->failed('Selected payment method is not available', [
                'payment' => 'Payment method not available for this order',
            ]);
        }

        $callbackToken = $this->ensureCallbackToken($session);

        // Build payment request
        $paymentRequest = $this->buildPaymentRequest($session);

        // Increment payment attempts
        $session->update([
            'payment_attempts' => $session->payment_attempts + 1,
            'selected_payment_gateway' => $processor->getIdentifier(),
        ]);
        $session->transitionStatus(PaymentProcessing::class);

        // Process payment
        $result = $processor->createPayment($session, $paymentRequest);

        // Handle payment result - merge into existing payment_data to preserve callback_token
        // (ensureCallbackToken() stored it above; replacing the whole array would wipe it,
        //  causing all redirect-based callbacks to fail token validation).
        $paymentData = array_merge($session->payment_data ?? [], [
            'gateway' => $processor->getIdentifier(),
            'payment_id' => $result->paymentId,
            'transaction_id' => $result->transactionId,
            'status' => $result->status->value,
            'amount' => $result->amount ?? $session->grand_total,
            'currency' => $result->currency ?? $session->currency,
            'gateway_response' => $result->gatewayResponse,
            'processed_at' => now()->toIso8601String(),
        ]);

        $session->update([
            'payment_id' => $result->paymentId,
            'payment_data' => $paymentData,
        ]);

        // Handle redirect-based payments
        if ($result->requiresRedirect()) {
            $session->update(['payment_redirect_url' => $result->redirectUrl]);
            $session->transitionStatus(AwaitingPayment::class);

            return $this->success('Payment initiated - redirect required', [
                'redirect_url' => $result->redirectUrl,
                'payment_id' => $result->paymentId,
            ]);
        }

        // Handle immediate success
        if ($result->isSuccessful()) {
            event(new CheckoutPaymentCompleted(
                session: $session,
                paymentData: $paymentData,
            ));
            if (! $session->status->is(Processing::class)) {
                $session->transitionStatus(Processing::class);
            }

            return $this->success('Payment completed', [
                'payment_id' => $result->paymentId,
                'transaction_id' => $result->transactionId,
            ]);
        }

        // Handle failure
        $session->transitionStatus(PaymentFailed::class);

        return $this->failed($result->message ?? 'Payment failed', $result->errors);
    }

    public function rollback(CheckoutSession $session): void
    {
        // Payment rollback is typically handled via refunds
        // This is a no-op as we don't want to automatically refund on rollback
        // Refunds should be explicit actions
    }

    private function buildPaymentRequest(CheckoutSession $session): PaymentRequest
    {
        $billingData = $session->billing_data ?? [];
        $customer = $session->customer;

        $customerName = $billingData['name'] ?? null;
        if ($customerName === null && $customer !== null) {
            $customerName = $customer->full_name ?? $customer->first_name ?? null;
        }

        return new PaymentRequest(
            amount: $session->grand_total,
            currency: $session->currency,
            gateway: $session->selected_payment_gateway,
            description: "Order checkout - Session {$session->id}",
            customerEmail: $billingData['email'] ?? $customer?->email,
            customerName: $customerName,
            customerPhone: $billingData['phone'] ?? $customer?->phone,
            successUrl: $this->buildCallbackUrl('success', $session),
            failureUrl: $this->buildCallbackUrl('failure', $session),
            cancelUrl: $this->buildCallbackUrl('cancel', $session),
            metadata: [
                'checkout_session_id' => $session->id,
                'cart_id' => $session->cart_id,
                'customer_id' => $session->customer_id,
            ],
        );
    }

    private function buildCallbackUrl(string $type, CheckoutSession $session): string
    {
        $callbackToken = $session->payment_data['callback_token'] ?? null;

        $routeName = match ($type) {
            'success' => 'checkout.payment.success',
            'failure' => 'checkout.payment.failure',
            'cancel' => 'checkout.payment.cancel',
            default => 'checkout.payment.success',
        };

        // Use route if available, fall back to config path
        if (Route::has($routeName)) {
            return route($routeName, [
                'session' => $session->id,
                'checkout_callback_token' => $callbackToken,
            ]);
        }

        $prefix = mb_trim((string) config('checkout.routes.prefix', 'checkout'), '/');
        $callbackPath = mb_trim((string) config("checkout.routes.callbacks.{$type}", "payment/{$type}"), '/');
        $path = mb_trim($prefix . '/' . $callbackPath, '/');
        $separator = str_contains($path, '?') ? '&' : '?';

        return url($path . $separator . http_build_query([
            'session' => $session->id,
            'checkout_callback_token' => $callbackToken,
        ]));
    }

    private function ensureCallbackToken(CheckoutSession $session): string
    {
        $paymentData = $session->payment_data ?? [];
        $callbackToken = $paymentData['callback_token'] ?? null;

        if (is_string($callbackToken) && $callbackToken !== '') {
            return $callbackToken;
        }

        $callbackToken = Str::random(40);
        $paymentData['callback_token'] = $callbackToken;
        $paymentData['callback_token_created_at'] = now()->toIso8601String();

        $session->update(['payment_data' => $paymentData]);

        return $callbackToken;
    }
}
