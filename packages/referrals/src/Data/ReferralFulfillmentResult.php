<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Data;

final readonly class ReferralFulfillmentResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public bool $success,
        public ?string $externalReference = null,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}

    /** @param array<string, mixed> $metadata */
    public static function fulfilled(?string $externalReference = null, array $metadata = []): self
    {
        return new self(true, $externalReference, metadata: $metadata);
    }

    public static function failed(string $reason, ?string $externalReference = null): self
    {
        return new self(false, $externalReference, $reason);
    }
}
