<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

/**
 * Result of binding an inbound callback to the session's current attempt.
 *
 * A callback must prove the generation and payment identity that own the
 * session before it may change lifecycle state, compensate, or create an
 * order. Superseded receipts are reported separately so callers can keep
 * them as attempt-specific reconciliation evidence without granting them
 * authority over the active generation.
 */
final class PaymentCallbackOwnership
{
    public function __construct(
        public readonly bool $owned,
        public readonly string $reason,
        public readonly bool $superseded = false,
        public readonly ?int $attempt = null,
        public readonly ?string $paymentId = null,
    ) {}

    public static function owned(?int $attempt = null, ?string $paymentId = null): self
    {
        return new self(true, 'Callback owns the current payment attempt', false, $attempt, $paymentId);
    }

    public static function superseded(string $reason, ?int $attempt = null, ?string $paymentId = null): self
    {
        return new self(false, $reason, true, $attempt, $paymentId);
    }

    public static function unverifiable(string $reason, ?int $attempt = null, ?string $paymentId = null): self
    {
        return new self(false, $reason, false, $attempt, $paymentId);
    }
}
