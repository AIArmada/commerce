<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Result of a payout provider operation.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class PayoutResult extends Data
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalReference = null,
        public readonly ?string $failureReason = null,
        public readonly ?string $failureCode = null,
        public readonly array $metadata = [],
    ) {}

    /** @param array<string, scalar|null> $metadata */
    public static function success(string $externalReference, array $metadata = []): self
    {
        return new self(true, $externalReference, metadata: $metadata);
    }

    public static function failure(string $reason = 'The payout provider rejected the request.', ?string $code = null): self
    {
        return new self(false, failureReason: $reason, failureCode: $code);
    }

    /** @param array<string, scalar|null> $metadata */
    public static function pending(string $externalReference, array $metadata = []): self
    {
        return new self(true, $externalReference, metadata: array_merge($metadata, ['status' => 'pending']));
    }

    public static function unknown(?string $code = null): self
    {
        return new self(
            false,
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
