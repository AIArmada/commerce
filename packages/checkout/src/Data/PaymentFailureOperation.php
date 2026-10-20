<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use AIArmada\Checkout\Enums\PaymentStatus;
use Spatie\LaravelData\Data;

/**
 * Durable permission for one failing execution to compensate and fail the
 * exact attempt generation it observed under the session row lock.
 *
 * The token fences every later compensation-log, step-state, lifecycle, and
 * notification write. A replacement claim clears the token, so the stale
 * operation can observe the replacement but can never write over it.
 */
final class PaymentFailureOperation extends Data
{
    public function __construct(
        public string $token,
        public int $attempts,
        public int $revision = 0,
        public ?int $attempt = null,
        public ?string $paymentId = null,
        public ?string $gateway = null,
        public ?string $provider = null,
        public ?string $paymentStatus = null,
        public int $amount = 0,
        public ?string $currency = null,
        public ?string $startedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = $data['payment_status'] ?? null;

        return new self(
            token: (string) $data['token'],
            attempts: (int) ($data['attempts'] ?? 0),
            revision: (int) ($data['revision'] ?? 0),
            attempt: is_numeric($data['attempt'] ?? null) ? (int) $data['attempt'] : null,
            paymentId: is_string($data['payment_id'] ?? null) ? $data['payment_id'] : null,
            gateway: is_string($data['gateway'] ?? null) ? $data['gateway'] : null,
            provider: is_string($data['provider'] ?? null) ? $data['provider'] : null,
            paymentStatus: is_string($status) && PaymentStatus::tryFrom($status) !== null ? $status : null,
            amount: (int) ($data['amount'] ?? 0),
            currency: is_string($data['currency'] ?? null) ? $data['currency'] : null,
            startedAt: is_string($data['started_at'] ?? null) ? $data['started_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toRecord(): array
    {
        return [
            'token' => $this->token,
            'attempts' => $this->attempts,
            'revision' => $this->revision,
            'attempt' => $this->attempt,
            'payment_id' => $this->paymentId,
            'gateway' => $this->gateway,
            'provider' => $this->provider,
            'payment_status' => $this->paymentStatus,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'started_at' => $this->startedAt,
        ];
    }
}
