<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Data;

final readonly class ReferralFulfillmentResult
{
    public function __construct(
        public bool $success,
        public ?string $externalReference = null,
        public ?string $reason = null,
    ) {}

    public static function fulfilled(?string $externalReference = null): self
    {
        return new self(true, $externalReference);
    }

    public static function failed(string $reason, ?string $externalReference = null): self
    {
        return new self(false, $externalReference, $reason);
    }
}
