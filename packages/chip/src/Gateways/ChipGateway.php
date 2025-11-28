<?php

declare(strict_types=1);

namespace AIArmada\Chip\Gateways;

use AIArmada\Chip\DataObjects\Purchase;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;
use Akaunting\Money\Money;
use Illuminate\Http\Request;

/**
 * CHIP payment gateway implementation.
 *
 * This gateway implements the universal PaymentGatewayInterface, allowing
 * CHIP to be used interchangeably with other payment gateways like Stripe,
 * PayPal, SenangPay, or eGHL.
 *
 * @example
 * ```php
 * // Using with any CheckoutableInterface (Cart, Order, etc.)
 * $payment = $gateway->createPayment($cart, $customer, [
 *     'success_url' => route('payment.success'),
 *     'failure_url' => route('payment.failed'),
 * ]);
 *
 * return redirect($payment->getCheckoutUrl());
 * ```
 */
final class ChipGateway implements PaymentGatewayInterface
{
    public function __construct(
        private ChipCollectService $service,
        private WebhookService $webhookService,
    ) {}

    public function getName(): string
    {
        return 'chip';
    }

    public function getDisplayName(): string
    {
        return 'CHIP';
    }

    public function isTestMode(): bool
    {
        return config('chip.environment', 'sandbox') === 'sandbox';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function createPayment(
        CheckoutableInterface $checkoutable,
        ?CustomerInterface $customer = null,
        array $options = []
    ): PaymentIntentInterface {
        try {
            $builder = $this->service->purchase()
                ->fromCheckoutable($checkoutable);

            if ($customer !== null) {
                $builder->fromCustomer($customer);
            }

            // Apply redirect URLs
            if (isset($options['success_url'])) {
                $builder->successUrl($options['success_url']);
            }

            if (isset($options['failure_url'])) {
                $builder->failureUrl($options['failure_url']);
            }

            if (isset($options['cancel_url'])) {
                $builder->cancelUrl($options['cancel_url']);
            }

            if (isset($options['webhook_url'])) {
                $builder->webhook($options['webhook_url']);
            }

            if (isset($options['send_receipt'])) {
                $builder->sendReceipt((bool) $options['send_receipt']);
            }

            // Handle pre-authorization mode
            if (isset($options['pre_authorize']) && $options['pre_authorize']) {
                $builder->preAuthorize(true);
            }

            // Add any additional metadata
            if (isset($options['metadata']) && is_array($options['metadata'])) {
                $metadata = array_merge(
                    $checkoutable->getCheckoutMetadata(),
                    $options['metadata']
                );
                $builder->metadata($metadata);
            }

            $purchase = $builder->create();

            return new ChipPaymentIntent($purchase);
        } catch (\Throwable $e) {
            throw PaymentGatewayException::creationFailed(
                gatewayName: $this->getName(),
                message: $e->getMessage(),
                context: [
                    'reference' => $checkoutable->getCheckoutReference(),
                    'amount' => $checkoutable->getCheckoutTotal()->getAmount(),
                    'currency' => $checkoutable->getCheckoutCurrency(),
                ],
                previous: $e
            );
        }
    }

    public function getPayment(string $paymentId): PaymentIntentInterface
    {
        try {
            $purchase = $this->service->getPurchase($paymentId);

            return new ChipPaymentIntent($purchase);
        } catch (\Throwable $e) {
            throw PaymentGatewayException::notFound($this->getName(), $paymentId);
        }
    }

    public function cancelPayment(string $paymentId): PaymentIntentInterface
    {
        try {
            $purchase = $this->service->cancelPurchase($paymentId);

            return new ChipPaymentIntent($purchase);
        } catch (\Throwable $e) {
            throw PaymentGatewayException::cancellationFailed(
                gatewayName: $this->getName(),
                paymentId: $paymentId,
                message: $e->getMessage(),
                previous: $e
            );
        }
    }

    public function refundPayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface
    {
        try {
            $amountInCents = $amount !== null ? (int) $amount->getAmount() : null;
            $purchase = $this->service->refundPurchase($paymentId, $amountInCents);

            return new ChipPaymentIntent($purchase);
        } catch (\Throwable $e) {
            throw PaymentGatewayException::refundFailed(
                gatewayName: $this->getName(),
                paymentId: $paymentId,
                message: $e->getMessage(),
                context: ['amount' => $amount?->getAmount()],
                previous: $e
            );
        }
    }

    public function capturePayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface
    {
        try {
            $amountInCents = $amount !== null ? (int) $amount->getAmount() : null;
            $purchase = $this->service->capturePurchase($paymentId, $amountInCents);

            return new ChipPaymentIntent($purchase);
        } catch (\Throwable $e) {
            throw PaymentGatewayException::captureFailed(
                gatewayName: $this->getName(),
                paymentId: $paymentId,
                message: $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPaymentMethods(array $filters = []): array
    {
        return $this->service->getPaymentMethods($filters);
    }

    public function supports(string $feature): bool
    {
        return match ($feature) {
            'refunds' => true,
            'partial_refunds' => true,
            'pre_authorization' => true,
            'recurring' => true,
            'webhooks' => true,
            'hosted_checkout' => true,
            'embedded_checkout' => false,
            'direct_charge' => true,
            default => false,
        };
    }

    public function getWebhookHandler(): WebhookHandlerInterface
    {
        return new ChipWebhookHandler($this->webhookService, $this->service);
    }
}
