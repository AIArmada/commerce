<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final class PayoutResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalReference = null,
        public readonly ?string $failureReason = null,
        public readonly ?string $failureCode = null,
        public readonly array $metadata = []
    ) {}

    public static function success(string $externalReference, array $metadata = []): self
    {
        return new self(
            success: true,
            externalReference: $externalReference,
            metadata: $metadata
        );
    }

    public static function failure(string $reason, ?string $code = null): self
    {
        return new self(
            success: false,
            failureReason: $reason,
            failureCode: $code
        );
    }

    public static function pending(string $externalReference, array $metadata = []): self
    {
        return new self(
            success: true,
            externalReference: $externalReference,
            metadata: array_merge($metadata, ['status' => 'pending'])
        );
    }
}
