<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Support\RegisterTaggedPaymentProcessors;

it('registers processors contributed by an optional provider package', function (): void {
    $processor = new class implements PaymentProcessorInterface
    {
        public function getIdentifier(): string
        {
            return 'future-provider';
        }

        public function getName(): string
        {
            return 'Future Provider';
        }

        public function isAvailable(CheckoutSession $session): bool
        {
            return true;
        }

        public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
        {
            return PaymentResult::processing('future-payment');
        }

        public function handleCallback(array $payload): PaymentResult
        {
            return new PaymentResult(PaymentStatus::Processing);
        }

        public function getRedirectUrl(CheckoutSession $session): ?string
        {
            return null;
        }

        public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
        {
            return new PaymentResult(PaymentStatus::Refunded, $paymentId, amount: $amount);
        }

        public function checkStatus(string $paymentId): PaymentResult
        {
            return new PaymentResult(PaymentStatus::Processing, $paymentId);
        }
    };

    $binding = get_class($processor);
    app()->instance($binding, $processor);
    app()->tag($binding, RegisterTaggedPaymentProcessors::TAG);

    $resolver = new PaymentGatewayResolver(null, ['future-provider']);
    app(RegisterTaggedPaymentProcessors::class)->register($resolver);

    expect($resolver->resolve('future-provider'))->toBe($processor);
});
