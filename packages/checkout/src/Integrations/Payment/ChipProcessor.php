<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations\Payment;

use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\ChipPaymentStatusMapper;
use AIArmada\Checkout\Support\ChipPurchasePayloadBuilder;
use AIArmada\Checkout\Support\ChipRefundGateway;
use AIArmada\Chip\Facades\Chip;
use Throwable;

final class ChipProcessor implements PaymentProcessorInterface
{
    public function __construct(
        private readonly ChipPurchasePayloadBuilder $payloadBuilder,
        private readonly ChipPaymentStatusMapper $statusMapper,
        private readonly ChipRefundGateway $refundGateway,
    ) {}

    public function getIdentifier(): string
    {
        return 'chip';
    }

    public function getName(): string
    {
        return 'CHIP Direct';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        if (! class_exists(Chip::class)) {
            return false;
        }

        return config('chip.collect.brand_id') !== null
            && config('chip.collect.api_key') !== null;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        try {
            $purchase = Chip::createPurchase(
                $this->payloadBuilder->build($session, $request),
            );

            $checkoutUrl = $purchase->checkout_url;
            $purchaseId = $purchase->id;

            if ($checkoutUrl !== null) {
                return PaymentResult::pending(
                    paymentId: $purchaseId,
                    redirectUrl: $checkoutUrl,
                );
            }

            return PaymentResult::processing($purchaseId);
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
            $paymentId = $payload['id'] ?? null;
            $paymentStatus = $this->statusMapper->fromCallbackPayload($payload);

            return new PaymentResult(
                status: $paymentStatus,
                paymentId: $paymentId,
                transactionId: $payload['transaction_id'] ?? null,
                amount: $payload['purchase']['total'] ?? null,
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
        return $this->refundGateway->refund($paymentId, $amount, $reason);
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        try {
            $purchase = Chip::getPurchase($paymentId);

            $paymentStatus = $this->statusMapper->fromPurchaseStatus($purchase->status);

            return new PaymentResult(
                status: $paymentStatus,
                paymentId: $paymentId,
                transactionId: $purchase->reference_generated,
                amount: $purchase->purchase->total->getAmount(),
                gatewayResponse: (array) $purchase,
            );
        } catch (Throwable $e) {
            return PaymentResult::failed($e->getMessage(), [], $paymentId);
        }
    }
}
