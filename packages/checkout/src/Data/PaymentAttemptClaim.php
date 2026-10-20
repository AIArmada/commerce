<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use AIArmada\Checkout\Enums\PaymentClaimStatus;
use Spatie\LaravelData\Data;

/**
 * Immutable identity of the attempt a payment step may drive.
 *
 * Claimed and adopted attempts carry the frozen amount, currency, gateway,
 * provider, reference, and idempotency key captured when the attempt was
 * first claimed, so a crashed or retried attempt always reconciles the same
 * external charge.
 */
final class PaymentAttemptClaim extends Data
{
    public function __construct(
        public PaymentClaimStatus $status,
        public int $number = 0,
        public ?string $gateway = null,
        public ?string $provider = null,
        public int $amount = 0,
        public ?string $currency = null,
        public ?string $reference = null,
        public ?string $idempotencyKey = null,
        public ?string $paymentMethod = null,
        public ?string $claimedAt = null,
        public int $revision = 0,
        /** @var array<string, mixed>|null */
        public ?array $request = null,
    ) {}

    /**
     * @param  array<string, mixed>  $identity
     */
    public static function claimed(array $identity): self
    {
        return self::fromIdentity(PaymentClaimStatus::Claimed, $identity);
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    public static function adopted(array $identity): self
    {
        return self::fromIdentity(PaymentClaimStatus::Adopted, $identity);
    }

    public static function completed(): self
    {
        return new self(PaymentClaimStatus::Completed);
    }

    public static function rejected(): self
    {
        return new self(PaymentClaimStatus::Rejected);
    }

    public static function observed(): self
    {
        return new self(PaymentClaimStatus::Observed);
    }

    public function proceeds(): bool
    {
        return $this->status === PaymentClaimStatus::Claimed
            || $this->status === PaymentClaimStatus::Adopted;
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private static function fromIdentity(PaymentClaimStatus $status, array $identity): self
    {
        $provider = $identity['provider'] ?? null;

        return new self(
            status: $status,
            number: (int) ($identity['number'] ?? 0),
            gateway: is_string($identity['gateway'] ?? null) ? $identity['gateway'] : null,
            provider: is_string($provider) && $provider !== '' ? $provider : null,
            amount: (int) ($identity['amount'] ?? 0),
            currency: is_string($identity['currency'] ?? null) && $identity['currency'] !== ''
                ? $identity['currency']
                : null,
            reference: is_string($identity['reference'] ?? null) ? $identity['reference'] : null,
            idempotencyKey: is_string($identity['idempotency_key'] ?? null) ? $identity['idempotency_key'] : null,
            paymentMethod: is_string($identity['payment_method'] ?? null) ? $identity['payment_method'] : null,
            claimedAt: is_string($identity['claimed_at'] ?? null) ? $identity['claimed_at'] : null,
            revision: is_numeric($identity['revision'] ?? null) ? (int) $identity['revision'] : 0,
            request: is_array($identity['request'] ?? null) ? $identity['request'] : null,
        );
    }
}
