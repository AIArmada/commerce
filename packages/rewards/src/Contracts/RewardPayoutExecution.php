<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

/**
 * Payout execution result: the provider-side outcome of one submit call.
 *
 * Status is one of `completed`, `pending`, `unknown`, or `failed`. Core maps
 * it honestly: completed settles both ledgers, pending stays submitted,
 * unknown keeps the reservation for later reconciliation, and confirmed
 * failure releases the attempt's reservation.
 */
final readonly class RewardPayoutExecution
{
    public function __construct(
        public string $status,
        public ?string $externalPayoutId = null,
        public ?string $externalReferenceId = null,
        public ?string $externalProvider = null,
        public ?string $externalReference = null,
        public ?string $failureCode = null,
    ) {}

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isUnknown(): bool
    {
        return $this->status === 'unknown';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
