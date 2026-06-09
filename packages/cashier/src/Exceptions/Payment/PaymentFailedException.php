<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions\Payment;

/** @phpstan-consistent-constructor */
class PaymentFailedException extends PaymentException
{
    protected ?string $paymentId = null;

    protected ?string $errorCode = null;

    /**
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

    public function paymentId(): ?string
    {
        return $this->paymentId;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }
}
