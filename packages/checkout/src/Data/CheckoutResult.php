<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\CheckoutState;
use AIArmada\Checkout\States\Completed;
use Spatie\LaravelData\Data;

final class CheckoutResult extends Data
{
    public function __construct(
        public bool $success,
        public CheckoutState $status,
        public ?string $sessionId = null,
        public ?string $orderId = null,
        public ?string $paymentId = null,
        public ?string $redirectUrl = null,
        public ?string $message = null,
        /** @var array<string, string> */
        public array $errors = [],
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}

    public static function fromSession(CheckoutSession $session, ?string $message = null): self
    {
        return new self(
            success: $session->status instanceof Completed,
            status: $session->status,
            sessionId: $session->id,
            orderId: $session->order_id,
            paymentId: $session->payment_id,
            redirectUrl: $session->payment_redirect_url,
            message: $message,
        );
    }

    public static function success(CheckoutSession $session, ?string $orderId = null): self
    {
        return new self(
            success: true,
            status: new Completed($session),
            sessionId: $session->id,
            orderId: $orderId ?? $session->order_id,
            paymentId: $session->payment_id,
            message: 'Checkout completed successfully',
        );
    }

    public static function failed(CheckoutSession $session, string $message, array $errors = []): self
    {
        return new self(
            success: false,
            status: $session->status,
            sessionId: $session->id,
            message: $message,
            errors: $errors,
        );
    }

    public static function awaitingPayment(CheckoutSession $session, string $redirectUrl): self
    {
        return new self(
            success: false,
            status: new AwaitingPayment($session),
            sessionId: $session->id,
            redirectUrl: $redirectUrl,
            message: 'Awaiting payment completion',
        );
    }

    public function requiresRedirect(): bool
    {
        return $this->redirectUrl !== null && ! $this->success;
    }
}
