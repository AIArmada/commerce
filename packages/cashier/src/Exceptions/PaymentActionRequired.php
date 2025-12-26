<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

/**
 * Exception thrown when a payment requires additional action (e.g., 3D Secure).
 */
final class PaymentActionRequired extends CashierException
{
    /**
     * The payment intent ID or reference.
     */
    protected ?string $paymentId = null;

    /**
     * The client secret for completing the payment.
     */
    protected ?string $clientSecret = null;

    /**
     * The action URL for redirecting the user.
     */
    protected ?string $actionUrl = null;

    /**
     * Create a new payment action required exception.
     */
    public static function create(
        string $gateway,
        string $paymentId,
        ?string $clientSecret = null,
        ?string $actionUrl = null,
    ): static {
        $exception = new static('Payment requires additional action.');
        $exception->setGateway($gateway);
        $exception->paymentId = $paymentId;
        $exception->clientSecret = $clientSecret;
        $exception->actionUrl = $actionUrl;

        return $exception;
    }

    /**
     * Get the payment ID.
     */
    public function paymentId(): ?string
    {
        return $this->paymentId;
    }

    /**
     * Get the client secret.
     */
    public function clientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /**
     * Get the action URL.
     */
    public function actionUrl(): ?string
    {
        return $this->actionUrl;
    }
}
