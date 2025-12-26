<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

/**
 * Exception thrown when a payment fails.
 */
final class PaymentFailedException extends CashierException
{
    /**
     * The payment intent ID or reference.
     */
    protected ?string $paymentId = null;

    /**
     * The error code from the gateway.
     */
    protected ?string $errorCode = null;

    /**
     * Create a new payment failed exception.
     *
     * @param  array<string, mixed>  $details
     */
    public static function create(string $gateway, string $message, array $details = []): static
    {
        $exception = new static($message);
        $exception->setGateway($gateway);

        if (isset($details['payment_id'])) {
            $exception->paymentId = $details['payment_id'];
        }

        if (isset($details['error_code'])) {
            $exception->errorCode = $details['error_code'];
        }

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
     * Get the error code.
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }
}
