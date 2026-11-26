<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

/**
 * Result of a payout provider operation.
 */
final readonly class PayoutResult
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public bool $success,
        public ?string $externalReference = null,
        public ?string $failureReason = null,
        public ?string $failureCode = null,
        public array $metadata = [],
    ) {}

    /** @param array<string, scalar|null> $metadata */
    public static function success(string $externalReference, array $metadata = []): self
    {
        return new self(true, $externalReference, metadata: $metadata);
    }

    public static function failure(string $reason = 'The payout provider rejected the request.', ?string $code = null, ?string $externalReference = null): self
    {
        return new self(false, $externalReference, failureReason: $reason, failureCode: $code);
    }

    /** @param array<string, scalar|null> $metadata */
    public static function pending(string $externalReference, array $metadata = []): self
    {
        return new self(true, $externalReference, metadata: array_merge($metadata, ['status' => 'pending']));
    }

    public static function unknown(?string $code = null, ?string $externalReference = null): self
    {
        return new self(
            false,
            $externalReference,
            failureReason: 'The provider outcome is unknown and requires reconciliation.',
            failureCode: $code ?? 'PROVIDER_OUTCOME_UNKNOWN',
            metadata: ['status' => 'unknown'],
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isPending(): bool
    {
        return $this->getStatus() === 'pending';
    }

    public function isUnknown(): bool
    {
        return $this->getStatus() === 'unknown';
    }

    public function getStatus(): string
    {
        return (string) ($this->metadata['status'] ?? ($this->success ? 'completed' : 'failed'));
    }
}
