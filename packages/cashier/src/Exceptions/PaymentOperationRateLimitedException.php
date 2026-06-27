<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

final class PaymentOperationRateLimitedException extends CashierException
{
    public function __construct(
        string $message,
        private readonly string $operation,
        private readonly int $retryAfter,
    ) {
        parent::__construct($message);
    }

    public static function create(string $gateway, string $operation, int $retryAfter): self
    {
        $exception = new self(
            sprintf(
                'Payment operation [%s] on gateway [%s] is rate limited. Retry after %d seconds.',
                $operation,
                $gateway,
                $retryAfter,
            ),
            $operation,
            $retryAfter,
        );
        $exception->setGateway($gateway);

        return $exception;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
