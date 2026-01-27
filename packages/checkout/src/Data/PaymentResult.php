<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use AIArmada\Checkout\Enums\PaymentStatus;
use Spatie\LaravelData\Data;

final class PaymentResult extends Data
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $paymentId = null,
        public ?string $transactionId = null,
        public ?string $redirectUrl = null,
        public ?string $message = null,
        public ?int $amount = null,
        public ?string $currency = null,
        /** @var array<string, mixed> */
        public array $gatewayResponse = [],
        /** @var array<string, string> */
        public array $errors = [],
    ) {}

    public static function success(string $paymentId, ?string $transactionId = null, ?int $amount = null): self
    {
        return new self(
            status: PaymentStatus::Completed,
            paymentId: $paymentId,
            transactionId: $transactionId,
            amount: $amount,
            message: 'Payment completed successfully',
        );
    }

    public static function pending(string $paymentId, string $redirectUrl): self
    {
        return new self(
            status: PaymentStatus::Pending,
            paymentId: $paymentId,
            redirectUrl: $redirectUrl,
            message: 'Payment pending - redirect required',
        );
    }

    public static function processing(string $paymentId): self
    {
        return new self(
            status: PaymentStatus::Processing,
            paymentId: $paymentId,
            message: 'Payment is being processed',
        );
    }

    public static function failed(string $message, array $errors = [], ?string $paymentId = null): self
    {
        return new self(
            status: PaymentStatus::Failed,
            paymentId: $paymentId,
            message: $message,
            errors: $errors,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function requiresRedirect(): bool
    {
        return $this->redirectUrl !== null && $this->status === PaymentStatus::Pending;
    }
}
