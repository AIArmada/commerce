<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions\Payment;

/** @phpstan-consistent-constructor */
class PaymentActionRequired extends PaymentException
{
    protected ?string $paymentId = null;

    protected ?string $clientSecret = null;

    protected ?string $actionUrl = null;

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

    public function paymentId(): ?string
    {
        return $this->paymentId;
    }

    public function clientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function actionUrl(): ?string
    {
        return $this->actionUrl;
    }
}
